<?php

/*
|--------------------------------------------------------------------------
| Paket Langganan
|--------------------------------------------------------------------------
|
| Paket sengaja tinggal di berkas config, bukan di tabel database.
|
| Harga dan batas berubah beberapa kali setahun, bukan setiap hari. Menaruhnya
| di tabel berarti menambah migrasi, seeder, CRUD di admin, dan satu sumber
| kebenaran baru yang bisa berbeda antara dev, staging, dan produksi tanpa ada
| yang menyadarinya. Di config, setiap perubahan harga terlihat di diff git,
| ikut di-review, dan sampai ke semua tahap lewat jalur yang sama dengan kode.
|
| Konsekuensi mengubah angka di sini TIDAK seragam, dan bedanya penting:
|
|   - `max_sessions`, `monthly_message_quota`, `api_rate_limit_per_minute`
|     disalin ke kolom `workspaces` saat tagihan dibayar (`markPaid()`), jadi
|     perubahan di sini baru berlaku untuk pelanggan lama pada pembayaran
|     berikutnya. Itu memang yang diinginkan: menurunkan kuota di tengah
|     periode berarti mengecilkan sesuatu yang sudah dibayar penuh.
|   - `max_api_keys`, `max_members`, `message_retention_days` dibaca langsung
|     dari config setiap kali, jadi perubahannya berlaku SEKETIKA untuk semua
|     orang di paket itu. Menurunkan retensi berarti pesan lama pelanggan mulai
|     terhapus pada job pemangkasan berikutnya — periksa dampaknya dulu.
|
| Menaikkan harga adalah cerita lain: harganya dibaca saat tagihan diterbitkan,
| jadi ia langsung berlaku untuk perpanjangan siapa pun. Kalau perlu mengunci
| harga lama untuk pelanggan lama, beri slug baru (mis. `prime-2027`); jangan
| mengubah angka paket yang sedang dipakai orang.
|
| Nilai 0 berarti "tanpa batas", mengikuti perjanjian yang sudah dipakai
| `Workspace::hasQuotaRemaining()` untuk `monthly_message_quota`.
|
| Langganan melekat pada WORKSPACE, bukan pada akun. Satu orang yang memegang
| tiga cabang berlangganan tiga kali — dan itu memang yang seharusnya, karena
| biaya nyata kami (slot sesi nomor WhatsApp) juga tumbuh per workspace.
|
*/

return [

    /*
    | Paket yang diberikan ke workspace yang baru dibuat, dan ke workspace lama
    | yang belum pernah berlangganan saat penagihan dinyalakan.
    */
    'default' => 'essentials',

    /*
    | Paket coba gratis untuk workspace yang baru dibuat.
    |
    | Jatahnya dihitung SEUMUR HIDUP workspace, bukan per bulan — lihat
    | `Workspace::freeMessagesUsed()`. Kalau ia diperlakukan sebagai kuota
    | bulanan biasa, angkanya kembali penuh tiap tanggal 1 dan "lima pesan
    | gratis" berubah diam-diam menjadi lima pesan gratis selamanya.
    |
    | Paket ini `sellable => false`: ia tidak boleh muncul di halaman harga
    | maupun di dropdown admin, karena tidak ada yang bisa membelinya.
    */
    'free' => 'coba',

    /*
    | Harga tahunan = harga bulanan x 10. Dua bulan gratis, dan angkanya tidak
    | perlu ditulis dua kali sehingga tidak mungkin berbeda diam-diam.
    */
    'yearly_multiplier' => 10,

    /*
    |--------------------------------------------------------------------------
    | Harga perkenalan — HANYA pembelian pertama sebuah workspace
    |--------------------------------------------------------------------------
    |
    | `intro_price_monthly` dan `intro_price_yearly` di tiap paket adalah harga
    | yang dibayar SEKALI, pada tagihan pertama workspace yang belum pernah
    | membayar apa pun. Perpanjangan berikutnya selalu harga normal, dan itu
    | ditegakkan di `Workspace::berhakHargaPerkenalan()` — bukan dijanjikan di
    | teks halaman harga.
    |
    | Angkanya kira-kira separuh harga normal, dan itu ditanggung sadar: biaya
    | nyata kami per workspace adalah sesi engine yang sudah berjalan di
    | server yang sudah dibayar bulanan, jadi pelanggan tambahan di bulan
    | pertama nyaris tidak menambah ongkos. Yang benar-benar ditanggung adalah
    | risiko orang berhenti setelah bulan pertama — dan itulah sebabnya promo
    | ini tidak pernah berlaku dua kali untuk workspace yang sama.
    |
    | Boleh DITUMPUK dengan kode referal (keputusan Owner, 7 Sep 2026): promo
    | dipotong lebih dulu, lalu kode referal memotong sisanya, dan komisi
    | reseller dihitung dari angka yang benar-benar dibayar. Konsekuensinya
    | harus disadari saat mengubah angka di bawah — Essentials tahunan dengan
    | referal 10% dan komisi 20% menyisakan sekitar Rp 712.800 dari harga
    | normal Rp 1.490.000.
    |
    | Paket `coba` dan `payg` tidak punya harga perkenalan: yang pertama sudah
    | gratis, yang kedua tidak punya harga bulanan sama sekali.
    |
    */

    'catalog' => [

        'coba' => [
            'name' => 'Coba Gratis',
            'tagline' => 'Lima pesan untuk memastikan integrasinya jalan.',
            'price_monthly' => 0,
            'highlight' => false,
            'sellable' => false,

            'max_sessions' => 1,
            'monthly_message_quota' => 5,
            'max_api_keys' => 1,
            'max_members' => 1,
            'message_retention_days' => 7,
            'api_rate_limit_per_minute' => 10,

            'features' => [
                '1 nomor WhatsApp aktif',
                '5 pesan keluar — sekali seumur workspace',
                '1 API key',
                'Riwayat pesan 7 hari',
            ],
        ],

        /*
        | Pay as you go — bentuk harga yang berbeda dari yang lain.
        |
        | Rp 200 per pesan, saldo diisi di depan. Angkanya sengaja jauh di atas
        | harga per pesan paket termurah (Essentials: Rp 74,50) supaya PAYG
        | TIDAK menggerus penjualan paket. Titik impasnya 745 pesan per bulan;
        | di atas itu Essentials selalu lebih murah, dan itu cerita jualan yang
        | bersih: PAYG untuk yang kirimnya sedikit dan tidak tentu, paket untuk
        | yang rutin.
        |
        | `price_monthly => 0` karena tidak ada harga bulanan sama sekali; harga
        | sebenarnya ada di `config('billing.payg.price_per_message')`. Batas di
        | bawah tetap ditulis lengkap supaya seluruh kode yang membaca
        | `limits()`, `maxApiKeys()`, dan `messageRetentionDays()` tetap bekerja
        | tanpa satu pun pemeriksaan khusus. Yang membatasi pengiriman di sini
        | bukan `monthly_message_quota` melainkan saldo — jadi kuotanya 0, yang
        | di seluruh kode ini sudah berarti "tanpa batas".
        |
        | `payg => true` yang membuatnya tidak ikut `Plan::all()`: halaman harga
        | merendernya sebagai kartu keempat dengan bentuk sendiri, dan dropdown
        | admin tidak boleh memindahkan pelanggan berlangganan ke sini secara
        | tidak sengaja.
        */
        'payg' => [
            'name' => 'Pay as you go',
            'tagline' => 'Bayar per pesan, tanpa langganan bulanan.',
            'price_monthly' => 0,
            'highlight' => false,
            'sellable' => true,
            'payg' => true,

            'max_sessions' => 1,
            'monthly_message_quota' => 0,
            'max_api_keys' => 3,
            'max_members' => 2,
            'message_retention_days' => 30,
            'api_rate_limit_per_minute' => 60,

            /*
             | Harga per pesan SENGAJA tidak disebut di sini.
             |
             | Daftar ini dibaca halaman harga, dan angka per pesan di sana
             | mengundang orang menghitung sendiri lalu menyimpulkan paket
             | bulanan lebih murah — padahal PAYG bukan untuk mereka. Yang
             | dijual di halaman harga adalah bentuk pembayarannya (bayar
             | sesuai pemakaian, tanpa langganan), bukan tarifnya.
             |
             | Angkanya tetap terbuka penuh di halaman Saldo, tempat pelanggan
             | yang SUDAH memakainya perlu tahu saldonya habis untuk apa.
            */
            'features' => [
                '1 nomor WhatsApp aktif',
                'Bayar hanya saat pesan terkirim',
                'Saldo diisi di depan, mulai Rp 50.000',
                'Saldo tidak punya masa kedaluwarsa',
                'Pesan gagal tidak memotong saldo',
                '3 API key',
                'Riwayat pesan 30 hari',
                'Webhook, template, dan OTP',
            ],
        ],

        /*
        | Enterprise — harganya tidak ada di sini, dan itu memang bentuknya.
        |
        | Batas dan harga yang berlaku hidup di tabel `enterprise_plans`, satu
        | baris per workspace, karena kesepakatan enterprise dinegosiasikan per
        | pelanggan. Baris di katalog ini hanya ada supaya `Plan::exists()`
        | mengenali slug-nya saat tagihan terbit dan supaya namanya dibaca dari
        | satu tempat yang sama dengan paket lain.
        |
        | `sellable => false`: tidak ada yang bisa membelinya sendiri lewat
        | halaman harga — yang ada di sana tombol "Hubungi kami".
        |
        | Angka di bawah TIDAK semuanya cadangan, dan bedanya penting. Tiga yang
        | ditegakkan di jalur pengiriman — `max_sessions`,
        | `monthly_message_quota`, `api_rate_limit_per_minute` — disalin ke kolom
        | `workspaces` dari baris kesepakatan saat tagihannya lunas, jadi nilai
        | di sini cuma dipakai kalau baris itu hilang. Tiga sisanya
        | (`max_api_keys`, `max_members`, `message_retention_days`) dibaca
        | LANGSUNG dari sini setiap kali, seperti paket lain — jadi merekalah
        | yang benar-benar berlaku untuk pelanggan Enterprise, dan `0` di
        | keduanya berarti tanpa batas, mengikuti perjanjian yang sama dengan
        | Elite.
        */
        'enterprise' => [
            'name' => 'Enterprise',
            'tagline' => 'Batas dan harga disusun mengikuti kebutuhan Anda.',
            'price_monthly' => 0,
            'highlight' => false,
            'sellable' => false,

            'max_sessions' => 1,
            'monthly_message_quota' => 0,
            'max_api_keys' => 0,
            'max_members' => 0,
            'message_retention_days' => 365,
            'api_rate_limit_per_minute' => 300,

            'features' => [
                'Jumlah nomor WhatsApp sesuai kebutuhan',
                'Kuota pesan disusun bersama',
                'API key tanpa batas',
                'Retensi riwayat pesan lebih panjang',
                'Prioritas penanganan tiket',
                'Pendampingan integrasi',
            ],
        ],

        'essentials' => [
            'name' => 'Essentials',
            'tagline' => 'Satu usaha dengan satu nomor.',
            'price_monthly' => 149_000,
            'intro_price_monthly' => 79_000,
            'intro_price_yearly' => 990_000,
            'highlight' => false,

            'max_sessions' => 1,
            'monthly_message_quota' => 2_000,
            'max_api_keys' => 3,
            'max_members' => 2,
            'message_retention_days' => 30,
            'api_rate_limit_per_minute' => 60,

            'features' => [
                '1 nomor WhatsApp aktif',
                '2.000 pesan keluar per bulan',
                '3 API key',
                'Batas API 60 permintaan/menit',
                'Riwayat pesan 30 hari',
                '2 anggota tim',
                'Webhook, template, dan OTP',
                'Dukungan lewat email',
            ],
        ],

        'prime' => [
            'name' => 'Prime',
            'tagline' => 'Volume harian dari satu nomor.',
            'price_monthly' => 249_000,
            'intro_price_monthly' => 129_000,
            'intro_price_yearly' => 1_690_000,
            'highlight' => true,

            'max_sessions' => 1,
            'monthly_message_quota' => 10_000,
            'max_api_keys' => 10,
            'max_members' => 10,
            'message_retention_days' => 90,
            'api_rate_limit_per_minute' => 120,

            'features' => [
                '1 nomor WhatsApp aktif',
                '10.000 pesan keluar per bulan',
                '10 API key',
                'Batas API 120 permintaan/menit',
                'Riwayat pesan 90 hari',
                '10 anggota tim dengan peran',
                'Webhook, template, dan OTP',
                'Dukungan lewat email',
            ],
        ],

        'elite' => [
            'name' => 'Elite',
            'tagline' => 'Dua nomor dan riwayat setahun penuh.',
            'price_monthly' => 449_000,
            'intro_price_monthly' => 249_000,
            'intro_price_yearly' => 2_990_000,
            'highlight' => false,

            'max_sessions' => 2,
            'monthly_message_quota' => 50_000,
            'max_api_keys' => 0,
            'max_members' => 0,
            'message_retention_days' => 365,
            'api_rate_limit_per_minute' => 300,

            'features' => [
                '2 nomor WhatsApp aktif',
                '50.000 pesan keluar per bulan',
                'API key tanpa batas',
                'Batas API 300 permintaan/menit',
                'Riwayat pesan 12 bulan',
                'Anggota tim tanpa batas',
                'Webhook, template, dan OTP',
                'Dukungan lewat email',
            ],
        ],

    ],

];
