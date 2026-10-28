# Pertanyaan yang Sering Muncul

## Umum

### Apa bedanya ini dengan tombol "Chat via WhatsApp" biasa?

Tombol `wa.me` hanya **membuka** aplikasi WhatsApp dengan pesan yang sudah terisi — manusia tetap harus menekan kirim. Gateway ini benar-benar mengirimkan pesannya, otomatis, tanpa ada orang yang menekan apa pun.

### Apakah ini resmi dari WhatsApp?

Bukan. Gateway ini memakai `whatsapp-web.js`, yang mengotomasi WhatsApp Web lewat browser. WhatsApp tidak mengizinkan klien tidak resmi, jadi nomor berisiko diblokir. Perbandingan lengkap dengan API resmi ada di [PERBANDINGAN_PROVIDER.md](PERBANDINGAN_PROVIDER.md).

### Kalau tidak resmi, kenapa tidak pakai Twilio saja?

Karena biayanya per pesan (±$0.008–$0.055 tergantung negara dan kategori), butuh verifikasi bisnis Meta, nomor khusus yang belum pernah dipakai WhatsApp biasa, dan template yang harus disetujui lebih dulu. Untuk kebutuhan Pesanvo sekarang, itu terlalu berat.

Arsitekturnya sudah multi-driver, jadi pindah ke API resmi nanti tidak mengubah cara aplikasi memanggilnya.

### Berapa banyak nomor yang bisa dipakai?

Dibatasi RAM server, bukan oleh sistem. Satu nomor ≈ 300–500 MB. Server 4 GB nyaman untuk 8–10 nomor.

---

## Nomor dan sesi

### Apakah nomor saya terkunci? Bisa diganti?

**Bisa, kapan saja.** Di dashboard: **Putus tautan** pada sesi, lalu **Hubungkan** dan scan QR dengan nomor mana pun — boleh nomor yang sama, boleh nomor berbeda.

Riwayat pesan, API key, dan webhook tetap utuh. Aplikasi yang memakai sesi itu tidak perlu diubah sama sekali.

Yang dijamin sistem justru kebalikannya: nomor **tidak akan terputus sendiri** hanya karena server di-deploy ulang.

### Kenapa dulu harus scan QR tiap deploy, sekarang tidak?

Dulu kredensial nomor disimpan di dalam container aplikasi. Setiap deploy, container dibuat ulang dari nol dan kredensialnya ikut terhapus.

Sekarang kredensial disimpan di penyimpanan permanen yang hidup terpisah dari container, **dan** dicadangkan ke database tiap 5 menit. Deploy ulang tidak menyentuh keduanya.

### Apakah HP harus selalu online?

Tidak harus terus-menerus, tapi HP perlu online sesekali. WhatsApp Multi-Device memungkinkan perangkat tertaut bekerja sendiri, tapi tautannya akan kedaluwarsa kalau HP tidak pernah online dalam waktu lama (± 14 hari).

### Bisakah satu nomor dipakai di dua sesi?

Tidak. Satu nomor WhatsApp hanya bisa tertaut ke satu sesi aktif. Men-scan nomor yang sama di sesi kedua akan memutus sesi pertama.

Ini juga alasan **nomor produksi tidak boleh dipakai untuk uji coba di dev/staging** — sesi produksinya akan terputus dan notifikasi pelanggan ikut berhenti.

### Bisakah WhatsApp tetap dipakai normal di HP?

Bisa. Gateway berjalan sebagai perangkat tertaut, sama seperti WhatsApp Web. Anda tetap bisa chat seperti biasa. Pesan yang dikirim gateway akan muncul di riwayat chat HP Anda.

### Bisakah kirim ke grup?

Bisa, dengan mengirim chat id grup (berakhiran `@g.us`) sebagai `to`. Cara mendapatkan id-nya: kirim pesan ke grup itu dari HP, lalu lihat `chat_id` pada pesan masuk di riwayat gateway.

---

## Mengirim pesan

### Kenapa broadcast saya lambat sekali?

Disengaja. Setiap pesan diberi jeda acak 3–8 detik. Broadcast 500 nomor wajar memakan 30–60 menit.

Mengirim beruntun tanpa jeda adalah pola paling khas robot, dan cara paling cepat membuat nomor diblokir. Jeda ini yang menjaga nomor Anda tetap hidup.

### Bisakah jedanya dipercepat?

`WA_MIN_DELAY_MS` dan `WA_MAX_DELAY_MS` bisa diubah, tapi **jangan diturunkan di produksi**. Di development jeda memang sengaja dipendekkan agar pengujian tidak berlarut-larut — di sana nomornya nomor uji coba.

### Apa arti status pesan?

| Status | Arti |
|---|---|
| `queued` | Sudah diterima gateway, menunggu giliran |
| `sending` | Sedang dikirim |
| `sent` | Sampai ke server WhatsApp (satu centang) |
| `delivered` | Sampai ke HP tujuan (dua centang) |
| `read` | Dibaca (dua centang biru) |
| `failed` | Gagal — alasannya ada di kolom `error` |

`delivered` dan `read` hanya muncul kalau penerima mengaktifkan laporan dibaca.

### Kenapa pesan saya `failed`?

Alasan tersering:

- Nomor tujuan tidak terdaftar di WhatsApp
- Sesi terputus dan tidak pulih dalam batas percobaan ulang
- Format nomor tidak valid setelah dinormalisasi

Alasan persisnya ada di kolom `error` pada detail pesan.

### Apakah pesan hilang kalau sesi sedang putus?

Tidak. Pesan dikembalikan ke antrean dan dicoba lagi dengan jeda menaik (10 detik, 1 menit, 5 menit). Sesi yang putus biasanya pulih sendiri dalam hitungan detik.

Baru setelah empat percobaan gagal, pesan ditandai `failed`.

### Nomor 0812 atau 62812?

Keduanya diterima. Gateway menormalkan `0812…`, `+62 812-3456-7890`, dan `62812…` menjadi satu bentuk yang sama.

---

## API dan integrasi

### API key saya hilang, bagaimana?

Tidak bisa dilihat lagi — yang tersimpan di server hanya hash-nya. Buat kunci baru, perbarui aplikasi Anda, lalu cabut yang lama.

### Kenapa satu kunci per aplikasi?

Supaya kunci yang bocor bisa dicabut tanpa mengganggu aplikasi lain. Kalau semua aplikasi memakai satu kunci, mencabutnya berarti mematikan semuanya sekaligus.

### Kenapa API mengembalikan 202, bukan 200?

202 berarti "diterima untuk diproses", bukan "sudah selesai". Pesan masuk antrean dan dikirim di latar belakang. Status sebenarnya ditelusuri lewat ULID yang dikembalikan, atau lewat webhook.

### Apa itu scope `otp` dan kenapa dibatasi?

Scope `otp` memberi kemampuan mengirim kode verifikasi ke nomor mana pun. Kunci yang bocor dengan scope ini bisa dipakai membombardir nomor orang lain — dan laporan spam dari mereka bisa membuat nomor platform Pesanvo diblokir, mematikan notifikasi seluruh ekosistem.

Karena itu scope ini hanya diberikan ke `app-auth`.

### Bagaimana memastikan webhook benar-benar dari Pesanvo?

Verifikasi header `X-Pesanvo-Signature`:

```php
$expected = hash_hmac('sha256', $request->getContent(), $webhookSecret);

if (! hash_equals($expected, $request->header('X-Pesanvo-Signature'))) {
    abort(401);
}
```

Tanpa verifikasi ini, siapa pun yang tahu URL webhook Anda bisa mengirim data palsu.

### Endpoint webhook saya sempat mati, pesannya hilang?

Kiriman diulang 3 kali dengan jeda menaik. Setelah 50 kegagalan berturut-turut, webhook dinonaktifkan otomatis — nyalakan lagi dari dashboard setelah endpoint pulih. Pesan masuknya sendiri tetap tersimpan di riwayat gateway.

---

## Nomor pengirim

### Kenapa notifikasi ke pelanggan saya harus dari nomor saya sendiri?

Dua alasan.

Pertama, pelanggan Anda tidak mengenal Pesanvo. Invoice yang datang dari nomor asing terlihat seperti penipuan.

Kedua, mengirim ratusan invoice per hari dari satu nomor platform adalah cara tercepat membuat nomor itu diblokir. Kalau itu terjadi, OTP dan seluruh notifikasi Pesanvo ikut mati — untuk semua pelanggan sekaligus.

### Kalau saya belum menautkan nomor sendiri, apakah dipakai nomor Pesanvo?

Tidak. Sistem sengaja **tidak** diam-diam memakai nomor platform. Yang terjadi: kembali ke tautan `wa.me` manual seperti sebelumnya.

### Kenapa notifikasi ke saya sendiri tidak sampai?

Kemungkinan besar nomor Anda belum diverifikasi. Selama belum terbukti kepemilikannya, semua aplikasi Pesanvo melewatinya — email tetap terkirim seperti biasa.

Verifikasi di `auth.pesanvo.id/account/phone`. Penjelasan lengkap: [VERIFIKASI_NOMOR.md](VERIFIKASI_NOMOR.md).

### Kenapa nomor harus diverifikasi dulu?

Kolom nomor di aplikasi Pesanvo selama ini hanya divalidasi *formatnya*. Kalau ada salah ketik satu digit, pesan mendarat di HP orang asing — dan laporan spam dari mereka bisa membuat nomor platform diblokir.

---

## Pengembangan

### Kenapa engine terpisah, tidak jadi satu dengan Laravel?

Chromium memakan RAM besar dan sesekali crash. Kalau satu container, ia menjatuhkan aplikasi juga — persis yang dulu terjadi di `app-erp`. Selain itu skala keduanya berbeda.

### Kenapa `npm run all` mematikan semua proses saat satu gagal?

Karena `--kill-others`. Ini disengaja: menjalankan Laravel tanpa engine, atau engine tanpa Laravel, hanya menghasilkan galat yang membingungkan. Lebih baik semuanya berhenti dan galat aslinya terlihat jelas di baris paling atas.

### Kenapa Laravel Pail tidak ikut?

Pail butuh ekstensi `pcntl` yang tidak ada di PHP Windows. Karena `--kill-others`, matinya Pail akan menjatuhkan ketiga proses lain. Di Linux/WSL, jalankan `php artisan pail` di terminal terpisah.

### Bisakah dipakai tanpa aplikasi Pesanvo lain?

Bisa. pesanvo berdiri sendiri: punya login/register, dashboard, dan REST API-nya sendiri. Aplikasi apa pun yang bisa mengirim HTTP request bisa memakainya.
