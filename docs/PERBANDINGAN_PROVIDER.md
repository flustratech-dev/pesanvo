# Kenapa whatsapp-web.js, dan Apa Risikonya

Dokumen ini menjawab pertanyaan: kalau ada jalur resmi dari Meta dan layanan berbayar seperti Twilio, kenapa gateway ini dibangun di atas whatsapp-web.js — dan kapan sebuah kebutuhan sebaiknya tidak dilayani produk ini sama sekali.

Jalur resmi di bawah **bukan bagian dari produk ini** dan tidak dijual di sini. Ia dijelaskan supaya kita tahu persis apa yang kita tawarkan dan apa yang tidak.

## Dua jalur yang berbeda secara mendasar

### 1. whatsapp-web.js — yang dipakai produk ini

Bukan API. Library ini menjalankan **WhatsApp Web di dalam browser Chromium** yang dikendalikan program, lalu menekan tombol-tombolnya secara otomatis. Dari sudut pandang WhatsApp, yang terlihat adalah perangkat tertaut biasa — persis seperti WhatsApp Web di laptop Anda.

Konsekuensinya:

- **Gratis.** Tidak ada biaya per pesan. Yang dibayar hanya server.
- **Nomor apa pun bisa dipakai**, termasuk nomor WhatsApp pribadi/bisnis yang sudah Anda pakai sehari-hari.
- **Setup-nya scan QR**, selesai dalam hitungan detik. Tidak ada pendaftaran, verifikasi bisnis, atau persetujuan template.
- **Bebas format.** Bisa mengirim apa saja, kapan saja, ke siapa saja.

Risiko yang harus jujur diakui:

- **Tidak resmi.** Dokumentasi whatsapp-web.js sendiri menyatakan WhatsApp tidak mengizinkan bot atau klien tidak resmi. Nomor bisa diblokir, dan tidak ada jalur banding.
- **Rapuh terhadap perubahan.** Kalau WhatsApp mengubah antarmuka WhatsApp Web, library harus menyesuaikan dan gateway bisa berhenti bekerja sampai versi baru rilis.
- **Berat.** Satu sesi = satu Chromium ≈ 300–500 MB RAM. Sepuluh nomor butuh server 4–5 GB.
- **Tidak ada SLA.** Tidak ada siapa pun yang bisa dimintai pertanggungjawaban saat bermasalah.

Yang gateway ini lakukan untuk menekan risikonya: jeda acak 3–8 detik antar pesan keluar per sesi, antrean terpisah per sesi, verifikasi nomor tujuan sebelum kirim, dan kuota per workspace.

### 2. WhatsApp Business Platform resmi — bukan bagian produk ini

Ini API sungguhan dari Meta. Bisa diakses langsung (Cloud API) atau lewat Business Solution Provider seperti Twilio, yang menambahkan tooling dan penagihan di atasnya.

- **Resmi dan stabil.** Ada SLA, dukungan, dan tidak akan patah karena perubahan tampilan.
- **Syarat masuknya berat.** Butuh Meta Business Manager terverifikasi, dan **nomor khusus** yang belum pernah dipakai di aplikasi WhatsApp biasa.
- **Semua pesan di luar jendela 24 jam wajib memakai template yang disetujui Meta.** Tidak bisa mengarang kalimat bebas. Persetujuan template butuh waktu, dan Meta bisa mengubah kategori template Anda sepihak — yang ikut mengubah tarifnya.
- **Berbayar per pesan.** Sejak 1 Juli 2025 Meta menagih per pesan terkirim, bukan lagi per percakapan.

Gambaran biaya (per Agustus 2026):

| Komponen | Tarif |
|---|---|
| Biaya per pesan Twilio | ±$0.005 (masuk maupun keluar) |
| Biaya template Meta | $0.0034 – $0.0499, tergantung negara & kategori |
| Pesan bebas format dalam jendela layanan 24 jam | Gratis |

Kategori template menentukan tarif: *marketing* paling mahal dan tidak punya diskon volume; *utility* dan *authentication* lebih murah dan tarifnya turun setelah melewati ambang volume bulanan.

## Perbandingan ringkas

| | whatsapp-web.js | Cloud API / Twilio |
|---|---|---|
| Biaya per pesan | Rp 0 | ±$0.008–$0.055 |
| Status | Tidak resmi | Resmi |
| Risiko nomor diblokir | Nyata | Sangat kecil |
| Nomor yang bisa dipakai | Nomor apa pun | Nomor khusus, belum pernah dipakai WhatsApp |
| Waktu setup | Menit (scan QR) | Hari–minggu (verifikasi bisnis) |
| Isi pesan | Bebas | Wajib template disetujui, kecuali dalam jendela 24 jam |
| RAM per nomor | 300–500 MB | ~0 |
| Cocok untuk | Notifikasi internal, UMKM, volume kecil–menengah | Enterprise, volume besar, komunikasi kritis |

## Siapa yang sebaiknya tidak memakai produk ini

Menjual ke pelanggan yang salah lebih merugikan daripada tidak menjual sama sekali: mereka akan kecewa, dan nomor merekalah yang diblokir.

Arahkan ke jalur resmi Meta, bukan ke sini, kalau kebutuhannya:

- Komunikasi yang tidak boleh gagal — OTP perbankan, notifikasi keselamatan, apa pun yang punya konsekuensi hukum bila tidak sampai.
- Volume puluhan ribu pesan per bulan. Selain biaya RAM-nya menjadi tidak masuk akal, polanya juga paling cepat memancing pemblokiran.
- Butuh SLA tertulis atau jaminan kepatuhan.

Dulu ada rencana menampung keduanya lewat driver `cloud_api` di gateway yang sama. Rencana itu dibatalkan: syarat masuk jalur resmi (Business Manager terverifikasi, nomor khusus, template yang disetujui Meta, pelacakan jendela 24 jam) tidak cuma menambah satu implementasi provider — ia mengubah bentuk produknya. Menyatukan keduanya di satu antarmuka berarti setengah fiturnya tidak berlaku untuk setengah pelanggan.

## Sumber

- [Dokumentasi whatsapp-web.js](https://docs.wwebjs.dev/)
- [WhatsApp Messaging Pricing — Twilio](https://www.twilio.com/en-us/whatsapp/pricing)
- [Pricing on the WhatsApp Business Platform — Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing)
