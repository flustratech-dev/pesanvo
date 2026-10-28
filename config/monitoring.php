<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Denyut ke pemantau luar
    |--------------------------------------------------------------------------
    |
    | Alamat yang dipanggil `RekamStatusJob` tiap menit. Kalau panggilan itu
    | berhenti datang, pemantau di ujung sana yang mengabari tim.
    |
    | Ini satu-satunya cara mengetahui aplikasi mati TOTAL, dan alasannya bukan
    | soal kelengkapan fitur melainkan logika: sesuatu yang mati tidak bisa
    | melaporkan kematiannya sendiri. Halaman /status disajikan oleh aplikasi
    | yang statusnya ia laporkan — container yang tumbang membuat halaman itu
    | ikut tumbang, dan pelanggan melihat layar galat alih-alih tulisan "sedang
    | gangguan". Notifikasi email dan WhatsApp punya lubang yang sama: keduanya
    | dikirim oleh proses yang sedang mati.
    |
    | Layanan yang cocok dan gratis untuk pemakaian sebesar ini: BetterStack
    | Heartbeats, UptimeRobot, atau Healthchecks.io. Semuanya memberi satu URL;
    | tempelkan ke HEARTBEAT_URL.
    |
    | Kosong berarti fitur ini mati dan tidak ada permintaan keluar sama sekali —
    | halaman /admin/sistem menyebutkannya supaya keadaan itu tidak berlangsung
    | berbulan-bulan tanpa disadari.
    |
    */

    'heartbeat' => [
        'url' => env('HEARTBEAT_URL'),

        // Sengaja pendek. Denyut yang menggantung 30 detik menahan pekerja
        // antrean untuk sesuatu yang bukan bagian dari layanan; denyut yang
        // terlewat satu kali bukan gangguan, dan pemantau di ujung sana punya
        // toleransinya sendiri.
        'timeout' => (int) env('HEARTBEAT_TIMEOUT', 5),
    ],

    /*
    | Ambang yang membuat sebuah temuan cukup berat untuk mengganggu manusia
    | lewat email dan WhatsApp, bukan sekadar muncul di lonceng notifikasi.
    |
    | Angka yang terlalu rendah lebih berbahaya daripada terlalu tinggi:
    | peringatan yang berbunyi tiap hari untuk hal yang ternyata normal melatih
    | orang mengabaikannya, dan yang terabaikan berikutnya adalah yang sungguhan.
    */
    'ambang' => [
        'antrean_menunggu' => (int) env('ALERT_QUEUE_WAITING', 500),
    ],

];
