#!/bin/bash
#
# Memantau kesehatan container pesanvo dan engine websocket (Baileys).
# Dijalankan di HOST, bukan di dalam container.
#
# Pemakaian:  ./pantau.sh

set -u

NAMA="${1:-pesanvo}"
CID="$(docker ps -qf "name=${NAMA}" | head -1)"

if [ -z "$CID" ]; then
    echo "Container yang namanya memuat '${NAMA}' tidak sedang berjalan." >&2
    exit 1
fi

echo "=============================================================="
echo " Pemantauan pesanvo - $(date -Is)"
echo "=============================================================="
echo ""

# --- 1. Engine Baileys: Sesi Websocket & Memori ------------------------------
echo "1. ENGINE BAILEYS: SESI & KONEKSI WEBSOCKET"

SEHAT="$(docker exec "$CID" sh -c 'curl -sf --max-time 5 -H "X-Engine-Token: $ENGINE_TOKEN" http://127.0.0.1:${ENGINE_PORT:-3100}/health' 2>/dev/null)"

if [ -z "$SEHAT" ]; then
    echo "   engine tidak menjawab - itu sendiri sudah temuan"
else
    ambil() { echo "$SEHAT" | grep -o "\"$1\":-\?[0-9]*" | cut -d: -f2; }

    SESI="$(ambil sessions)"
    CONN="$(ambil connected_sessions)"
    CONNECTING="$(ambil connecting_sessions)"
    QR="$(ambil qr_sessions)"
    MAX="$(ambil max_sessions)"
    RSS="$(ambil rss_mb)"
    HEAP="$(ambil heap_used_mb)"

    echo "   total_sesi=${SESI:-?}  tersambung=${CONN:-?}  menghubungkan=${CONNECTING:-0}  qr=${QR:-0}  maks=${MAX:-?}"
    echo "   memori_engine: rss=${RSS:-?}MB  heap_used=${HEAP:-?}MB"

    if [ "${CONN:-0}" = "${SESI:-0}" ]; then
        echo "   -> semua sesi terhubung normal."
    else
        echo "   -> PERHATIAN: ada sesi belum tersambung (tersambung ${CONN:-0} dari ${SESI:-0})."
    fi
fi
echo ""

# --- 2. Zombie ---------------------------------------------------------------
echo "2. ZOMBIE DI HOST  (puluhan wajar; yang penting LAJUnya mendatar)"
echo "   Induknya 'php artisan serve', bukan penjadwal. Dibiarkan dengan sengaja -"
echo "   pid_max 4.194.304, jadi puluhan zombie tidak berbahaya. Utang teknis."
Z="$(ps -eo stat= 2>/dev/null | grep -c '^Z' || echo 0)"
echo "   $Z"
[ "$Z" -gt 500 ] && echo "   -> laju tidak wajar; periksa churn sesi, bukan zombie-nya"
echo ""

# --- 3. Memori container -----------------------------------------------------
#
# Dari cgroup, BUKAN dari jumlah RSS.
echo "3. MEMORI CONTAINER  (lantai tidak boleh naik lintas hari)"
docker exec "$CID" sh -c '
  for f in current peak max; do
    v=$(cat /sys/fs/cgroup/memory.$f 2>/dev/null)
    [ -n "$v" ] && [ "$v" != "max" ] && echo "   memory.$f = $((v / 1048576)) MB" || echo "   memory.$f = $v"
  done
  s=$(cat /sys/fs/cgroup/memory.swap.current 2>/dev/null); [ -n "$s" ] && echo "   swap      = $((s / 1048576)) MB"
    echo "   --- memory.events ---"
    sed "s/^/   /" /sys/fs/cgroup/memory.events 2>/dev/null
' 2>/dev/null || echo "   cgroup tidak terbaca"
echo ""

# --- 4. Tabel yang tumbuh ----------------------------------------------------
echo "4. TABEL YANG TUMBUH  (mendatar setelah retensi berjalan)"
docker exec "$CID" php artisan tinker --execute="
foreach (['webhook_deliveries','audit_logs','messages','notifications','failed_jobs','jobs','cache'] as \$t) {
    \$r = DB::selectOne('SELECT COUNT(*) n FROM '.\$t);
    echo '   '.str_pad(\$t, 20).number_format(\$r->n).PHP_EOL;
}
" 2>/dev/null | grep -v '^$' || echo "   tidak terbaca"

echo ""
echo "=============================================================="
echo "Tren lantai memori beberapa hari: scripts/ringkas-kapasitas.sh"
echo "=============================================================="
