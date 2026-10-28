<?php

namespace App\Support;

class WilayahIndonesia
{
    protected static ?array $data = null;

    /**
     * Seluruh pohon data wilayah Indonesia: Provinsi -> Kota/Kabupaten -> Kecamatan.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function tree(): array
    {
        if (static::$data !== null) {
            return static::$data;
        }

        $path = public_path('data/wilayah.json');
        if (file_exists($path)) {
            $json = file_get_contents($path);
            static::$data = json_decode($json, true) ?: [];
        } else {
            static::$data = [];
        }

        return static::$data;
    }

    /**
     * Daftar 38 Provinsi di Indonesia.
     *
     * @return array<int, string>
     */
    public static function provinsi(): array
    {
        $tree = static::tree();
        if (!empty($tree)) {
            return array_keys($tree);
        }

        return [
            'Aceh',
            'Sumatera Utara',
            'Sumatera Barat',
            'Riau',
            'Kepulauan Riau',
            'Jambi',
            'Sumatera Selatan',
            'Kepulauan Bangka Belitung',
            'Bengkulu',
            'Lampung',
            'DKI Jakarta',
            'Jawa Barat',
            'Banten',
            'Jawa Tengah',
            'DI Yogyakarta',
            'Jawa Timur',
            'Bali',
            'Nusa Tenggara Barat',
            'Nusa Tenggara Timur',
            'Kalimantan Barat',
            'Kalimantan Tengah',
            'Kalimantan Selatan',
            'Kalimantan Timur',
            'Kalimantan Utara',
            'Sulawesi Utara',
            'Gorontalo',
            'Sulawesi Tengah',
            'Sulawesi Barat',
            'Sulawesi Selatan',
            'Sulawesi Tenggara',
            'Maluku',
            'Maluku Utara',
            'Papua',
            'Papua Barat',
            'Papua Selatan',
            'Papua Tengah',
            'Papua Pegunungan',
            'Papua Barat Daya',
        ];
    }

    /**
     * Daftar Kota / Kabupaten untuk suatu Provinsi.
     *
     * @return array<int, string>
     */
    public static function kota(string $provinsi): array
    {
        $tree = static::tree();
        return array_keys($tree[$provinsi] ?? []);
    }

    /**
     * Daftar Kecamatan untuk suatu Kota / Kabupaten.
     *
     * @return array<int, string>
     */
    public static function kecamatan(string $provinsi, string $kota): array
    {
        $tree = static::tree();
        return $tree[$provinsi][$kota] ?? [];
    }
}
