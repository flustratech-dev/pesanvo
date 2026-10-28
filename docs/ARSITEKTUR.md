# Arsitektur

Dokumen ini menjelaskan bagaimana bagian-bagian pesanvo bekerja sama, dan **kenapa** dirancang begitu. Baca [PENGANTAR.md](PENGANTAR.md) dulu kalau belum.

---

## 1. Gambaran besar

```
  app-erp   app-web   app-pricing   app-helpdesk   pelanggan
        │             │              │                 │               │
        └─────────────┴──────────────┴─────────────────┴───────────────┘
                                     │
                      X-Api-Key  ▼  HTTPS
   ╔═════════════════════════════════════════════════════════════════════╗
   ║  SATU container Coolify — start.sh                                  ║
   ║                                                                     ║
   ║  ┌────────────────────────────────────────────────┐                 ║
   ║  │  web (Laravel 12, php artisan serve)           │                 ║
   ║  │                                                │                 ║
   ║  │  • dashboard      • REST API v1                │                 ║
   ║  │  • workspace & user  • antrean pesan           │                 ║
   ║  │  • API key        • riwayat & status           │                 ║
   ║  │  • webhook        • penyimpan cadangan sesi    │                 ║
   ║  └────────────────────────────────────────────────┘                 ║
   ║        │ ▲                       │              │                   ║
   ║   127.0│.0.1:3100                │ jobs         │ tiap menit        ║
   ║   token│ │ callback HMAC         ▼              ▼                   ║
   ║        ▼ │ (qr, ready, ack)  ┌──────────────┐ ┌──────────────┐      ║
   ║  ┌───────────────────────┐   │ queue:work   │ │ schedule:work│      ║
   ║  │ engine (Node)         │   └──────────────┘ └──────────────┘      ║
   ║  │ whatsapp-web.js       │                                          ║
   ║  │ + Chromium per sesi   │   volume: /data/.wwebjs_auth             ║
   ║  └───────────────────────┘   volume: /app/storage/app/private       ║
   ╚═════════│═══════════════════════════════════════════════════════════╝
             ▼
         WhatsApp Web
```

Empat proses, satu repo, **satu container**:

| Proses | Bahasa | Tugas |
|---|---|---|
| web | PHP | Menerima permintaan, menyimpan keadaan, menyajikan dashboard |
| engine | Node.js | Berbicara dengan WhatsApp |
| worker | PHP | Menjalankan pengiriman & webhook di latar belakang |
| penjadwal | PHP | Sinkronisasi status sesi tiap menit, pembersihan data lama |

Pembagian tugas di bawah ini tetap berlaku apa adanya — yang berubah 17 Agustus 2026 hanya **di mana** keempatnya berjalan, bukan siapa mengerjakan apa. Engine tetap proses terpisah dengan batas yang sama tegasnya: ia tetap dijaga token, tetap tidak punya database, tetap bisa dibunuh dan dibangun ulang kapan saja.

---

## 2. Kenapa dipisah jadi dua bahasa

Pertanyaan yang wajar: kenapa tidak semuanya Node, atau semuanya PHP?

**Kenapa engine harus Node.** `whatsapp-web.js` adalah library Node. Tidak ada padanan PHP yang setara — semua gateway WhatsApp non-resmi bertumpu pada library Node yang sama. Ini bukan pilihan, ini kenyataan.

**Kenapa sisanya PHP.** Seluruh ekosistem Pesanvo adalah Laravel. Dashboard, workspace, billing, SSO, antrean — semua polanya sudah ada dan sudah terbukti. Menulis ulang semuanya dalam Node berarti membangun kembali dari nol hal-hal yang sudah selesai.

**Kenapa engine jadi proses terpisah, bukan library di dalam PHP.** Karena bahasanya memang beda, dan batas prosesnya yang membuat engine bisa dibunuh, di-restart, dan dibangun ulang tanpa menyentuh keadaan apa pun.

**Kenapa proses, bukan container sendiri.** Ini keputusan yang dibalik pada **17 Agustus 2026**. Sampai tanggal itu engine punya resource Coolify sendiri, dengan tiga alasan yang ditulis di sini. Ketiganya masih ditulis apa adanya di bawah, beserta apa yang terjadi pada masing-masing — supaya keputusan ini tidak dibongkar ulang tiap sesi baru, dan supaya yang membongkarnya tahu persis apa yang ia bayar.

| Alasan lama | Keadaannya sekarang |
|---|---|
| "Chromium sesekali crash dan menjatuhkan aplikasi juga." | **Terjawab.** Engine berjalan di dalam loop pengawas di `start.sh`; matinya engine menjalankan engine lagi dalam 3 detik tanpa menyentuh proses web. Dijaga Uji 4 di [DEPLOYMENT.md](DEPLOYMENT.md#4-uji-regresi-wajib). |
| "Container Laravel harus memasang belasan library sistem hanya untuk Chromium." | **Masih benar, dan memang dibayar.** Bedanya, biaya itu dibayar sekali per build image dan di-cache Nixpacks — sementara tiga resource membayar `composer install` + `npm ci` **tiga kali pada setiap deploy**. |
| "Skala keduanya berbeda." | **Benar secara prinsip, tidak berlaku di sini.** Satu VPS 2 vCPU, satu mesin; tidak ada yang bisa diskalakan sendiri-sendiri. Alasan ini baru hidup lagi kalau gateway pindah ke mesin yang bisa menambah node. |

Satu hal yang **tidak** terjawab dan harus disadari: di satu container, Chromium berebut RAM dengan PHP, dan yang dipilih OOM killer belum tentu Chromium. Penjagaannya bukan arsitektur melainkan angka — `WA_MAX_SESSIONS` yang disetel jujur terhadap RAM yang ada. Cara menghitungnya di [DEPLOYMENT.md §2](DEPLOYMENT.md#2-berapa-sesi-yang-muat).

Kalau nanti gateway benar-benar perlu dipisah lagi, yang dipisah **engine**-nya saja. Worker dan penjadwal tidak pernah layak jadi resource sendiri: keduanya hampir tanpa biaya jalan, tapi masing-masing membawa satu build penuh.

---

## 3. Pembagian tanggung jawab

Aturan pembagiannya satu kalimat: **Laravel memegang keadaan, engine memegang koneksi.**

| | Laravel | Engine |
|---|---|---|
| Daftar sesi | ✅ sumber kebenaran | ❌ menanyakan saat boot |
| Kredensial WhatsApp | ✅ menyimpan cadangan | ✅ memegang yang aktif |
| Riwayat pesan | ✅ | ❌ |
| Workspace & API key | ✅ | ❌ tidak tahu-menahu |
| Koneksi ke WhatsApp | ❌ | ✅ |
| Jeda antar pesan | ❌ | ✅ |
| Antrean & percobaan ulang | ✅ | ✅ (per sesi) |

Engine **tidak punya database sama sekali**. Konsekuensinya menguntungkan: container engine bisa dibunuh, dihapus, dan dibangun ulang kapan saja tanpa kehilangan apa pun — semua yang perlu diingat ada di Laravel dan di volume.

---

## 4. Cara keduanya saling percaya

Dua arah, dua mekanisme berbeda.

### Laravel → Engine: token statis

Header `X-Engine-Token` pada setiap permintaan. Sederhana, karena Laravel yang memulai dan payload-nya tidak sensitif.

Engine tidak punya domain publik di Coolify, tapi jaringan internal **tetap bukan batas keamanan yang cukup** — siapa pun yang bisa menjangkau port itu bisa mengirim WhatsApp atas nama semua workspace. Karena itu tokennya tetap wajib.

### Engine → Laravel: tanda tangan HMAC + timestamp

Callback dari engine (QR baru, sesi siap, pesan masuk) ditandatangani:

```
signature = HMAC-SHA256(timestamp + "." + body, ENGINE_HMAC_SECRET)
```

Dikirim sebagai `X-Engine-Signature` dan `X-Engine-Timestamp`.

**Kenapa timestamp ikut ditandatangani.** Tanpa itu, siapa pun yang pernah menangkap satu callback "sesi terhubung" bisa memutarnya ulang kapan saja untuk membuat dashboard menampilkan keadaan palsu. Laravel menolak tanda tangan yang lebih tua dari 5 menit.

**Kenapa secret-nya berbeda dari token.** Kalau token bocor (misal dari log), ia tidak boleh sekaligus memberi kemampuan memalsukan callback.

### Kekhususan: unggahan cadangan sesi

File zip kredensial bisa puluhan MB. Menandatangani seluruh isinya berarti memuat semuanya ke memori PHP dua kali.

Untuk permintaan itu, engine mengirim sha256 isi file di header dan menandatangani **hash-nya**, bukan isinya:

```
signature = HMAC-SHA256(timestamp + "." + path + "." + sha256(file), secret)
```

Laravel memverifikasi tanda tangan lebih dulu (murah), menulis file secara streaming, lalu mencocokkan ulang sha256-nya. Keutuhan isi tetap terjamin tanpa pernah menampung file utuh di memori.

---

## 5. Alur data

### Mengirim pesan

```
1. Aplikasi   POST /api/v1/messages/text  (X-Api-Key)
2. Laravel    verifikasi kunci → cek workspace aktif → cek kuota
3. Laravel    normalisasi nomor (0812… → 62812…)
4. Laravel    simpan baris `messages` status=queued, naikkan pemakaian
5. Laravel    balas 202 dengan ULID  ← permintaan selesai di sini
6. Worker     ambil SendMessageJob
7. Worker     POST ke engine /sessions/{id}/messages
8. Engine     masuk antrean sesi → tunggu jeda 3–8 detik
9. Engine     pastikan nomor terdaftar di WhatsApp → kirim
10. Engine    balas id pesan WhatsApp
11. Worker    status=sent, kirim webhook message.status
12. Engine    ack menyusul → callback → delivered → read
```

Dua hal yang perlu diperhatikan:

**Kuota dihitung di langkah 4, saat diantre, bukan saat terkirim.** Kalau dihitung belakangan, satu workspace bisa mengantrekan puluhan ribu pesan lebih dulu dan baru ketahuan melewati batas setelah semuanya terlanjur terkirim.

**Permintaan selesai di langkah 5.** Pemanggil tidak menunggu pesan benar-benar terkirim — itu bisa memakan menit atau jam untuk broadcast. Status pengiriman ditelusuri lewat ULID atau webhook.

### Menerima pesan

```
1. Pelanggan membalas di WhatsApp
2. Engine     event 'message' dari whatsapp-web.js
3. Engine     callback bertanda tangan ke Laravel
4. Laravel    simpan `messages` direction=inbound, naikkan hitungan
5. Laravel    antrekan DeliverWebhookJob untuk tiap webhook aktif
6. Worker     POST ke URL workspace dengan X-Pesanvo-Signature
```

### Menautkan nomor

```
1. Pengguna   klik "Hubungkan" di dashboard
2. Laravel    status=connecting, POST ke engine /sessions/{id}/start
3. Engine     buat Client dengan RemoteAuth, jalankan Chromium
4. Engine     cek cadangan di Laravel — ada? pulihkan, tidak? QR baru
5. Engine     event 'qr' → render PNG → callback ke Laravel
6. Dashboard  polling tiap 3 detik → tampilkan QR
7. Pengguna   scan dengan WhatsApp
8. Engine     event 'ready' → callback: nomor & nama
9. Laravel    status=connected
10. Engine    tiap 5 menit: zip kredensial → unggah ke Laravel
```

---

## 6. Ketahanan sesi

Bagian ini adalah alasan utama gateway ini dibuat.

### Dua lapis penyimpanan

| Lapis | Menyelesaikan | Cara |
|---|---|---|
| Persistent volume `/data` | Deploy ulang biasa | Folder hidup di luar container |
| Cadangan di Laravel | Volume hilang, server diganti | Zip dikirim tiap 5 menit |

Keduanya diperlukan. Volume saja tidak cukup — volume bisa terhapus, dan container bisa dipindah ke host lain. Cadangan saja juga tidak cukup — memulihkan dari zip lebih lambat dan hanya seakurat cadangan terakhir.

### Pemulihan saat boot

Engine tidak menyimpan daftar sesi. Saat menyala ia memanggil `GET /internal/engine/bootstrap`, dan Laravel menjawab dengan sesi yang perlu dijalankan — yaitu yang `auto_reconnect` menyala dan statusnya pernah terhubung.

Sesi berstatus `pending` sengaja **tidak** ikut dijalankan: ia hanya akan memunculkan QR yang tidak ada yang men-scan, sambil memakan satu Chromium.

### Jaring pengaman

Callback menangani kasus normal. Tapi kalau container engine dibunuh mendadak, tidak ada event `disconnected` yang sempat terkirim — dan dashboard akan terus menampilkan sesi sebagai terhubung padahal sudah tidak.

`SyncSessionStatusJob` berjalan tiap menit: menanyakan keadaan sebenarnya ke engine, memperbarui database, dan menyambungkan ulang sesi yang putus.

---

## 7. Antrean dua lapis

Ada dua antrean berbeda, masing-masing dengan alasannya sendiri.

**Antrean Laravel (database).** Memisahkan pengiriman dari siklus request, memberi percobaan ulang dengan jeda menaik (10 detik, 1 menit, 5 menit), dan membuat pesan selamat dari restart aplikasi.

**Antrean engine (dalam memori, per sesi).** Memberi jeda acak 3–8 detik antar pesan dalam satu sesi.

Kenapa antrean kedua ada di engine dan bukan di Laravel? Karena isolasinya harus **per sesi**. Kalau jedanya diatur Laravel, broadcast 500 pesan milik satu workspace akan menahan satu pesan mendesak milik workspace lain. Dengan antrean per sesi di engine, keduanya berjalan paralel.

Jeda itu sendiri bukan hiasan: mengirim beruntun tanpa jeda adalah pola paling khas robot, dan cara paling cepat membuat nomor diblokir.

---

## 8. Lapisan provider

Semua pengiriman lewat satu interface, `WhatsAppProvider`:

```php
interface WhatsAppProvider
{
    public function startSession(WaSession $session): void;
    public function stopSession(WaSession $session): void;
    public function logoutSession(WaSession $session): void;
    public function status(WaSession $session): array;
    public function send(WaSession $session, Message $message): array;
}
```

Satu implementasi: `WwebjsProvider`, lewat engine Node. Kolom `wa_sessions.driver` selalu berisi `wwebjs`; ia mencatat, bukan menawarkan pilihan.

**Kenapa hanya satu.** Dulu ada dua lagi: slot `cloud_api` yang tidak pernah diimplementasi, dan `fonnte` — layanan gateway berbayar milik pihak lain. Keduanya muncul sebagai pilihan di form pembuatan sesi, jadi halaman pertama yang dilihat pelanggan baru menawarkan produk orang lain dan sebuah janji yang belum ada. Produk ini menjual satu cara mengirim WhatsApp; nomor pelanggan tidak pernah melewati gateway selain milik kita sendiri.

**Kenapa interface-nya tetap ada.** Bukan untuk menampung driver kedua, melainkan sebagai batas antara "apa yang dilakukan sebuah sesi" dan "bagaimana caranya" — batas itulah yang membuat `SendMessageJob` dan `SessionService` tidak perlu tahu apa pun soal Chromium.

### Kegagalan sementara vs permanen

`ProviderException` membedakan keduanya, dan bedanya penting:

- **Permanen** (nomor tidak terdaftar di WhatsApp, format salah) — langsung ditandai gagal, tidak diulang. Mengulanginya hanya membakar jatah percobaan.
- **Sementara** (sesi sedang putus, engine restart) — dikembalikan ke antrean. Sesi yang putus biasanya pulih sendiri; membuang pesannya berarti kehilangan notifikasi yang sah.

Engine memberi sinyal ini lewat kode HTTP: `422` berarti permanen, selain itu sementara.

---

## 9. Skema database

```
users ──┬── workspace_members ──┬── workspaces
        │                     ├── api_keys
        │                     ├── wa_sessions ──┬── session_backups
        │                     │                  └── messages
        │                     ├── message_templates
        │                     ├── webhooks ── webhook_deliveries
        │                     ├── usage_counters
        │                     └── audit_logs
        └── otp_codes (per nomor, bukan per user)
```

Beberapa keputusan yang layak dijelaskan:

**`wa_sessions.id` dan `messages.id` memakai ULID, bukan auto-increment.** Untuk sesi, id-nya ikut dipakai sebagai nama folder dan file zip di engine, jadi harus aman untuk nama file. Untuk pesan, id-nya dikembalikan ke pemanggil API — auto-increment akan membocorkan berapa banyak pesan yang lewat sistem, dan memungkinkan menebak id milik workspace lain.

**`usage_counters` menyimpan agregat bulanan terpisah.** Menghitung ulang dari tabel `messages` akan lambat (jutaan baris) dan salah (tabel itu dipangkas oleh retensi). Kolomnya dinaikkan dengan operasi increment mentah supaya aman dari race saat banyak worker memproses workspace yang sama.

**`api_keys` menyimpan `prefix` terpisah dari hash.** Prefix mempersempit pencarian ke satu baris sebelum verifikasi hash — tanpa itu, setiap permintaan API harus membandingkan hash ke seluruh isi tabel.

**Status pesan hanya boleh maju.** Ack dari WhatsApp bisa datang tidak berurutan; tanpa penjagaan, pesan yang sudah `read` bisa turun lagi jadi `delivered`. Logikanya ada di `Message::advanceStatus()`.

---

## 10. Isolasi antar workspace

Semua kueri dashboard berangkat dari workspace yang sedang dipilih, bukan dari model global. Middleware `EnsureWorkspaceSelected` menaruhnya di request, dan controller mengambilnya dari sana:

```php
$workspace = EnsureWorkspaceSelected::from($request);
$session = $workspace->sessions()->findOrFail($id);   // ← bukan WaSession::find($id)
```

Bedanya menentukan: `WaSession::find($id)` akan menemukan sesi milik siapa pun. `$workspace->sessions()->findOrFail($id)` hanya menemukan yang benar-benar milik workspace tersebut, dan melempar 404 untuk yang lain.

Pola yang sama berlaku di REST API, di mana workspace berasal dari API key. Ada tes khusus untuk ini (`test_kunci_workspace_lain_tidak_bisa_melihat_sesi_kita`).

---

## 11. Yang sengaja belum dibuat

Jujur mencatat batas, supaya tidak ada yang mengira ini sudah ada:

- **Billing & langganan.** Kolom `plan_slug` dan `usage_counters` sudah disiapkan, tapi belum ada penagihan otomatis — batas paket masih disetel manual.
- **Realtime.** Dashboard memakai polling 3 detik saat modal QR terbuka. Reverb bisa ditambahkan, tapi belum sepadan untuk satu kasus pakai.
- **Beberapa instance engine.** Saat ini satu engine per tahap. Untuk menyebar sesi ke banyak container perlu tabel pemetaan sesi→engine.
