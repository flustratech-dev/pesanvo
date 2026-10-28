<?php

namespace App\Support;

use App\Models\SpecialNumber;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Berapa nomor WhatsApp yang sanggup dijalankan platform ini SELURUHNYA.
 *
 * Ini bukan batas per workspace — itu `Workspace::max_sessions`, dan ia sudah
 * ditegakkan sejak lama. Ini batas yang selama ini tidak ditegakkan di mana pun
 * kecuali di engine, pada detik terakhir, setelah uang pelanggan masuk.
 *
 * ANGKANYA. `WA_MAX_SESSIONS` = 3 (konfigurasi batas slot engine). Paket menjual
 * `max_sessions` 1 untuk Essentials, 1 untuk Prime, dan 2 untuk Elite
 * (config/plans.php). Jadi tiga pelanggan Essentials sudah menghabiskan seluruh
 * platform, dan satu pelanggan Elite menghabiskan dua pertiganya.
 *
 * KENAPA KELAS INI ADA. Tanpa ia, urutan kejadiannya begini: pelanggan keempat
 * mendaftar, memilih paket, mentransfer, buktinya diperiksa, langganannya
 * aktif, ia membuat sesi — semuanya berhasil — lalu menekan Hubungkan dan
 * engine menjawab 409. Tidak ada satu pun langkah sebelumnya yang tahu apa-apa
 * soal batas ini. Pelanggan yang sudah membayar lalu tidak bisa menautkan
 * nomornya adalah kegagalan terburuk yang bisa dialami produk ini, dan seluruh
 * jalurnya berjalan mulus sampai detik terakhir.
 *
 * Yang dihitung KOMITMEN, bukan sesi yang sedang menyala. Sesi yang kebetulan
 * sedang mati tetap milik pelanggan yang membayarnya dan tetap akan minta
 * slotnya kembali; menghitung yang menyala berarti menjual slot yang sama dua
 * kali kepada dua orang yang sama-sama sudah membayar.
 */
class KapasitasPlatform
{
    public static function batas(): int
    {
        return (int) config('gateway.engine.max_sessions');
    }

    /**
     * Slot yang sudah dijanjikan kepada pelanggan yang layanannya hidup.
     *
     * Nomor istimewa (milik Pesanvo sendiri) tidak ikut dihitung di sisi
     * workspace — `sessionsTerhitung()` sudah mengecualikannya — tapi ia TETAP
     * memakan slot engine yang nyata. Jadi ia ditambahkan terpisah di sini.
     * Pengecualian yang bocor lebih jauh dari maksudnya adalah cara paling
     * halus sebuah SaaS menjual kapasitas yang tidak dimilikinya.
     */
    public static function terpakai(): int
    {
        /*
         | Hanya langganan BERBAYAR yang dihitung sebagai komitmen, dan itu
         | pembedaan yang tidak sengaja ditemukan tapi ternyata inti soalnya.
         |
         | Setiap pendaftar baru lahir di paket coba gratis dengan
         | `max_sessions` 1 (SubscriptionService::ensureFor). Kalau paket coba
         | ikut dihitung sebagai komitmen, pendaftar keempat sudah menghabiskan
         | seluruh kapasitas SEBELUM ada satu rupiah pun masuk — dan yang
         | tertahan di luar justru pelanggan yang mau membayar. Kapasitas yang
         | dijual habis oleh orang yang belum pernah membayar bukan kapasitas
         | yang terjaga; itu kapasitas yang hilang.
         |
         | Konsekuensi yang harus disadari dan memang disengaja: pengguna paket
         | coba bisa gagal menautkan nomornya saat slot engine sedang penuh
         | dipakai pelanggan berbayar. Itu benar. Yang tidak boleh terjadi
         | adalah kebalikannya.
         |
         | `status` di sini status LANGGANAN, bukan workspace: `active` berarti
         | sudah dibayar. `trialing` tidak ikut, `past_due` dan `suspended` juga
         | tidak — layanan mereka sedang berhenti, dan slotnya memang bebas.
        */
        $dijanjikan = Workspace::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('service_until')->orWhere('service_until', '>', now());
            })
            ->whereHas('subscription', fn ($q) => $q->where('status', 'active'))
            ->sum('max_sessions');

        // Nomor milik Pesanvo sendiri tidak menghitung jatah workspace mana pun
        // (`sessionsTerhitung()` mengecualikannya), tapi ia TETAP memakan slot
        // engine yang nyata. Pengecualian yang bocor lebih jauh dari maksudnya
        // adalah cara paling halus sebuah SaaS menjual kapasitas yang tidak
        // dimilikinya.
        $istimewa = WaSession::query()
            ->whereIn('phone_number', SpecialNumber::daftar() ?: ['-'])
            ->count();

        return (int) $dijanjikan + $istimewa;
    }

    /**
     * Slot yang SUDAH terhitung sebagai milik satu workspace.
     *
     * Definisinya harus sama persis dengan yang dijumlahkan `terpakai()`, dan
     * itu sebabnya ia ada di sini alih-alih ditulis ulang di pemanggilnya.
     * Ditulis dua kali sudah salah sekali: pemeriksaan di checkout memakai
     * `max_sessions` apa adanya, sehingga workspace paket coba — yang
     * `max_sessions`-nya 1 tapi komitmen berbayarnya nol — terbaca sudah
     * memiliki slot, dan gerbangnya melewatkannya begitu saja.
     */
    public static function komitmenWorkspace(Workspace $workspace): int
    {
        if (! $workspace->isActive()) {
            return 0;
        }

        return $workspace->subscription?->status === 'active'
            ? (int) $workspace->max_sessions
            : 0;
    }

    public static function tersisa(): int
    {
        return max(0, self::batas() - self::terpakai());
    }

    /**
     * Sesi yang BENAR-BENAR ada, terlepas dari komitmen paket siapa pun.
     *
     * Ini ukuran yang berbeda dari `terpakai()`, dan bedanya penting. Komitmen
     * menjawab "berapa slot yang sudah kita janjikan" dan dipakai sebelum
     * menerima uang. Ini menjawab "berapa nomor yang akan minta slot engine" dan
     * dipakai sebelum membuat sesi.
     *
     * Memakai komitmen di kedua tempat sudah salah sekali, dan salahnya halus:
     * komitmen sebuah workspace sudah termasuk dirinya sendiri, jadi pelanggan
     * yang jatah paketnya masih sisa tetap tertolak saat membuat sesi keduanya
     * — dihalangi oleh slot yang ia bayar sendiri.
     */
    public static function sesiAda(): int
    {
        return WaSession::query()->whereNot('status', 'pending')->count();
    }

    /**
     * Apakah seluruh slot engine sudah terpakai sesi yang nyata.
     *
     * Dipakai saat membuat sesi, bukan saat menerima pembayaran. Sesi
     * berstatus `pending` tidak dihitung: ia belum pernah dijalankan dan
     * belum memegang proses engine apa pun.
     */
    public static function slotHabis(): bool
    {
        return self::sesiAda() >= self::batas();
    }

    public static function penuh(): bool
    {
        return self::tersisa() <= 0;
    }

    /**
     * Apakah platform sanggup menerima komitmen sebanyak `$butuh` slot lagi.
     *
     * Dipakai SEBELUM pelanggan membayar. Menolak uang terdengar salah sampai
     * dibandingkan dengan alternatifnya: menerima uang untuk layanan yang tidak
     * bisa kita berikan, lalu menjelaskannya setelah transfernya masuk.
     */
    public static function sanggup(int $butuh): bool
    {
        return self::tersisa() >= $butuh;
    }

    /**
     * Kalimat untuk pelanggan saat kapasitas habis.
     *
     * Tidak menyebut angka, nama variabel env, maupun jumlah pelanggan lain:
     * itu urusan kami. Yang perlu diketahui pembacanya cuma bahwa ini antrean,
     * bukan kesalahannya, dan bahwa uangnya tidak diambil untuk sesuatu yang
     * belum bisa kami berikan.
     */
    public static function kalimat(): string
    {
        return 'Kapasitas nomor aktif sedang penuh, jadi kami menahan pendaftaran baru '
            .'sampai ada slot yang bebas. Kami tidak menagih untuk layanan yang belum bisa '
            .'kami jalankan. Permintaan Anda sudah kami catat di daftar tunggu — kami '
            .'mengabari Anda lewat email dan notifikasi begitu slotnya tersedia, tanpa '
            .'perlu menekan apa pun lagi.';
    }

    /**
     * Kalimat untuk yang sudah pernah masuk daftar tunggu.
     *
     * Dibedakan karena mengulang kalimat "sudah kami catat" kepada orang yang
     * memang sudah tercatat terbaca seperti sistem yang tidak mengingat apa-apa
     * — dan orang yang merasa tidak diingat akan menekan tombolnya lagi.
     */
    public static function kalimatSudahMenunggu(Carbon $sejak): string
    {
        return 'Kapasitas masih penuh. Permintaan Anda sudah tercatat di daftar tunggu sejak '
            .$sejak->translatedFormat('j F Y').' dan urutannya tidak berubah karena mencoba lagi. '
            .'Kami mengabari Anda begitu slotnya tersedia.';
    }
}
