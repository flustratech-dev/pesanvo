# Panduan Developer

Untuk siapa pun yang akan menulis kode di project ini. Baca [PENGANTAR.md](PENGANTAR.md) dan [ARSITEKTUR.md](ARSITEKTUR.md) dulu.

---

## 1. Menyiapkan mesin

### Yang dibutuhkan

| | Versi | Catatan |
|---|---|---|
| PHP | 8.2+ | XAMPP di Windows sudah cukup |
| Composer | 2.x | |
| Node.js | 20+ | Engine memakai `--env-file`, tersedia sejak Node 20 |
| MySQL | 8.x | Opsional untuk lokal — SQLite lebih praktis |

Chromium tidak perlu dipasang sendiri; `whatsapp-web.js` mengunduhnya lewat Puppeteer saat `npm install`.

### Langkah

```bash
git clone https://github.com/your-org/pesanvo.git
cd pesanvo

composer install
npm install
npm --prefix engine install

# Buat .env di root dan di engine/ — daftar variabelnya di docs/ENVIRONMENT.md

php artisan key:generate
php artisan migrate
```

Buat sepasang nilai acak, lalu isikan **nilai yang sama persis** ke `.env` dan `engine/.env`:

```bash
openssl rand -hex 32   # untuk ENGINE_TOKEN
openssl rand -hex 32   # untuk ENGINE_HMAC_SECRET
```

Kalau tidak punya `openssl`, string acak 40+ karakter apa pun bisa dipakai.

> Keduanya **harus berbeda satu sama lain**. Kalau token bocor lewat log, ia tidak boleh sekaligus memberi kemampuan memalsukan callback engine.

### Menjalankan

```bash
npm run all
```

Satu perintah, empat proses: Laravel (`:8051`), queue worker, Vite, dan engine (`:3100`).

Buka http://localhost:8051/register untuk membuat akun pertama.

> Laravel Pail sengaja tidak ikut: ia butuh ekstensi `pcntl` yang tidak ada di PHP Windows, dan karena `--kill-others`, matinya Pail akan menjatuhkan ketiga proses lain. Di Linux/WSL jalankan `php artisan pail` di terminal terpisah.

### Menjalankan terpisah

```bash
php artisan serve --port=8051
php artisan queue:listen
npm run dev
npm --prefix engine run dev
```

---

## 2. Mencoba dari nol sampai terkirim

```bash
# Daftar akun di http://localhost:8051, isi nama workspace,
# lalu buat sesi & API key lewat dashboard
```

Keluarannya memuat ULID sesi dan API key penuh. Kunci juga bisa dibuka lagi kapan saja dari halaman API Keys.

```bash
KEY="vwa_xxxxxxxx.xxxxxxxx"
SID="01k..."

# Jalankan sesi
curl -X POST -H "X-Api-Key: $KEY" http://127.0.0.1:8051/api/v1/sessions/$SID/connect

# Ambil QR (data URI PNG). Butuh beberapa detik sampai Chromium siap.
curl -H "X-Api-Key: $KEY" http://127.0.0.1:8051/api/v1/sessions/$SID/qr
```

Lebih mudah lewat dashboard: **Sesi WhatsApp → Hubungkan**, QR muncul di modal.

Setelah tersambung:

```bash
curl -X POST http://127.0.0.1:8051/api/v1/messages/text \
  -H "X-Api-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Halo dari lokal"}'
```

---

## 3. Struktur kode

### Laravel

```
app/
├─ Console/Commands/
│  └─ SetupWorkspaceCommand.php        Penyiapan workspace/sesi/API key dari CLI
├─ Http/
│  ├─ Controllers/
│  │  ├─ Api/                       REST API publik v1
│  │  ├─ Auth/AuthController.php    Login & register lokal
│  │  ├─ Dashboard/                 Halaman dashboard
│  │  └─ Internal/                  Callback engine — bukan API publik
│  └─ Middleware/
│     ├─ AuthenticateApiKey.php     Verifikasi X-Api-Key + scope
│     ├─ EnsureWorkspaceSelected.php   Menentukan workspace aktif dashboard
│     └─ VerifyEngineSignature.php  HMAC + timestamp untuk /internal/*
├─ Jobs/
│  ├─ SendMessageJob.php            Pengiriman + percobaan ulang
│  ├─ DeliverWebhookJob.php         Kiriman webhook bertanda tangan
│  ├─ SyncSessionStatusJob.php      Jaring pengaman status sesi
│  └─ PruneOldRecordsJob.php        Retensi data
├─ Models/                          Eloquent
├─ Services/
│  ├─ MessageDispatcher.php         Satu-satunya pintu pembuatan pesan keluar
│  ├─ SessionService.php            Siklus hidup sesi
│  ├─ WebhookDispatcher.php         Penyiaran event
│  ├─ OtpService.php                OTP WhatsApp
│  └─ Providers/                    Lapisan multi-driver
└─ Support/PhoneNumber.php          Normalisasi nomor
```

### Engine

```
engine/src/
├─ server.js            Express, penjaga token, endpoint
├─ session-manager.js   Map sesi, siklus hidup Client, pemetaan event
├─ queue.js             Antrean per sesi + jeda anti-ban
├─ laravel.js           Klien HTTP bertanda tangan ke Laravel
├─ stores/
│  └─ laravel-store.js  Store RemoteAuth (cadangan sesi)
├─ config.js            Pembacaan env + validasi saat boot
└─ logger.js            Pino + penyamaran nomor telepon
```

---

## 4. Aturan yang berlaku di seluruh kode

Empat hal ini berulang. Melanggarnya menimbulkan bug yang sulit dilacak.

### Selalu berangkat dari workspace

```php
// Benar — hanya menemukan milik workspace ini
$session = EnsureWorkspaceSelected::from($request)->sessions()->findOrFail($id);

// Salah — menemukan sesi milik siapa pun
$session = WaSession::find($id);
```

### Semua pesan keluar lewat MessageDispatcher

Jangan pernah `Message::create()` langsung untuk pesan keluar. `MessageDispatcher::queue()` yang mengurus normalisasi nomor, pemeriksaan kuota, pencatatan pemakaian, dan pengantrean — melewatinya berarti melewatkan semuanya.

### Nomor selalu dinormalisasi

`PhoneNumber::normalize()` mengubah `0812…`, `+62 812-…`, dan `62812…` menjadi satu bentuk `62812…`. Aturannya sengaja sama persis dengan `WhatsAppLink` di app-web supaya nomor yang sudah tersimpan di aplikasi lain tidak berubah arti.

### Nomor tidak boleh utuh di log

Log dikirim ke agregator dan disimpan lama, sementara isinya data pribadi pelanggan. Pakai `PhoneNumber::mask()` di PHP; di engine, Pino sudah menyamarkannya otomatis.

---

## 5. Menambah fitur

### Endpoint API baru

1. Method di controller `app/Http/Controllers/Api/` yang mewarisi `ApiController`
2. Rute di `routes/api.php` dalam grup `v1` + `apikey`
3. Balas dengan `$this->ok()` atau `$this->fail()` agar bentuknya konsisten
4. Tes di `tests/Feature/`
5. Dokumentasikan di [API.md](API.md)

### Event engine baru

1. Di `engine/src/session-manager.js`, tambahkan listener dan panggil `laravel.event(sessionId, 'nama_event', payload)`
2. Di `EngineEventController`, tambahkan cabang `match` dan penanganannya
3. Tes dengan menandatangani payload — lihat `EngineCallbackTest::signed()`

### Driver provider baru

1. Implementasi `WhatsAppProvider` di `app/Services/Providers/`
2. Daftarkan di `ProviderManager::driver()`
3. Tambahkan nilainya ke enum `driver` di migrasi `wa_sessions`
4. Lempar `ProviderException::permanent()` untuk kegagalan yang tidak akan membaik

### Kolom database baru

Selalu migrasi baru, jangan mengubah migrasi lama — kecuali sebelum rilis pertama. Nyalakan `--force` di produksi lewat post-deployment command Coolify.

---

## 6. Tes

```bash
php artisan test
php artisan test --filter=SendMessageTest
```

Tes memakai SQLite di memori. Env pengujian ada di `phpunit.xml`, termasuk `ENGINE_URL=http://engine.test` supaya panggilan ke engine bisa dipalsukan dengan `Http::fake()`.

### Yang sudah ada

| Berkas | Cakupan |
|---|---|
| `AuthenticationTest` | Login, register, landing, isolasi sesi pengguna |
| `ApiAuthenticationTest` | API key, scope, isolasi workspace |
| `SendMessageTest` | Antre, normalisasi, kuota, broadcast, percobaan ulang |
| `EngineCallbackTest` | HMAC, penolakan pemutaran ulang, event, urutan ack |

### Menulis tes

Nama method dalam Bahasa Indonesia, mendeskripsikan perilaku yang dijaga:

```php
public function test_kuota_habis_menolak_pesan_berikutnya(): void
```

Untuk callback engine, tanda tangannya harus dihitung atas body mentah — `json_encode` ulang oleh helper test bisa menghasilkan urutan atau escaping berbeda dan membuat tanda tangan meleset. Pakai pola `postEvent()` di `EngineCallbackTest`.

Sertakan komentar yang menjelaskan **kenapa** perilaku itu penting, bukan sekadar apa yang diuji:

```php
/**
 * Ack dari WhatsApp bisa datang tidak berurutan. Tanpa penjagaan, pesan
 * yang sudah "read" bisa turun lagi jadi "delivered".
 */
```

---

## 7. Gaya kode

**PHP** — mengikuti Laravel Pint:

```bash
./vendor/bin/pint
```

**JavaScript** — ESM, `const` sebagai default, `async/await` daripada rantai `.then()`.

**Komentar** menjelaskan alasan, bukan mekanisme. Kode sudah menunjukkan *apa* yang terjadi; komentar berguna saat menjelaskan *kenapa begitu* atau *apa akibatnya kalau tidak*.

**Semua teks dalam Bahasa Indonesia** — komentar, pesan galat, nama tes, dokumentasi, UI. Istilah teknis yang tidak punya padanan mapan (queue, webhook, endpoint, timestamp) boleh tetap bahasa Inggris.

---

## 8. Pemecahan masalah saat pengembangan

| Gejala | Penyebab |
|---|---|
| Semua callback engine 401 | `ENGINE_HMAC_SECRET` beda antara `.env` dan `engine/.env` |
| `Token engine tidak valid` | `ENGINE_TOKEN` beda antara keduanya |
| Sesi mentok `connecting` | Chromium gagal jalan — cek log engine; RAM atau library sistem |
| Modal QR kosong padahal status `qr` | `QR_TTL_SECONDS` terlalu pendek (jangan di bawah 60) |
| Pesan mentok `queued` | Worker antrean tidak jalan |
| `npm run all` mati semua | Satu proses gagal; `--kill-others` menjatuhkan sisanya. Cek log paling atas |
| Perubahan config tidak terbaca | `php artisan config:clear` |

### Melihat lebih dalam

```bash
tail -f storage/logs/laravel.log
php artisan queue:failed
php artisan tinker
curl http://127.0.0.1:3100/health   # tanpa token, boleh
```

Engine memakai log terstruktur (JSON). Untuk membacanya lebih nyaman:

```bash
npm --prefix engine run dev | npx pino-pretty
```

---

## 9. Alur git

| Branch | Tahap | Domain |
|---|---|---|
| `dev` | Development | `wa-dev.pesanvo.id` |
| `staging` | Staging | `wa-staging.pesanvo.id` |
| `main` | Production | `wa.pesanvo.id` |

Kerjakan di `dev`, promosikan ke `staging` setelah teruji, lalu ke `main`. Coolify men-deploy otomatis setiap push. Daftar periksa promosi ada di [ENVIRONMENT.md](ENVIRONMENT.md).

**Jangan pernah commit** `.env`, `engine/.env`, isi `.wwebjs_auth/`, atau file zip sesi. Semuanya sudah masuk `.gitignore` — kredensial WhatsApp yang bocor sama saja memberi orang lain akses penuh ke nomor tersebut.
