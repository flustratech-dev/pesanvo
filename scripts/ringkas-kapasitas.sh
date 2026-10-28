#!/bin/bash
#
# Meringkas /var/log/pesanvo-capacity.log jadi empat jawaban.
#
# Perekamnya menulis satu baris per jam:
#
#   <iso8601> cur= peak= swap= profil= proses= zombie= hostavail= hostswap=
#
# Yang dicari dari deretan itu bukan angka tertingginya melainkan LANTAInya.
# Puncak naik-turun mengikuti jumlah sesi dan itu wajar; lantai — pemakaian
# terendah dalam satu hari, yaitu saat beban paling sepi — tidak punya alasan
# untuk naik. Lantai yang naik adalah memori yang tidak pernah dikembalikan,
# dan itulah definisi kebocoran. Puncak yang tinggi dengan lantai mendatar cuma
# berarti sistemnya sibuk.
#
# Pemakaian:  ./ringkas-kapasitas.sh [berkas-log]

set -u

LOG="${1:-/var/log/pesanvo-capacity.log}"

if [ ! -r "$LOG" ]; then
    echo "Tidak bisa membaca $LOG" >&2
    exit 1
fi

BARIS="$(grep -c 'cur=' "$LOG" 2>/dev/null || echo 0)"

if [ "$BARIS" -lt 2 ]; then
    echo "Baru $BARIS baris terekam. Perekamnya berjalan tiap jam; butuh"
    echo "minimal 48 baris (dua hari) sebelum tren lantai berarti apa-apa."
    exit 0
fi

awk '
function angka(baris, kunci,   potong) {
    if (match(baris, kunci "=[0-9]+")) {
        potong = substr(baris, RSTART, RLENGTH)
        sub(kunci "=", "", potong)
        return potong + 0
    }
    return -1
}

/cur=/ {
    hari = substr($1, 1, 10)
    cur = angka($0, "cur")
    peak = angka($0, "peak")
    swap = angka($0, "swap")
    profil = angka($0, "profil")
    zombie = angka($0, "zombie")

    n++

    if (peak > peakMaks) peakMaks = peak
    if (swap > swapMaks) swapMaks = swap
    if (profil > profilMaks) profilMaks = profil

    # Lantai per hari: pemakaian TERENDAH hari itu.
    if (!(hari in lantai) || cur < lantai[hari]) lantai[hari] = cur
    if (!(hari in atap) || cur > atap[hari]) atap[hari] = cur
    hariAda[hari] = 1

    # Zombie: yang diukur lajunya, bukan jumlahnya. Angkanya di-reset tiap
    # container dibuat ulang, jadi penurunan berarti restart, bukan perbaikan.
    if (n == 1) { zombieAwal = zombie; waktuAwal = $1 }
    zombieAkhir = zombie
    waktuAkhir = $1
    curAkhir = cur
}

END {
    if (n == 0) { print "Tidak ada baris yang bisa dibaca."; exit }

    printf "Sampel        : %d baris\n", n
    printf "Rentang       : %s sampai %s\n", waktuAwal, waktuAkhir
    printf "memory.peak   : %d MB (tertinggi yang pernah tercatat)\n", peakMaks
    printf "swap tertinggi: %d MB\n", swapMaks
    printf "cur terakhir  : %d MB\n", curAkhir
    printf "profil maks   : %d\n\n", profilMaks

    print "LANTAI PER HARI (pemakaian terendah tiap hari)"
    print "Lantai yang naik = memori yang tidak pernah dikembalikan."
    print ""

    jml = 0
    for (h in hariAda) urut[jml++] = h
    for (i = 0; i < jml; i++)
        for (j = i + 1; j < jml; j++)
            if (urut[i] > urut[j]) { t = urut[i]; urut[i] = urut[j]; urut[j] = t }

    for (i = 0; i < jml; i++) {
        h = urut[i]
        delta = (i == 0) ? 0 : lantai[h] - lantai[urut[i-1]]
        printf "  %s  lantai %5d MB  atap %5d MB", h, lantai[h], atap[h]
        if (i > 0) printf "   selisih lantai %+d MB", delta
        printf "\n"
    }

    print ""

    if (jml < 3) {
        print "VONIS: belum bisa. Butuh minimal 3 hari penuh sebelum tren lantai"
        print "       bisa dibedakan dari kebetulan."
    } else {
        naikTotal = lantai[urut[jml-1]] - lantai[urut[0]]
        perHari = naikTotal / (jml - 1)

        printf "Laju lantai   : %+.0f MB/hari selama %d hari\n\n", perHari, jml

        if (perHari > 150) {
            printf "VONIS: MASIH BOCOR. Lantai naik %.0f MB/hari.\n", perHari
            printf "       Pada laju ini batas container 3584 MB tercapai dalam %.0f hari.\n", (3584 - lantai[urut[jml-1]]) / perHari
            print  "       Periksa status koneksi dan memory di endpoint /health."
            print  "       Periksa apakah ada churn reconnect websocket yang berulang."
        } else if (perHari > 50) {
            printf "VONIS: BELUM PASTI. Lantai naik %.0f MB/hari — di atas derau harian\n", perHari
            print  "       tapi belum jelas kebocoran. Tunggu tiga hari lagi sebelum"
            print  "       mengubah apa pun."
        } else {
            printf "VONIS: MENDATAR. Lantai bergerak %.0f MB/hari, di dalam derau harian.\n", perHari
            print  "       Tidak ada kebocoran memori yang berjalan."
        }
    }

    print ""
    print "ZOMBIE"
    jam = 0
    if (n > 1) jam = n - 1
    if (jam > 0 && zombieAkhir >= zombieAwal)
        printf "  %d -> %d selama ~%d jam = %.1f/jam\n", zombieAwal, zombieAkhir, jam, (zombieAkhir - zombieAwal) / jam
    else
        printf "  %d -> %d (turun: container dibuat ulang di tengah rentang)\n", zombieAwal, zombieAkhir
    print  "  Induknya php artisan serve, bukan engine. Bukan ancaman:"
    print  "  kernel.pid_max di server ini 4.194.304. Utang teknis yang dicatat,"
    print  "  bukan diperbaiki di deploy ini - lihat docs/CUTOVER.md 5."
    print  "  Yang perlu diawasi LAJUnya: melonjak = sesi sering mati-hidup."
}
' "$LOG"

echo ""
echo "KAPASITAS AMAN"
echo "  Isi angkanya dari baris di atas:"
echo ""
echo "    baseline  = memory.peak tertinggi - (profil maks x biaya per sesi)"
echo "    per sesi  = (memory.peak tertinggi - baseline) / profil maks"
echo "    sesi aman = (3584 x 0.8 - baseline) / per sesi"
echo ""
echo "  0,8 itu ruang kepala 20%: batas container adalah titik container"
echo "  DIBUNUH, bukan titik yang boleh disentuh. Bulatkan ke bawah, selalu."
