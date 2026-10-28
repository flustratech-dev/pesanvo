<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hitungan sehat/tidak sehat sebuah komponen dalam satu hari.
 *
 * Namanya Indonesia karena ia bukan istilah domain yang dipakai di antarmuka;
 * tabelnya tetap `status_daily` mengikuti aturan repo bahwa nama kolom dan
 * tabel berbahasa Inggris.
 */
class StatusHarian extends Model
{
    protected $table = 'status_daily';

    protected $fillable = ['component', 'day', 'ok_count', 'fail_count'];

    /*
     | `day` SENGAJA tidak di-cast ke tanggal, dan ini bukan kelalaian.
     |
     | Cast `date` membuat Eloquent MENULIS "2026-09-07 00:00:00" sementara
     | `firstOrCreate` MENCARI dengan "2026-09-07". Pencariannya tidak pernah
     | menemukan baris yang sudah ada, lalu penyisipannya menabrak indeks unik
     | — dan karena perekamnya menelan galat supaya tidak pernah menjatuhkan
     | apa pun, kegagalannya diam total: hitungan uptime berhenti bertambah
     | setelah menit pertama tiap hari, dan halaman status menampilkan angka
     | yang salah tanpa ada yang tahu.
     |
     | Sebagai string "Y-m-d" ia ditulis dan dicari dengan bentuk yang sama,
     | dan perbandingan `>=` untuk rentang 30/90 hari tetap benar baik secara
     | leksikografis di SQLite maupun sebagai DATE di MySQL.
    */
    protected function casts(): array
    {
        return [
            'ok_count' => 'integer',
            'fail_count' => 'integer',
        ];
    }

    /**
     * Persentase waktu sehat hari itu.
     *
     * Hari tanpa satu pun sampel mengembalikan `null`, BUKAN 0 dan bukan 100.
     * Keduanya berbohong ke arah yang berbeda: 0 menampilkan gangguan seharian
     * yang tidak pernah terjadi, 100 menjanjikan ketersediaan yang tidak pernah
     * diukur. Halaman status menggambar hari seperti itu sebagai abu-abu.
     */
    public function persen(): ?float
    {
        $total = $this->ok_count + $this->fail_count;

        return $total === 0 ? null : round($this->ok_count / $total * 100, 2);
    }
}
