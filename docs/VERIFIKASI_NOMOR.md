# Verifikasi Nomor WhatsApp (OTP)

## Masalahnya

Sebelum ini, tidak ada satu pun aplikasi Pesanvo yang pernah membuktikan bahwa sebuah nomor benar-benar milik penggunanya:

- `app-web` **mewajibkan** nomor saat registrasi dan sudah menormalkannya ke format `62xxx` — tapi validasinya berhenti di format (`RegisteredUserController`).
- `app-pricing` dan `app-helpdesk` menyimpannya opsional.
- `app-auth`, pemilik identitas, tidak menyimpan nomor sama sekali.
- Satu-satunya verifikasi yang ada di seluruh ekosistem adalah lupa password lewat email.

Selama gateway hanya dipakai internal, ini tidak terasa. Begitu sistem mulai mengirim WhatsApp otomatis, konsekuensinya nyata: satu digit salah ketik berarti pesan mendarat di HP orang asing. Orang itu menandai pesannya sebagai spam, dan **nomor platform Pesanvo bisa diblokir WhatsApp** — mematikan OTP dan seluruh notifikasi untuk semua aplikasi sekaligus.

## Pembagian tanggung jawab

| Bagian | Siapa |
|---|---|
| Membuat, mengirim, dan mencocokkan kode OTP | **pesanvo** — hanya di sini ada kemampuan mengirim WhatsApp |
| Menyimpan nomor & status verifikasinya | **app-auth** — nomor adalah bagian dari identitas |
| Menyebarkan status ke aplikasi lain | claim `phone` & `phone_verified` di `/oauth/userinfo` |
| Mematuhi status tersebut | setiap aplikasi konsumen, lewat `require_verified_phone` |

Hasilnya: pengguna cukup verifikasi **satu kali**, dan berlaku di web, pricing, helpdesk, dan office sekaligus. Kode OTP-nya sendiri tidak pernah melewati app-auth.

## Alur

```
Pengguna                app-auth                 pesanvo
   │                         │                            │
   ├─ isi nomor ────────────▶│                            │
   │                         ├─ POST /api/v1/otp/send ───▶│
   │                         │                            ├─ kirim WA dari nomor platform
   │◀────────── kode 6 digit lewat WhatsApp ──────────────┤
   ├─ masukkan kode ────────▶│                            │
   │                         ├─ POST /api/v1/otp/verify ─▶│
   │                         │◀──── verified: true ───────┤
   │                         ├─ set users.phone_verified_at
   │◀── terverifikasi ───────┤
```

Setelah itu, setiap login SSO membawa `phone_verified: true`, dan aplikasi konsumen mencerminkannya ke kolom `users.phone_verified_at` mereka sendiri.

## Pembatasan penyalahgunaan

Endpoint OTP adalah endpoint paling berbahaya di gateway: siapa pun yang bisa memanggilnya bisa mengirim WhatsApp ke nomor mana pun. Karena itu berlapis:

| Lapis | Batas |
|---|---|
| Scope API key | Wajib scope `otp` — tidak diberikan ke kunci integrasi biasa |
| Jeda kirim ulang | 60 detik per nomor per tujuan |
| Batas harian | 10 kode per nomor per hari |
| Percobaan salah | 5 kali per kode, lalu kode hangus |
| Masa berlaku | 5 menit |
| Per akun (di app-auth) | 5 permintaan per jam |

Kode disimpan ter-hash, bukan apa adanya. Kode lama untuk tujuan yang sama dimatikan setiap kali kode baru dibuat, sehingga kode dari pesan lama tidak bisa dipakai lagi.

## Pemasangan

### app-auth

1. Jalankan migrasi `2026_08_13_120000_add_phone_to_users_table`.
2. Isi env:
   ```env
   WA_GATEWAY_URL=https://wa.pesanvo.id
   WA_GATEWAY_KEY=vwa_xxxx.xxxx    # kunci dengan scope `otp`
   ```
3. Halaman verifikasi ada di `/account/phone`.

> pesanvo sendiri **tidak** memakai SSO — ia punya login dan register lokalnya
> sendiri, sama seperti aplikasi Pesanvo lain. Hubungannya dengan app-auth
> di sini hanya satu arah: app-auth memanggil API OTP milik gateway.

### Backfill nomor yang sudah ada

`app-web` sudah punya nomor semua penggunanya. Supaya mereka tidak perlu mengetik ulang:

```bash
php artisan phone:backfill-from-web --dry-run
php artisan phone:backfill-from-web
```

Perlu koneksi database `web` di `config/database.php` milik app-auth. Command ini **hanya menyalin nomornya** — `phone_verified_at` sengaja dibiarkan kosong, jadi setiap pengguna tetap melewati OTP sekali. Menandai nomor terverifikasi tanpa pernah membuktikannya akan menghilangkan seluruh gunanya.

### Aplikasi konsumen

Jalankan migrasi `2026_08_13_110000_*` di pricing, helpdesk, dan web (yang terakhir juga menambah `wa_notifications_enabled`), lalu pastikan callback SSO mencerminkan claim `phone_verified` ke kolom lokal.

## Selama masa transisi

Sampai pengguna benar-benar memverifikasi nomornya, `require_verified_phone` membuat semua notifikasi WhatsApp ke pengguna **dilewati diam-diam** — email tetap terkirim seperti biasa, jadi tidak ada notifikasi yang hilang.

Ini disengaja. Alternatifnya — mengirim ke nomor yang belum terbukti — mempertaruhkan nomor platform yang jadi tumpuan seluruh ekosistem. Yang perlu didorong adalah adopsi verifikasinya, bukan mematikan pemeriksaannya.

Catatan: pengiriman ke nomor yang diinput workspace sendiri (customer, vendor, karyawan di app-web dan app-erp) **tidak** terkena pembatasan ini. Nomor itu memang tanggung jawab workspace, dan dikirim dari nomor workspace sendiri.
