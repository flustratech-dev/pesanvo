<?php

/*
|--------------------------------------------------------------------------
| Klien Pesanvo Gateway
|--------------------------------------------------------------------------
|
| File ini disalin apa adanya ke setiap aplikasi Pesanvo yang perlu mengirim
| WhatsApp (app-erp, app-web, app-pricing, app-helpdesk).
| Pasangannya: app/Services/WhatsAppGateway.php.
|
| Sumber aslinya ada di repo pesanvo (docs/client/). Kalau ada perubahan,
| ubah di sana dulu lalu salin ulang ke tiap aplikasi.
|
*/

return [

    'enabled' => env('WA_GATEWAY_ENABLED', true),

    'url' => env('WA_GATEWAY_URL', 'https://wa.pesanvo.id'),

    // API key milik tenant aplikasi ini di dashboard gateway.
    'key' => env('WA_GATEWAY_KEY'),

    // Kosongkan untuk memakai sesi tenant pertama yang sedang terhubung.
    // Isi kalau aplikasi ini harus selalu mengirim dari nomor tertentu.
    'session' => env('WA_GATEWAY_SESSION'),

    /*
    | Kredensial workspace CS — nomor yang mengabari operator Pesanvo sendiri
    | (bukti pembayaran baru, tiket baru), bukan pelanggan.
    |
    | Workspace terpisah dengan API key sendiri, bukan sesi kedua di workspace
    | di atas: kuota dan riwayat pesan CS jadi tidak bercampur dengan trafik
    | pelanggan, sehingga lonjakan di salah satunya tidak mendiamkan yang lain.
    |
    | Dikosongkan berarti kabar internal ikut kredensial platform di atas —
    | mendarat di chat "Pesan ke Diri Sendiri" seperti perilaku lama.
    */
    'cs_key' => env('WA_CS_GATEWAY_KEY'),

    'cs_session' => env('WA_CS_GATEWAY_SESSION'),

    // Sengaja pendek. Notifikasi WhatsApp adalah pelengkap email, bukan
    // penggantinya — gateway yang lambat tidak boleh menahan request pengguna.
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 5),

    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 2),

    /*
    | Hanya kirim ke nomor yang kepemilikannya sudah dibuktikan lewat OTP.
    |
    | Nomor di tabel users cuma pernah divalidasi formatnya. Kalau ada salah
    | ketik satu digit, pesan mendarat di HP orang asing — dan laporan spam dari
    | mereka bisa membuat nomor platform Pesanvo diblokir WhatsApp, yang
    | mematikan notifikasi untuk seluruh aplikasi sekaligus.
    |
    | Status verifikasi berasal dari app-auth dan dicerminkan ke kolom
    | users.phone_verified_at aplikasi ini lewat SSO. Jangan matikan opsi ini
    | di produksi.
    |
    | HAPUS baris ini kalau aplikasi Anda tidak punya kolom phone_verified_at
    | dan tidak memeriksanya. Config yang menjanjikan pengaman yang tidak
    | pernah dijalankan lebih berbahaya daripada tidak ada sama sekali —
    | app-erp sempat begitu selama berbulan-bulan.
    */
    'require_verified_phone' => env('WA_GATEWAY_REQUIRE_VERIFIED_PHONE', true),

];
