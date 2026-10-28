<?php

/*
|--------------------------------------------------------------------------
| Template Bawaan
|--------------------------------------------------------------------------
|
| Template siap pakai yang tersedia untuk SEMUA workspace tanpa perlu dibuat
| lebih dulu. Tinggal dipilih di halaman Kirim Pesan, atau disalin ke daftar
| template sendiri kalau mau diubah.
|
| Ditaruh di config, bukan di-seed ke tabel `message_templates` tiap workspace
| baru. Alasannya sama dengan paket: yang di-seed adalah salinan mati — sekali
| dibuat, memperbaiki kalimatnya tidak pernah sampai ke workspace yang sudah
| ada, dan daftar template pelanggan langsung penuh delapan baris yang belum
| tentu mereka pakai. Di config, perbaikan kalimat berlaku untuk semua orang
| pada deploy berikutnya, dan daftar template pelanggan tetap berisi milik
| mereka sendiri.
|
| Nada seluruhnya sengaja seragam: sopan, langsung, tanpa basa-basi penjual.
| Ini pesan transaksional yang dibaca orang di tengah kesibukan, dan yang
| paling dihargai bukan keramahan melainkan kejelasan — nomor pesanan yang
| bisa dibaca sekilas, jumlah yang tidak perlu dihitung sendiri, dan satu
| kalimat yang menyebutkan apa yang harus dilakukan.
|
| Placeholder memakai sintaks yang sama dengan template buatan sendiri:
| {{ nama }}. Yang tidak diisi sengaja dibiarkan tampil apa adanya oleh
| MessageTemplate::render(), supaya kesalahan terlihat di pesan alih-alih
| hilang diam-diam jadi ruang kosong.
|
| CATATAN PENTING soal promosi: tidak ada satu pun template pemasaran di sini,
| dan itu keputusan sadar. Mengirim promosi ke nomor yang tidak memintanya
| adalah cara tercepat sebuah nomor WhatsApp diblokir — dan yang diblokir
| nomor pelanggan, bukan nomor kami.
|
*/

return [

    'bawaan' => [

        [
            'slug' => 'kode-otp',
            'name' => 'Kode verifikasi (OTP)',
            'kategori' => 'Verifikasi',
            'body' => "*{{ kode }}* adalah kode verifikasi Anda.\n\n"
                .'Berlaku {{ menit }} menit. Jangan berikan kode ini kepada siapa pun, '
                .'termasuk yang mengaku dari {{ perusahaan }}.',
        ],

        [
            'slug' => 'pesanan-diterima',
            'name' => 'Pesanan diterima',
            'kategori' => 'Pesanan',
            'body' => "Halo {{ nama }}, pesanan Anda sudah kami terima.\n\n"
                ."*No. pesanan:* {{ nomor_pesanan }}\n"
                ."*Total:* Rp {{ total }}\n\n"
                .'Kami kabari lagi begitu pesanannya dikirim.',
        ],

        [
            'slug' => 'pembayaran-diterima',
            'name' => 'Pembayaran diterima',
            'kategori' => 'Pesanan',
            'body' => "Terima kasih {{ nama }}, pembayaran Anda sudah kami terima.\n\n"
                ."*No. pesanan:* {{ nomor_pesanan }}\n"
                ."*Jumlah:* Rp {{ total }}\n"
                ."*Tanggal:* {{ tanggal }}\n\n"
                .'Pesanan Anda masuk proses berikutnya.',
        ],

        [
            'slug' => 'pesanan-dikirim',
            'name' => 'Pesanan dikirim',
            'kategori' => 'Pesanan',
            'body' => "Pesanan {{ nomor_pesanan }} sudah dikirim, {{ nama }}.\n\n"
                ."*Kurir:* {{ kurir }}\n"
                ."*No. resi:* {{ resi }}\n\n"
                .'Perkiraan sampai {{ estimasi }}. Kabari kami kalau ada yang tidak sesuai.',
        ],

        [
            'slug' => 'pengingat-pembayaran',
            'name' => 'Pengingat pembayaran',
            'kategori' => 'Tagihan',
            'body' => 'Halo {{ nama }}, tagihan {{ nomor_tagihan }} sebesar '
                ."*Rp {{ total }}* jatuh tempo {{ jatuh_tempo }}.\n\n"
                .'Kalau sudah dibayar, abaikan pesan ini — mungkin pembayarannya '
                .'belum sempat kami catat.',
        ],

        [
            'slug' => 'konfirmasi-jadwal',
            'name' => 'Konfirmasi jadwal',
            'kategori' => 'Jadwal',
            'body' => "Halo {{ nama }}, jadwal Anda sudah kami catat.\n\n"
                ."*Tanggal:* {{ tanggal }}\n"
                ."*Jam:* {{ jam }}\n"
                ."*Tempat:* {{ tempat }}\n\n"
                .'Balas pesan ini kalau perlu diubah.',
        ],

        [
            'slug' => 'pengingat-jadwal',
            'name' => 'Pengingat jadwal H-1',
            'kategori' => 'Jadwal',
            'body' => 'Pengingat: {{ nama }}, besok {{ tanggal }} pukul {{ jam }} '
                ."Anda dijadwalkan di {{ tempat }}.\n\n"
                .'Balas *BATAL* kalau berhalangan supaya slotnya bisa dipakai orang lain.',
        ],

        [
            'slug' => 'keluhan-diterima',
            'name' => 'Keluhan diterima',
            'kategori' => 'Dukungan',
            'body' => 'Halo {{ nama }}, keluhan Anda sudah kami terima dengan '
                ."nomor tiket *{{ nomor_tiket }}*.\n\n"
                .'Tim kami menindaklanjuti dan mengabari perkembangannya lewat '
                .'nomor ini. Terima kasih sudah memberi tahu.',
        ],

        [
            'slug' => 'di-luar-jam-kerja',
            'name' => 'Balasan di luar jam kerja',
            'kategori' => 'Dukungan',
            'body' => "Terima kasih sudah menghubungi {{ perusahaan }}.\n\n"
                .'Pesan Anda masuk di luar jam kerja kami ({{ jam_kerja }}). '
                ."Kami balas pada hari kerja berikutnya.\n\n"
                .'Kalau mendesak, hubungi {{ kontak_darurat }}.',
        ],

    ],

];
