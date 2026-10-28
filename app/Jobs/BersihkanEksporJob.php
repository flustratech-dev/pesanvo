<?php

namespace App\Jobs;

use App\Models\DataExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Membuang berkas ekspor yang sudah lewat masa unduhnya.
 *
 * Tiap berkas memuat seluruh isi percakapan sebuah workspace. Membiarkannya
 * menumpuk di disk berarti menyimpan salinan data paling sensitif pelanggan di
 * tempat yang tidak pernah dilihat siapa pun lagi — dan setiap salinan seperti
 * itu adalah kebocoran yang menunggu terjadi, tanpa menambah manfaat apa pun
 * setelah tautannya kedaluwarsa.
 *
 * Barisnya TIDAK ikut dihapus: pelanggan berhak melihat bahwa ia pernah
 * meminta ekspor dan kapan, dan catatan itu juga yang membuktikan kami memenuhi
 * hak aksesnya kalau suatu saat dipersoalkan.
 */
class BersihkanEksporJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $kedaluwarsa = DataExport::whereNotNull('path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($kedaluwarsa as $ekspor) {
            Storage::disk('local')->delete($ekspor->path);

            $ekspor->update(['path' => null, 'status' => 'kedaluwarsa']);
        }
    }
}
