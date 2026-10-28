<?php

namespace App\Support;

use App\Models\MessageTemplate;

/**
 * Template siap pakai yang berlaku untuk semua workspace.
 *
 * Dibaca dari `config/templates.php`, bukan dari tabel. Konsekuensi yang harus
 * diingat: memperbaiki kalimatnya berlaku untuk SEMUA workspace pada deploy
 * berikutnya — termasuk yang sudah lama memakainya. Itu memang yang diinginkan
 * untuk template bawaan, dan justru alasan ia tidak di-seed ke tabel; salinan
 * yang sudah terlanjur dibuat tidak pernah bisa diperbaiki lagi dari sini.
 *
 * Yang ingin mengubahnya untuk dirinya sendiri menyalinnya ke daftar template
 * workspace — sejak saat itu salinannya miliknya sepenuhnya dan tidak pernah
 * ikut berubah lagi.
 */
class TemplateBawaan
{
    /** @return array<int, array{slug: string, name: string, kategori: string, body: string, variables: array<int, string>}> */
    public static function semua(): array
    {
        return array_map(
            fn (array $t) => $t + ['variables' => MessageTemplate::extractVariables($t['body'])],
            config('templates.bawaan', [])
        );
    }

    /** @return array<int, array{slug: string, name: string, kategori: string, body: string, variables: array<int, string>}>|null */
    public static function cari(string $slug): ?array
    {
        foreach (self::semua() as $t) {
            if ($t['slug'] === $slug) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Dikelompokkan per kategori untuk ditampilkan, urut seperti di config.
     *
     * Sembilan template dalam satu daftar datar sulit dipindai; dikelompokkan,
     * orang menemukan yang dicarinya tanpa membaca semuanya.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function perKategori(): array
    {
        $hasil = [];

        foreach (self::semua() as $t) {
            $hasil[$t['kategori']][] = $t;
        }

        return $hasil;
    }
}
