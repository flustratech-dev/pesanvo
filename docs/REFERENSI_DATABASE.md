# Referensi Database

Setiap tabel, setiap kolom, dan alasan keberadaannya. Sumber kebenarannya tetap file migrasi di `database/migrations/` — dokumen ini menjelaskan *kenapa*, yang tidak bisa dibaca dari skema.

---

## Peta relasi

```
users ──┬──< workspace_members >──┬── workspaces ──< wa_sessions ──< session_backups
        │                       │                    │
        │                       │                    └──< messages
        │                       ├──< api_keys
        │                       ├──< message_templates
        │                       ├──< webhooks ──< webhook_deliveries
        │                       ├──< usage_counters
        │                       └──< audit_logs
        │
        └── workspaces.owner_id (nullOnDelete)

otp_codes  ── berdiri sendiri, dikunci oleh nomor telepon
```

Semua tabel milik workspace memakai `onDelete: cascade`. Menghapus workspace membuang seluruh datanya — memang itu yang diharapkan saat pelanggan berhenti.

Pengecualian: `workspaces.owner_id` memakai `nullOnDelete`. Menghapus akun pengguna tidak boleh ikut menghapus workspace beserta riwayat pesannya; kepemilikan cukup dipindahkan.

---

## `users`

Akun yang bisa masuk ke dashboard. pesanvo punya form login dan register sendiri, dan akunnya **tertaut** dengan aplikasi pesanvo: email dan kata sandi yang sama berlaku di keduanya ([AKUN_TERTAUT.md](AKUN_TERTAUT.md)). Aplikasi Pesanvo yang memakai gateway ini tidak ikut tertaut — mereka memanggil API dengan API key workspace, bukan akun.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Ditampilkan di dashboard |
| `email` | string unique | Selalu disimpan huruf kecil, supaya `Budi@…` dan `budi@…` tidak jadi dua akun |
| `email_verified_at` | timestamp null | Disiapkan; verifikasi email belum diwajibkan |
| `password` | string | Di-hash otomatis lewat cast `hashed` |
| `is_super_admin` | bool | Akses lintas workspace untuk keperluan dukungan. **Tidak bisa diatur lewat antarmuka mana pun** — hanya langsung di database |
| `last_login_at` | timestamp null | Untuk melihat akun yang sudah lama tidak dipakai |
| `remember_token` | string null | |
| `linked_sync_pending_at` | timestamp null | Ada perubahan email/kata sandi yang belum sampai ke aplikasi pesanvo. Diulang `akun-tertaut:kirim` tiap menit |
| `linked_sync_previous_email` | string null | Email lama yang harus dicari di pesanvo saat email berganti dan pengirimannya belum berhasil — tanpa ini percobaan ulang membuat akun kedua di sana |

Satu pengguna bisa menjadi anggota banyak workspace lewat `workspace_members`.

## `workspaces`

Batas isolasi antar pelanggan. Semua data lain menggantung di sini.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Nama tampilan workspace |
| `slug` | string unique | Dibuat dari nama, diberi akhiran angka bila bentrok |
| `owner_id` | FK users null | Pemilik. `nullOnDelete` — lihat catatan di atas |
| `owner_email` | string null | Salinan email saat dibuat, berguna saat akun pemilik sudah tidak ada |
| `status` | enum | `active`, `suspended`. Workspace `suspended` ditolak API dengan 403 |
| `plan_slug` | string null | Cerminan dari app-pricing. **Bukan** sumber kebenaran |
| `max_sessions` | int | Batas jumlah nomor |
| `monthly_message_quota` | int | Batas pesan keluar per bulan. `0` berarti tanpa batas |
| `api_rate_limit_per_minute` | int | Bawaan untuk API key milik workspace ini |
| `is_internal` | bool | Workspace internal Pesanvo — dikecualikan dari kuota |
| `deleted_at` | timestamp null | Soft delete |

**Kenapa soft delete.** Menghapus workspace membuang riwayat pesan yang mungkin masih dibutuhkan untuk audit atau sengketa tagihan. Soft delete memberi jeda sebelum penghapusan permanen.

**Kenapa `plan_slug` bukan sumber kebenaran.** Langganan dikelola app-pricing. Menyimpan salinannya di sini hanya agar dashboard bisa menampilkannya tanpa memanggil layanan lain di setiap permintaan.

## `workspace_members`

Tabel pivot keanggotaan.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `workspace_id` | FK workspaces cascade | |
| `user_id` | FK users cascade | |
| `role` | enum | `owner`, `admin`, `member` |

Unik pada `(workspace_id, user_id)` — satu pengguna tidak bisa punya dua peran di satu workspace.

| Peran | Boleh |
|---|---|
| `owner` | Semuanya. Tidak bisa dikeluarkan dari workspace-nya sendiri |
| `admin` | Kelola sesi, API key, webhook, anggota |
| `member` | Lihat dan kirim pesan |

## `api_keys`

Kredensial untuk REST API.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `workspace_id` | FK cascade | |
| `name` | string | Label, mis. "app-erp produksi" |
| `prefix` | string(12) unique | Bagian depan kunci, mis. `vwa_a1b2c3d4` |
| `key_hash` | string | Hash dari bagian rahasia |
| `scopes` | json null | `["*"]` atau daftar seperti `["otp"]` |
| `rate_limit_per_minute` | int null | Menimpa batas workspace bila diisi |
| `last_used_at` | timestamp null | Ditulis paling sering sekali per menit |
| `last_used_ip` | string(45) null | Muat alamat IPv6 |
| `expires_at` | timestamp null | Opsional |
| `revoked_at` | timestamp null | Dicabut, bukan dihapus |
| `created_by` | FK users nullOnDelete | |

**Kenapa prefix dipisah dari hash.** Hash tidak bisa dicari. Tanpa prefix, setiap permintaan API harus mengambil seluruh isi tabel dan membandingkan hash satu per satu — biaya yang tumbuh seiring jumlah pelanggan. Prefix mempersempit ke satu baris, lalu hash-nya diverifikasi sekali.

**Kenapa dicabut, bukan dihapus.** Baris yang tetap ada menyimpan jejak "kunci ini dipakai sampai tanggal sekian" — informasi yang dibutuhkan saat menyelidiki penyalahgunaan.

**Kenapa scope terpisah.** Scope `otp` memberi kemampuan mengirim kode verifikasi ke nomor mana pun. Kunci yang bocor dengan scope itu bisa dipakai membombardir orang lain dan membuat nomor pengirim diblokir. Kunci integrasi biasa tidak boleh memilikinya.

Indeks: `(workspace_id, revoked_at)` untuk daftar kunci aktif di dashboard.

## `wa_sessions`

Satu nomor WhatsApp yang tertaut.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | **ULID** PK | Ikut dipakai sebagai nama folder & file json cadangan di engine |
| `workspace_id` | FK cascade | |
| `name` | string | Label, mis. "CS Utama" |
| `driver` | enum | selalu `wwebjs` |
| `status` | enum | `pending`, `connecting`, `qr`, `connected`, `disconnected`, `failed` |
| `phone_number` | string(20) null | Terisi setelah tersambung, format `62812…` |
| `push_name` | string null | Nama profil WhatsApp |
| `qr_payload` | text null | Data URI PNG, dirender engine |
| `qr_expires_at` | timestamp null | Lihat catatan di bawah |
| `auto_reconnect` | bool | Ikut dijalankan saat engine boot |
| `last_seen_at` | timestamp null | Pemeriksaan status terakhir |
| `connected_at` | timestamp null | |
| `last_error` | text null | Ditampilkan di kartu sesi |
| `meta` | json null | Ruang untuk kebutuhan khusus driver |
| `deleted_at` | timestamp null | Soft delete |

**Kenapa ULID, bukan auto-increment.** Id sesi menjadi nama folder di engine dan nama file cadangan. Nilai itu harus aman dipakai sebagai nama file dan tidak boleh bisa ditebak dari luar.

**Kenapa `qr_expires_at` penting.** whatsapp-web.js menerbitkan QR baru dengan jeda tidak tetap — pengamatan menunjukkan 20 sampai 60 detik. Kalau masa berlakunya lebih pendek dari jeda terlama itu, akan ada celah di mana QR dianggap kedaluwarsa padahal penggantinya belum datang, dan modal di dashboard mendadak kosong tepat saat pengguna bersiap men-scan. `QR_TTL_SECONDS` bawaannya 90 detik; jangan diturunkan di bawah 60.

**Kenapa `kind` dihapus.** Kolom itu dulu memisahkan nomor Pesanvo dari nomor pelanggan, tapi pemisahannya tidak pernah tampil di antarmuka dan justru membuat sesi hijau ditolak saat `session_id` dikosongkan. Pemisahan nomor sekarang dilakukan dengan workspace terpisah, yang memang terlihat.

Unik pada `(workspace_id, name)`. Indeks pada `(workspace_id, status)`.

## `session_backups`

Cadangan kredensial sesi — inti dari ketahanan terhadap deploy ulang.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `wa_session_id` | FK ULID cascade | |
| `disk` | string | Nama disk Laravel, bawaan `session-backups` |
| `path` | string | `{session_id}/session.json` |
| `size` | bigint | Byte |
| `checksum` | string(64) | sha256, dicocokkan saat unggah |
| `backed_up_at` | timestamp | |

**Satu baris per sesi, ditimpa terus.** Yang dibutuhkan hanya cadangan terbaru. Menyimpan riwayat file lama memenuhi disk tanpa guna — cadangan sesi yang lama tidak bisa dipakai karena WhatsApp sudah memutar kredensialnya.

## `messages`

Riwayat pesan masuk dan keluar.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | **ULID** PK | Dikembalikan ke pemanggil API sebagai id pelacakan |
| `workspace_id` | FK cascade | |
| `wa_session_id` | FK ULID nullOnDelete | Null bila sesinya sudah dihapus |
| `direction` | enum | `outbound`, `inbound` |
| `wa_message_id` | string null idx | Id dari WhatsApp, untuk mencocokkan ack |
| `chat_id` | string null | `62812…@c.us` atau `…@g.us` untuk grup |
| `to_number` | string(32) null | Sudah ternormalisasi |
| `from_number` | string(32) null | |
| `type` | enum | `text`, `image`, `document`, `video`, `audio`, `location`, `other` |
| `body` | text null | Isi pesan atau caption media |
| `media_path` | string null | Di disk `media` |
| `media_mime` | string null | |
| `media_filename` | string null | |
| `status` | enum | `queued`, `sending`, `sent`, `delivered`, `read`, `failed` |
| `error` | text null | |
| `attempts` | tinyint | |
| `provider_response` | json null | Balasan mentah, berguna saat menyelidiki kegagalan |
| `batch_id` | ULID null idx | Menandai pesan dari satu broadcast |
| `sent_at`, `delivered_at`, `read_at` | timestamp null | |

**Kenapa ULID.** Id ini dikembalikan ke pemanggil API. Auto-increment akan membocorkan berapa banyak pesan yang lewat sistem, dan memungkinkan menebak id milik workspace lain.

**Status hanya boleh maju.** Ack dari WhatsApp bisa datang tidak berurutan; tanpa penjagaan, pesan yang sudah `read` bisa turun lagi jadi `delivered`. Logikanya di `Message::advanceStatus()`, dengan peringkat `queued(0) → sending(1) → sent(2) → delivered(3) → read(4)`. `failed` di luar peringkat dan selalu diterima.

Indeks: `(workspace_id, created_at)` untuk riwayat, `(workspace_id, status)` untuk filter, `(wa_session_id, direction)` untuk statistik per sesi.

## `message_templates`

Pesan siap pakai dengan placeholder.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `workspace_id` | FK cascade | |
| `name` | string | Nama tampilan |
| `slug` | string | Dipakai di API: `"template": "pengingat-invoice"` |
| `body` | text | Boleh mengandung `{{ nama }}` |
| `variables` | json null | Daftar placeholder yang dipakai body |
| `is_active` | bool | |

Unik pada `(workspace_id, slug)`.

**Kenapa `variables` disimpan padahal bisa diturunkan dari body.** Supaya antarmuka bisa memvalidasi variabel yang dikirim tanpa mem-parsing ulang setiap kali. Kolom ini selalu ditulis ulang dari body saat menyimpan (`MessageTemplate::extractVariables()`), jadi tidak mungkin melenceng.

**Placeholder tanpa pasangan dibiarkan apa adanya.** Kalau `{{ nama }}` tidak diberi nilai, ia tetap muncul sebagai `{{ nama }}` di pesan. Sengaja — kesalahan yang terlihat jelas lebih baik daripada kalimat yang diam-diam bolong.

> Template di sini bebas format — tidak ada proses persetujuan seperti pada WhatsApp Business API resmi, yang memang bukan jalur yang dipakai produk ini.

## `webhooks`

Endpoint workspace yang menerima kejadian.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `workspace_id` | FK cascade | |
| `url` | string | |
| `secret` | string(64) | Untuk menandatangani payload |
| `events` | json null | `null` berarti berlangganan semua |
| `is_active` | bool | |
| `last_success_at` | timestamp null | |
| `last_failure_at` | timestamp null | |
| `consecutive_failures` | int | Dinolkan setiap berhasil |

**Penonaktifan otomatis.** Setelah 50 kegagalan berturut-turut, `is_active` dimatikan. Tanpa ini, endpoint workspace yang mati berhari-hari akan terus mengisi antrean dengan kiriman yang pasti gagal, memperlambat semua workspace lain.

## `webhook_deliveries`

Catatan tiap percobaan kiriman, untuk penelusuran.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `webhook_id` | FK cascade | |
| `event` | string | |
| `payload` | json | |
| `response_code` | smallint null | Null berarti tidak bisa terhubung sama sekali |
| `response_body` | text null | Dipotong 2000 karakter |
| `attempts` | tinyint | |
| `delivered_at` | timestamp null | Hanya terisi bila 2xx |

Dipangkas oleh `RETENTION_WEBHOOK_DAYS`.

## `usage_counters`

Agregat pemakaian bulanan per workspace.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `workspace_id` | FK cascade | |
| `period` | string(7) | `YYYY-MM` |
| `messages_sent` | int | Dinaikkan **saat diantre**, bukan saat terkirim |
| `messages_received` | int | |
| `messages_failed` | int | |

Unik pada `(workspace_id, period)`.

**Kenapa tabel terpisah, tidak menghitung dari `messages`.** Dua alasan. Menghitung `COUNT(*)` pada tabel jutaan baris di setiap pemeriksaan kuota terlalu lambat. Dan `messages` dipangkas oleh retensi — angka pemakaian akan ikut hilang bersamanya.

**Kenapa dihitung saat diantre.** Kalau dihitung setelah terkirim, satu workspace bisa mengantrekan puluhan ribu pesan lebih dulu dan baru ketahuan melewati batas setelah semuanya terlanjur terkirim.

**Kenapa memakai upsert + increment mentah.** Beberapa worker bisa memproses pesan workspace yang sama secara bersamaan. Membaca lalu menulis (`$counter->messages_sent + 1`) akan kehilangan hitungan; `increment()` di level database aman dari race.

## `otp_codes`

Kode verifikasi nomor. Berdiri sendiri, tidak terikat ke workspace maupun user — dikunci oleh nomor telepon.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `phone` | string(20) idx | Ternormalisasi |
| `purpose` | string(40) | Bawaan `phone_verification` |
| `code_hash` | string | Kode di-hash, tidak pernah disimpan apa adanya |
| `attempts` | tinyint | |
| `expires_at` | timestamp | |
| `verified_at` | timestamp null | |
| `requested_by_ip` | string(45) null | |
| `workspace_id` | FK nullOnDelete | Siapa yang meminta |

**Kenapa `purpose` ada.** Supaya kode yang dibuat untuk satu tujuan tidak bisa dipakai di tujuan lain. Tanpa itu, kode verifikasi nomor bisa dipakai untuk, misalnya, mengonfirmasi transaksi.

**Kode lama dimatikan saat kode baru dibuat.** Hanya kode terbaru yang berlaku — mencegah kode dari pesan lama masih bisa dipakai.

Dipangkas oleh `PruneOldRecordsJob`: OTP tidak punya nilai setelah kedaluwarsa, dan menyimpannya lama hanya menambah data nomor telepon yang tidak perlu ada.

## `audit_logs`

Jejak tindakan penting.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `workspace_id` | FK nullOnDelete | |
| `user_id` | FK nullOnDelete | |
| `action` | string | mis. `api_key.revoked` |
| `subject_type` | string null | Kelas model |
| `subject_id` | string null | String, bukan int — muat ULID |
| `context` | json null | |
| `ip_address` | string(45) null | |

Tindakan yang dicatat: `workspace.created`, `workspace.member_added`, `session.created`, `session.connect`, `session.disconnect`, `session.logout`, `session.deleted`, `api_key.created`, `api_key.revoked`, `webhook.created`, `webhook.deleted`.

## Tabel bawaan Laravel

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`, `migrations`.

`jobs` dan `sessions` dipakai karena `QUEUE_CONNECTION=database` dan `SESSION_DRIVER=database`. Redis tersedia di infrastruktur tapi belum dipakai — volume pekerjaannya belum sepadan dengan tambahan komponen yang harus dijaga.

---

## Catatan tentang migrasi

Semua tabel inti dibuat dalam lima migrasi bertanggal `2026_08_13_1000xx`, bukan satu migrasi per tabel. Ini disengaja: tabel-tabel itu lahir bersamaan sebagai satu rancangan, dan memecahnya jadi belasan file hanya menyulitkan pembacaan.

Setelah rilis pertama, aturan biasa berlaku: **setiap perubahan skema adalah migrasi baru**, jangan pernah mengubah migrasi yang sudah pernah dijalankan di produksi.
