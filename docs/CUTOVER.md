# Cutover — Migrasi Engine pesanvo: whatsapp-web.js → Baileys

Dokumen panduan cutover dan operasional deploy migrasi engine WhatsApp dari
`whatsapp-web.js` (Chromium-based) ke **Baileys** (`@whiskeysockets/baileys` websocket murni).

---

## Ringkasan Perubahan Engine

| Komponen | Sebelum (whatsapp-web.js) | Sesudah (Baileys) |
|---|---|---|
| **Arsitektur** | Headless Chromium + browser automation | Koneksi WebSocket langsung ke WhatsApp |
| **Memori baseline container (0 sesi)** | **446 MB** (TERUKUR di produksi) | **446 MB** (TERUKUR di produksi) |
| **Memori per sesi (stabil / idle)** | **730 MB** (TERUKUR di produksi) | **BELUM DIUKUR SAMA SEKALI** |
| **Memori per sesi (puncak / koneksi)** | **1.385 MB** (TERUKUR di produksi) | **BELUM DIUKUR SAMA SEKALI** |
| **Batas sesi aman (`WA_MAX_SESSIONS`)** | **3 sesi** (TERUKUR: 1 puncak 1.385 MB + 2 stabil 1.460 MB + baseline 446 MB = 3.291 MB, mendekati limit 3.584 MB) | **Dipertahankan 3 sesi** (Batas baru belum diketahui sampai ada pengukuran riil) |
| **Dependensi OS** | Libnss3, libatk, libgtk, fonts (~180 MB apt) | Node.js standar + curl saja |
| **Format Kredensial** | `session.zip` multi-MB | `session.json` (lossless multi-file auth) |
| **Kontrak HTTP Internal** | Port 3100, token statis, endpoints sama | **Sama persis (tidak ada perubahan kontrak)** |

> [!IMPORTANT]
> **Kredensial WhatsApp Web (Chromium) TIDAK DAPAT dimigrasikan langsung ke Baileys.**
> Kunci sesi WhatsApp Web berbasis LocalStorage/IndexedDB Chromium, sedangkan Baileys menggunakan MultiFileAuthState (Noise/Signal protocol keys). Seluruh nomor yang aktif di produksi **wajib ditautkan ulang (scan QR)** saat cutover.

---

## 1. Urutan Cutover 2 Nomor Produksi

Saat ini terdapat **2 nomor aktif** di produksi:
1. **Nomor Internal / Tim Pesanvo** (pengujian & notifikasi sistem)
2. **Nomor Produksi / Pelanggan** (trafik pesan nyata)

### Urutan Pelaksanaan

1. **Jadwalkan Jendela Pemeliharaan (2–5 Menit)**
   - Waktu terbaik: di luar jam sibuk bisnis pelanggan (misal: 21:00–22:00 WIB).
   - Beri tahu pengguna bahwa ada pembaruan infrastruktur gateway selama ±5 menit.

2. **Persepsi & Dampak Pelanggan Selama Cutover**
   - **Pesan Masuk (Inbound):** Pesan yang dikirim pengguna WhatsApp ke nomor tetap aman di server WhatsApp. Begitu nomor berhasil di-scan kembali pada engine Baileys, pesan yang belum terbaca akan diterima.
   - **Pesan Keluar (Outbound):** Laravel menahan pesan di antrean (`jobs` database). Job pengiriman yang mencoba mengirim saat sesi belum tersambung akan menerima status 503 sementara, lalu dicoba kembali secara otomatis (`retry_after` / percobaan ulang queue). **Tidak ada pesan yang hilang.**
   - **Dashboard:** Kartu sesi menampilkan status `disconnected` atau `qr`.

3. **Langkah 1: Deploy Kode Baru ke Coolify**
   - Trigger deploy branch `main` di Coolify.
   - Container membangun image baru (tanpa download Chromium, build jauh lebih cepat).
   - Migrasi database dijalankan otomatis oleh `start.sh`.
   - Engine Baileys menyala di port 3100.

4. **Langkah 2: Tautkan Nomor 1 (Internal Tim)**
   - Buka Dashboard Pesanvo → Menu **Sesi**.
   - Klik **Hubungkan** pada nomor internal.
   - Scan QR code yang muncul di layar dengan aplikasi WhatsApp di HP.
   - Verifikasi status berubah menjadi `connected` (hijau).
   - Jalankan uji kirim pesan teks dan media.

5. **Langkah 3: Tautkan Nomor 2 (Produksi Pelanggan)**
   - Hubungi pemegang HP nomor pelanggan (atau koordinasi langsung).
   - Klik **Hubungkan** pada nomor produksi di dashboard.
   - Scan QR code yang tampil.
   - Begitu terhubung, antrean pesan keluar di database akan otomatis terkirim.
   - Pantau log worker dan webhook pesan keluar/masuk.

---

## 2. Uji Asap Pasca-Deploy

Jalankan blok berikut langsung di terminal server (HOST):

```bash
CID=$(docker ps -qf name=pesanvo)

echo "== 1. Status Container ==" && docker ps --filter name=pesanvo --format '{{.Status}}'

echo "== 2. Log Booting ==" && docker logs --tail 80 "$CID" 2>&1 | grep -E 'start.sh|migrat|Baileys|Engine'

echo "== 3. Health Endpoint Engine ==" && docker exec "$CID" sh -c \
  'curl -s -H "X-Engine-Token: $ENGINE_TOKEN" http://127.0.0.1:${ENGINE_PORT:-3100}/health'

echo && echo "== 4. Status Sesi di Database ==" && docker exec "$CID" php artisan tinker --execute="
DB::table('wa_sessions')->select('name','status','connect_failures')->get()
  ->each(fn(\$s) => print(str_pad(\$s->name,20).str_pad(\$s->status,14).\$s->connect_failures.PHP_EOL));"

echo "== 5. Antrean Pesan ==" && docker exec "$CID" php artisan tinker --execute="
echo 'jobs='.DB::table('jobs')->count().' failed='.DB::table('failed_jobs')->count().PHP_EOL;"
```

### Hasil yang Diharapkan:

1. **Container:** `Up ... seconds` tanpa status `Restarting`.
2. **Log Booting:** Memuat `Menyalakan web, engine WhatsApp (Baileys)...` dan `Engine WhatsApp berjalan (Baileys)`. Tidak ada error Chromium atau missing libraries.
3. **Health Endpoint:**
   ```json
   {
     "status": "ok",
     "engine": "baileys",
     "sessions": 2,
     "connected_sessions": 2,
     "connecting_sessions": 0,
     "qr_sessions": 0,
     "max_sessions": 20,
     "memory": {
       "rss_mb": 145,
       "heap_used_mb": 62,
       "heap_total_mb": 85
     },
     "uptime_seconds": 120
   }
   ```
4. **Sesi:** Keduanya berstatus `connected` dengan `connect_failures: 0`.
5. **Antrean:** `jobs` berkurang mendekati 0 saat semua pesan terkirim.

---

## 3. Kapasitas & Metrik Memori Produksi (Ground Truth)

### Angka Riil Server Produksi

| Metrik | Status | Nilai | Keterangan |
|---|---|---|---|
| **RAM Server Total** | **TERUKUR** | `7.940 MB` | Total RAM fisik VPS Pesanvo |
| **Batas RAM Container Gateway** | **TERUKUR** | `3.584 MB` | Limit cgroup container Coolify |
| **Baseline Gateway (0 Sesi)** | **TERUKUR** | `446 MB` | Container menyala (PHP-FPM, Nginx, Node engine, worker, scheduler) tanpa sesi aktif |
| **Biaya per Sesi Chromium Lama (Stabil / Idle)** | **TERUKUR** | `730 MB` / sesi | Pemakaian steady-state per browser WhatsApp Web |
| **Biaya per Sesi Chromium Lama (Puncak / Koneksi)** | **TERUKUR** | `1.385 MB` / sesi | Lonjakan puncak saat peluncuran browser & sync awal |
| **Biaya per Sesi Baileys Baru (Stabil / Idle)** | **BELUM DIUKUR** | *Belum ada data* | Belum ada nomor riil yang tersambung cukup lama di produksi |
| **Biaya per Sesi Baileys Baru (Puncak / Koneksi)** | **BELUM DIUKUR** | *Belum ada data* | Belum ada pengukuran saat autentikasi socket & pertukaran key |

### Kenapa `WA_MAX_SESSIONS` Dipertahankan di Angka 3

- **Kondisi Chromium (Terukur):**
  $$\text{Beban Puncak} = 1 \text{ sesi puncak } (1.385\text{ MB}) + 2 \text{ sesi idle } (1.460\text{ MB}) + \text{baseline } (446\text{ MB}) = 3.291\text{ MB}$$
  Nilai 3.291 MB sudah menyerap 91,8% dari batas 3.584 MB. Batas `WA_MAX_SESSIONS=3` adalah batas matematika keras untuk mencegah OOM killer.
- **Kondisi Baileys (Belum Diukur):**
  Karena biaya per sesi Baileys di server produksi **BELUM DIUKUR SAMA SEKALI**, platform **BELUM TAHU** batas amannya berapa. Jangan mengubah `WA_MAX_SESSIONS` sebelum ada data pengukuran riil dari nomor produksi yang tersambung.

### Prosedur Pengukuran Memori Baileys Saat Nomor Produksi Tersambung

Pengukuran wajib dilakukan secara empiris di server produksi menggunakan kernel cgroup:

1. **Catat Baseline Bersih (0 Sesi):**
   ```bash
   CID=$(docker ps -qf name=pesanvo)
   docker exec "$CID" sh -c '
     cur=$(cat /sys/fs/cgroup/memory.current 2>/dev/null)
     echo "Baseline memory.current = $((cur / 1048576)) MB"
   '
   ```
2. **Ukur Lonjakan Puncak Sesi (Saat Scan QR & Handshake):**
   Catat pembacaan kontinu selama proses penautan nomor:
   ```bash
   docker exec "$CID" sh -c '
     for i in $(seq 1 30); do
       cur=$(cat /sys/fs/cgroup/memory.current 2>/dev/null)
       echo "$(date +%T) - $((cur / 1048576)) MB"
       sleep 1
     done
   '
   ```
3. **Ukur Nilai Tunak / Idle (Setelah 30–60 Menit Tersambung):**
   Catat delta memori terhadap baseline:
   $$\text{Biaya Tunak per Sesi} = \text{memory.current} - 446\text{ MB}$$
4. **Ukur Beban Lonjakan Pesan:**
   Kirim rentetan pesan antrean (burst) untuk melihat lonjakan heap Node.js saat enkripsi dan socket dispatch.
5. **Penentuan Batas `WA_MAX_SESSIONS` Baru:**
   Setelah biaya stabil dan puncak Baileys TERUKUR secara konsisten selama 24–48 jam di produksi, barulah kapasitas dihitung ulang dan `WA_MAX_SESSIONS` dapat disesuaikan.

---

## 4. Pertimbangan Multi-Node untuk Skala Masa Depan

Dengan arsitektur Baileys saat ini, seluruh sesi berjalan di dalam satu container bersama Laravel dan queue worker. Jika di masa depan bisnis berkembang melampaui kapasitas satu server atau membutuhkan ketersediaan tinggi lintas server (*high availability*), berikut arsitektur yang perlu dipertimbangkan:

1. **Arsitektur Stateless Socket:**
   - Koneksi WebSocket WhatsApp terikat ke satu instance Node.js yang memegang soket TCP aktif.
   - Jika engine dipisah menjadi beberapa node worker (misal: Node A, Node B):
     - Rute HTTP Laravel ke engine harus menggunakan *sticky dispatching* (berdasarkan `session_id`), atau engine mengekspos REST API melalui load balancer internal dengan konsistensi hashing `session_id`.
2. **Penyimpanan Kredensial Tersentralisasi:**
   - Sistem `LaravelStore` saat ini sudah menyimpan bundel `session.json` di storage Laravel (`session-backups` disk).
   - Pada arsitektur multi-node, persistent storage disk bersama (seperti NFS atau S3-compatible Object Storage seperti MinIO/R2) memungkinkan node mana pun memulihkan sesi jika node sebelumnya mati (*failover*).
3. **Pemisahan Container Web dan Engine:**
   - Jika beban traffic pesan harian mencapai ratusan ribu, pisahkan kembali resource Coolify menjadi `pesanvo-web` dan `pesanvo-engine` dengan jaringan private Docker overlay / internal mesh.
   - Untuk skala saat ini, container tunggal terbukti efisien, hemat build resource, dan minim latensi IPC (127.0.0.1).

---

## 5. Pemantauan & Script Operasional

Gunakan skrip yang telah diperbarui untuk memantau server:

```bash
# Pantau status sesi websocket, memori container, dan zombie proses
./scripts/pantau.sh

# Ringkasan lantai memori jangka panjang (24-48 jam)
./scripts/ringkas-kapasitas.sh /var/log/pesanvo-capacity.log
```

- Pantau nilai `connected_sessions` di `scripts/pantau.sh`.
- Jika terjadi diskoneksi tak terduga, engine Baileys otomatis melakukan *exponential backoff reconnect* (2s, 3s, 4.5s... up to 15s) hingga 5 kali.
- Jika nomor di-*unlink* dari HP, engine mendeteksi kode 401 (`loggedOut`), menghentikan sesi secara bersih, dan memberitahu Laravel untuk memperbarui status menjadi `disconnected`.
