<?php

namespace App\Support;

/**
 * Mengganti penanda identitas di dokumen hukum dengan isinya.
 *
 * Keenam dokumen hukum menulis `{{legal.entity.name}}`, bukan nama badan
 * usahanya. Yang mengganti isinya kelas ini, sekali, saat halaman dirender.
 *
 * Yang membuat kelas ini ada bukan kerapian melainkan satu kegagalan yang
 * spesifik: nilai yang belum diisi **tidak boleh** menghilang. Kalau
 * `entity.name` kosong lalu penandanya dirender sebagai string kosong, yang
 * tayang adalah kalimat "Layanan ini diselenggarakan oleh , berkedudukan di ."
 * — kalimat yang terbaca seperti salah ketik biasa, bisa bertahan bertahun-tahun
 * tanpa ada yang melaporkannya, dan membuat seluruh dokumen tidak menyebut
 * siapa yang sebenarnya terikat. Karena itu yang kosong dirender mencolok:
 * `[BELUM DIISI: nama badan usaha]`.
 */
class Legal
{
    /**
     * Nama manusiawi tiap penanda, dipakai saat nilainya belum diisi.
     *
     * @var array<string, string>
     */
    private const LABEL = [
        'legal.entity.name' => 'nama badan usaha',
        'legal.entity.address' => 'alamat terdaftar badan usaha',
        'legal.entity.nib' => 'Nomor Induk Berusaha (NIB)',
        'legal.entity.npwp' => 'NPWP badan usaha',
        'legal.hosting' => 'nama penyedia server',
    ];

    /** Mengganti seluruh penanda `{{legal.*}}` di sebuah teks. */
    public static function isi(string $teks): string
    {
        return preg_replace_callback(
            '/\{\{\s*(legal\.[a-z0-9_.]+)\s*\}\}/i',
            fn (array $m): string => self::nilai(strtolower($m[1])),
            $teks
        ) ?? $teks;
    }

    /** Apakah masih ada identitas yang belum diisi. Dipakai panel admin. */
    public static function belumLengkap(): array
    {
        $kurang = [];

        foreach (self::LABEL as $penanda => $label) {
            if (blank(config($penanda))) {
                $kurang[$penanda] = $label;
            }
        }

        return $kurang;
    }

    private static function nilai(string $penanda): string
    {
        $isi = config($penanda);

        // Angka dan boolean ikut lewat sini (`sla.uptime_percent`,
        // `retention.billing_years`), jadi yang diperiksa `blank()` bukan
        // `empty()` — `0` adalah nilai yang sah dan tidak boleh dianggap kosong.
        if (blank($isi)) {
            return '**[BELUM DIISI: '.(self::LABEL[$penanda] ?? $penanda).']**';
        }

        return is_array($isi) ? implode(', ', $isi) : (string) $isi;
    }
}
