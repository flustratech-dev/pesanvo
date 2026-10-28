#!/bin/bash

# Titik masuk container produksi pesanvo.
#
# SATU resource Coolify menjalankan EMPAT proses: web, engine WhatsApp (Node +
# Baileys websocket), worker antrean, dan penjadwal.
#
# Konsekuensi yang harus disadari: kalau proses WEB mati, seluruh container ikut
# berhenti — termasuk engine. Itu disengaja; Coolify menghidupkannya kembali
# dengan Restart Policy `always`, dan sesi WhatsApp pulih sendiri dari cadangan
# di Laravel. Yang TIDAK boleh terjadi adalah container mati tanpa engine sempat
# menyimpan kredensial; itu yang ditangani `matikan()` di bawah.

set -u

cd "$(dirname "$0")" || exit 1

mkdir -p storage/logs

PORT_WEB="${PORT_WEB:-80}"
URL_KESEHATAN="http://127.0.0.1:${PORT_WEB}/up"

# Penanda bahwa container sedang berhenti dengan sengaja. Tanpa ini, loop
# pengawas akan dengan patuh menghidupkan lagi proses yang baru saja kita bunuh.
TANDA_BERHENTI="/tmp/pesanvo.berhenti"
PIDFILE_ENGINE="/tmp/pesanvo-engine.pid"
rm -f "$TANDA_BERHENTI" "$PIDFILE_ENGINE"

# --- Engine WhatsApp (Node + Baileys) -------------------------------------
#
# Engine menanyakan daftar sesi ke Laravel saat boot (`/internal/engine/bootstrap`)
# dan TIDAK pernah mengulang panggilan itu kalau gagal — ia cuma mencatat
# "engine tetap jalan tanpa sesi" lalu diam. Selama engine jadi resource
# terpisah hal itu jarang menggigit karena Laravel sudah lama hidup duluan.
# Dalam satu container justru sebaliknya: keduanya lahir berbarengan, dan tanpa
# penantian di bawah ini setiap deploy dimulai dengan nol sesi aktif sampai ada
# yang menekan tombol Hubungkan.
#
# Penantiannya dibatasi 120 detik supaya web yang benar-benar rusak tidak
# membuat engine ikut diam selamanya; sesudah itu engine jalan apa adanya dan
# `bootstrap()` di sisi Node punya percobaan ulangnya sendiri.
supervisi_engine() {
    local tunggu=0

    until curl -sfo /dev/null --max-time 2 "$URL_KESEHATAN"; do
        [ -f "$TANDA_BERHENTI" ] && return

        tunggu=$((tunggu + 1))

        if [ "$tunggu" -ge 120 ]; then
            echo "[start.sh] Web belum menjawab setelah 120 detik; engine dijalankan apa adanya."
            break
        fi

        sleep 1
    done

    while [ ! -f "$TANDA_BERHENTI" ]; do
        node engine/src/server.js &
        echo $! > "$PIDFILE_ENGINE"
        wait $! || true

        rm -f "$PIDFILE_ENGINE"

        [ -f "$TANDA_BERHENTI" ] && break

        echo "[start.sh] engine berhenti, dijalankan ulang dalam 3 detik."
        sleep 3
    done
}

# --- Worker antrean -------------------------------------------------------
#
# WAJIB ADA. QUEUE_CONNECTION=database, jadi tanpa worker setiap pesan berhenti
# di status `queued` selamanya dan tidak ada satu pun webhook yang terkirim.
#
# `--max-time=3600` membuatnya berhenti terjadwal tiap jam lalu dijalankan ulang
# oleh loop — cara paling murah menahan kebocoran memori proses yang hidup
# berhari-hari. `--timeout=240` menyamai ENGINE_SEND_TIMEOUT ditambah kelonggaran
# untuk antrean anti-ban engine.
supervisi_worker() {
    while [ ! -f "$TANDA_BERHENTI" ]; do
        php artisan queue:work --sleep=3 --tries=3 --timeout=240 --max-time=3600

        [ -f "$TANDA_BERHENTI" ] && break

        echo "[start.sh] queue:work berhenti, dijalankan ulang dalam 2 detik."
        sleep 2
    done
}

# --- Penjadwal ------------------------------------------------------------
#
# Menjalankan SyncSessionStatusJob tiap menit dan PruneOldRecordsJob tiap dini
# hari (routes/console.php). Yang pertama adalah jaring pengaman status sesi:
# kalau engine mati mendadak, tidak ada event `disconnected` yang terkirim dan
# dashboard akan terus menampilkan sesi sebagai terhubung padahal sudah tidak.
#
# Menggantikan Scheduled Task di Coolify. Scheduled Task berjalan di container
# yang dibuat khusus untuk itu — build ulang kecil tiap menit; ini cuma satu
# proses PHP yang tidur di antara pemeriksaan.
# `schedule:run` dalam loop bash, BUKAN `schedule:work`.
supervisi_penjadwal() {
    while [ ! -f "$TANDA_BERHENTI" ]; do
        # Tidur sampai pergantian menit berikutnya.
        sleep $((60 - $(date +%-S)))

        [ -f "$TANDA_BERHENTI" ] && break

        # Dijalankan di latar lalu ditunggu, supaya penanda berhenti tetap bisa
        # diperiksa tanpa menunggu `schedule:run` yang kebetulan lama selesai.
        php artisan schedule:run >/dev/null 2>&1 &
        wait $! || true
    done
}

# --- Penghentian yang rapi ------------------------------------------------
#
# Satu-satunya kesempatan menyimpan kredensial yang berubah sejak backup berkala
# terakhir adalah SIGTERM yang sampai ke proses Node. Kalau shell ini mati tanpa
# meneruskan sinyalnya, engine kena SIGKILL dan kredensial belum tercadangkan.
#
# Engine didahulukan dan ditunggu. Batasnya 10 detik karena Docker hanya memberi
# ~10 detik sebelum SIGKILL, dan engine sendiri sudah membatasi penyimpanan 8
# detik per sesi. Kalau Coolify disetel dengan grace period lebih panjang, angka
# ini boleh ikut dinaikkan.
matikan() {
    trap '' TERM INT

    echo "[start.sh] Sinyal berhenti diterima; menyimpan kredensial sesi lebih dulu."

    touch "$TANDA_BERHENTI"

    local pid_engine
    pid_engine="$(cat "$PIDFILE_ENGINE" 2>/dev/null || true)"

    if [ -n "$pid_engine" ]; then
        kill -TERM "$pid_engine" 2>/dev/null || true

        for _ in $(seq 1 10); do
            kill -0 "$pid_engine" 2>/dev/null || break
            sleep 1
        done
    fi

    kill -TERM "${PID_WEB:-}" "${PID_WORKER:-}" "${PID_PENJADWAL:-}" "${PID_ENGINE:-}" 2>/dev/null || true

    exit 0
}

# --- Menyalakan -----------------------------------------------------------
#
# Web dijalankan di latar belakang, bukan lewat `exec`, supaya shell ini tetap
# hidup sebagai PID 1 dan bisa menangkap SIGTERM untuk `matikan()` di atas.
#
# Migrasi database dijalankan di sini sebelum web dan engine melayani permintaan.
echo "[start.sh] Menjalankan migrasi database."

if ! php artisan migrate --force; then
    cat >&2 <<'GAGAL'
[start.sh] ============================================================
[start.sh] MIGRASI GAGAL. Container dihentikan sebelum melayani apa pun.
[start.sh]
[start.sh] Aplikasi TIDAK dinyalakan dengan sengaja: melayani permintaan
[start.sh] dengan skema database setengah jadi merusak data, sementara
[start.sh] container yang mati cuma perlu diperbaiki lalu di-deploy ulang.
[start.sh]
[start.sh] Periksa: DB_HOST/DB_DATABASE/DB_USERNAME di environment resource,
[start.sh] dan baris galat tepat di atas kotak ini.
[start.sh] ============================================================
GAGAL
    exit 1
fi

# Admin dibuat dan disamakan dengan env ADMIN_* setiap boot. Dulu ini langkah
# manual `db:seed` yang harus diingat seseorang — dan produksi pertama yang
# dimulai dari database kosong berakhir tanpa admin sama sekali, sementara
# pemiliknya disuruh mendaftar ulang dengan kredensial yang sebenarnya benar.
# Gagal di sini tidak menghentikan container: layanan pelanggan lebih penting
# daripada akun admin, dan pesan galatnya tetap tercatat di log.
echo "[start.sh] Menyamakan akun admin dengan env ADMIN_*."

if ! php artisan db:seed --class=AdminUserSeeder --force; then
    echo "[start.sh] PERINGATAN: akun admin gagal disamakan dengan env; periksa ADMIN_* dan baris galat di atas." >&2
fi

echo "[start.sh] Menyalakan web, engine WhatsApp (Baileys), worker, dan penjadwal dalam satu container."

php artisan serve --host=0.0.0.0 --port="$PORT_WEB" --no-reload &
PID_WEB=$!

supervisi_engine &
PID_ENGINE=$!

supervisi_worker &
PID_WORKER=$!

supervisi_penjadwal &
PID_PENJADWAL=$!

trap matikan TERM INT

# Menunggu proses web. Kalau ia mati, biarkan container berhenti supaya
# Restart Policy Coolify membangunkan semuanya bersih-bersih.
wait "$PID_WEB"

echo "[start.sh] Proses web berhenti; menutup sisa proses."
matikan
