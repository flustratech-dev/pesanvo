<?php

namespace App\Support;

/**
 * Terjemahan status menjadi label dan warna lencana.
 *
 * Dikumpulkan di sini karena status yang sama muncul di banyak halaman —
 * langganan di panel admin, di halaman langganan pelanggan, di daftar sesi.
 * Menuliskan petanya ulang di tiap Blade berarti suatu saat "Ditangguhkan"
 * berwarna merah di satu halaman dan abu-abu di halaman lain, dan pembacanya
 * menyimpulkan keduanya berarti hal yang berbeda.
 */
class StatusBadge
{
    /** @return array{label: string, warna: string} */
    public static function subscription(?string $status): array
    {
        return match ($status) {
            'active' => ['label' => 'Aktif', 'warna' => 'hijau'],
            'trialing' => ['label' => 'Masa percobaan', 'warna' => 'biru'],
            'unpaid' => ['label' => 'Belum berlangganan', 'warna' => 'netral'],
            'past_due' => ['label' => 'Lewat jatuh tempo', 'warna' => 'kuning'],
            'suspended' => ['label' => 'Ditangguhkan', 'warna' => 'merah'],
            'canceled' => ['label' => 'Dihentikan', 'warna' => 'netral'],
            default => ['label' => $status ?? '—', 'warna' => 'netral'],
        };
    }

    /** @return array{label: string, warna: string} */
    public static function invoice(string $status): array
    {
        return match ($status) {
            'paid' => ['label' => 'Lunas', 'warna' => 'hijau'],
            'pending' => ['label' => 'Menunggu', 'warna' => 'kuning'],
            'expired' => ['label' => 'Kedaluwarsa', 'warna' => 'netral'],
            'canceled' => ['label' => 'Dibatalkan', 'warna' => 'netral'],
            default => ['label' => $status, 'warna' => 'netral'],
        };
    }

    /** @return array{label: string, warna: string} */
    public static function session(string $status): array
    {
        return match ($status) {
            'connected' => ['label' => 'Terhubung', 'warna' => 'hijau'],
            'connecting' => ['label' => 'Menghubungkan', 'warna' => 'biru'],
            'qr' => ['label' => 'Menunggu scan QR', 'warna' => 'kuning'],
            'failed' => ['label' => 'Gagal', 'warna' => 'merah'],
            'disconnected' => ['label' => 'Terputus', 'warna' => 'netral'],
            'pending' => ['label' => 'Belum dijalankan', 'warna' => 'netral'],
            default => ['label' => $status, 'warna' => 'netral'],
        };
    }

    /** @return array{label: string, warna: string} */
    public static function message(string $status): array
    {
        return match ($status) {
            'read' => ['label' => 'Dibaca', 'warna' => 'hijau'],
            'delivered' => ['label' => 'Sampai', 'warna' => 'hijau'],
            'sent' => ['label' => 'Terkirim', 'warna' => 'biru'],
            'queued' => ['label' => 'Mengantre', 'warna' => 'kuning'],
            'failed' => ['label' => 'Gagal', 'warna' => 'merah'],
            default => ['label' => $status, 'warna' => 'netral'],
        };
    }
}
