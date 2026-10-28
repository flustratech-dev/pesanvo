# Operasional

Menjalankan pesanvo di produksi: apa yang perlu dipantau, apa yang harus dilakukan saat bermasalah, dan bagaimana memulihkannya.

---

## 1. Pemeriksaan harian

Tiga hal, dua menit.

**Sesi masih terhubung?** Buka dashboard → Sesi WhatsApp. Semua harus **Terhubung**. Sesi yang `disconnected` berarti notifikasi sedang diam-diam gagal.

**Ada pesan gagal?** Dashboard → Ringkasan → kartu "Gagal bulan ini". Naik mendadak biasanya berarti satu sesi bermasalah, bukan nomor tujuan yang salah.

**Antrean tidak menumpuk?**

```bash
php artisan queue:failed
```

Kalau panjang, kemungkinan besar worker mati atau sesi putus berkepanjangan.

## 2. Yang layak dipantau otomatis

| Sinyal | Cara | Batas wajar |
|---|---|---|
| Engine hidup | `GET :3100/health` | balas 200 |
| Sesi terhubung | `GET /api/v1/health` dengan API key | `sessions.connected` = jumlah sesi |
| Pesan gagal | `usage.messages_failed` | < 5% dari terkirim |
| Antrean macet | `SELECT COUNT(*) FROM jobs` | < 100 |
| RAM engine | Coolify | < 80% |

Endpoint `/api/v1/health` sengaja dikecualikan dari rate limit supaya aman dipanggil sesering apa pun.

## 3. Kejadian umum

### Sesi tiba-tiba terputus

**Penyebab paling sering:** pemilik nomor membuka *Perangkat Tertaut* di HP dan mengeluarkan perangkat, atau HP-nya lama tidak online.

**Yang sudah terjadi otomatis:** `SyncSessionStatusJob` mendeteksinya dalam satu menit dan mencoba menyambungkan ulang. Kalau kredensialnya masih sah, sesi pulih sendiri tanpa campur tangan.

**Kalau tidak pulih:** kredensialnya sudah dicabut dari sisi WhatsApp. Harus scan QR lagi — Hubungkan, lalu scan.

### Nomor diblokir WhatsApp

**Gejala:** sesi terus `auth_failure`, atau nomor tidak bisa mengirim ke siapa pun.

Tidak ada cara memulihkan dari sisi sistem. Yang bisa dilakukan:

1. Tautkan nomor pengganti ke sesi yang sama (Putus tautan → Hubungkan → scan nomor baru). Riwayat pesan dan API key tetap utuh — aplikasi yang memakainya tidak perlu diubah sama sekali.
2. Cari tahu penyebabnya sebelum mengulang pola yang sama:
   - Mengirim ke orang yang tidak mengharapkan pesan → mereka menandai spam
   - Jeda kirim dimatikan atau dipendekkan berlebihan
   - Nomor baru langsung dipakai broadcast besar

**Pencegahan:** jangan turunkan `WA_MIN_DELAY_MS` di produksi, dan pastikan `WA_GATEWAY_REQUIRE_VERIFIED_PHONE=true` di aplikasi konsumen.

### Pesan menumpuk di `queued`

Urutan pemeriksaan:

1. Worker jalan? `docker exec <container-pesanvo> pgrep -af queue:work` — harus ada satu. Kosong berarti Start Command resource-nya bukan `bash ./start.sh`.
2. Sesi terhubung? Pesan untuk sesi yang putus memang sengaja ditahan, bukan dibuang.
3. Broadcast besar? Dengan jeda 3–8 detik, 500 pesan wajar memakan 30–60 menit. Ini bukan kemacetan.

### Engine kehabisan memori

**Gejala:** container restart berulang, sesi mentok `connecting`.

Satu sesi ≈ 300–500 MB. Pilihan:

- Turunkan `WA_MAX_SESSIONS` agar engine menolak sesi berlebih alih-alih kehabisan memori. Ini yang pertama dicoba, dan hampir selalu cukup — patokan angkanya di [DEPLOYMENT.md §2](DEPLOYMENT.md#2-berapa-sesi-yang-muat)
- Isi `WA_CHROME_HEAP_MB` (mis. `512`) untuk membatasi heap V8 di dalam Chromium. Hati-hati: terlalu rendah membuat sesi mati sendiri, dan gejalanya tidak menyebut memori sama sekali
- Tambah RAM VPS
- Pisahkan engine jadi resource Coolify sendiri lagi — pilihan terakhir, dan yang dibayar adalah satu build penuh tambahan tiap deploy

Sejak engine berbagi container dengan Laravel, kehabisan memori punya akibat baru yang harus disadari: yang dipilih OOM killer belum tentu Chromium. Kalau `dmesg -T | grep -i oom` menunjuk `mysqld` atau `php`, sumber masalahnya tetap `WA_MAX_SESSIONS`.

### Webhook workspace mati

Setelah 50 kegagalan berturut-turut, webhook dinonaktifkan otomatis supaya antrean tidak terus terisi kiriman yang pasti gagal. Workspace menyalakannya lagi dari dashboard setelah endpoint mereka pulih.

## 4. Pemulihan

### Volume engine hilang

Justru ini kasus yang sudah dirancang. Cukup:

1. Buat volume baru, mount ke `/data`
2. Restart engine

Engine akan menarik daftar sesi dari Laravel, mengunduh cadangan terakhir tiap sesi, dan menyambungkan kembali. **Tidak perlu scan QR**, selama cadangan terakhir masih berlaku (dibuat tiap 5 menit).

### Cadangan sesi ikut hilang

Sesi harus di-scan ulang. Data lain (riwayat, API key, workspace) tetap utuh di database.

### Database dipulihkan dari backup lama

Sesi yang dibuat setelah titik backup akan hilang dari database, tapi kredensialnya mungkin masih ada di volume engine. Yang terjadi: engine tetap menjalankan sesi itu, tapi callback-nya ditolak dengan `action: stop_session` — engine kemudian mematikannya sendiri. Aman, hanya perlu dibuat ulang.

### Engine tidak bisa dihubungi Laravel

Cek berurutan:

1. `ENGINE_URL` menunjuk ke UUID resource engine (bukan `localhost`, bukan pula nama tampilan resource)
2. Kedua container ada di jaringan Coolify yang sama
3. `curl http://<uuid-resource-engine>:3100/health` dari container Laravel
4. `ENGINE_TOKEN` sama di kedua sisi

## 5. Rutin berkala

| Kapan | Apa |
|---|---|
| Otomatis, tiap menit | Sinkronisasi status sesi + sambung ulang |
| Otomatis, 03:15 | Pemangkasan pesan & log webhook lama |
| Bulanan | Tinjau API key yang `last_used_at`-nya kosong — cabut yang tidak terpakai |
| Bulanan | Tinjau pemakaian workspace terhadap kuota |
| Per kuartal | Perbarui `whatsapp-web.js` — versi lama bisa berhenti bekerja saat WhatsApp Web berubah |

Memperbarui whatsapp-web.js:

```bash
npm --prefix engine update whatsapp-web.js
```

Uji di development lebih dulu, lalu staging, baru produksi. Versi baru bisa mengubah perilaku event.

## 6. Skala

Batas praktis satu engine:

| RAM | Sesi wajar |
|---|---|
| 1 GB | 1–2 |
| 2 GB | 3–4 |
| 4 GB | 8–10 |
| 8 GB | 15–20 |

Melewati itu, jalankan engine kedua. Saat ini pemetaan sesi ke engine belum ada di kode — semua sesi mengarah ke satu `ENGINE_URL`. Menyebarkannya butuh tabel pemetaan sesi→engine; dicatat sebagai batas di [ARSITEKTUR.md](ARSITEKTUR.md#11-yang-sengaja-belum-dibuat).

Sisi Laravel jauh lebih ringan dan bisa di-scale mendatar seperti aplikasi web biasa — dengan syarat disk `session-backups` dipindahkan ke S3/MinIO agar semua instance membaca cadangan yang sama.

## 7. Keamanan

**Rotasi berkala:**

- `ENGINE_TOKEN` dan `ENGINE_HMAC_SECRET` — ubah di kedua sisi lalu restart keduanya bersamaan
- API key — buat yang baru, perbarui aplikasi konsumen, cabut yang lama
- `APP_KEY` — **jangan** diubah; ia mengenkripsi data sesi yang sudah tersimpan

**Yang tidak boleh terjadi:**

- `APP_DEBUG=true` di produksi — halaman error akan menampilkan seluruh isi environment, termasuk password database dan secret HMAC
- Engine punya domain publik
- API key dengan scope `otp` diberikan ke aplikasi selain app-auth
- Secret yang sama dipakai di dev dan produksi

**Jejak audit** ada di tabel `audit_logs` — pembuatan/pencabutan API key, perubahan sesi, perubahan workspace.
