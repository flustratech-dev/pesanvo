<?php

namespace App\Support;

use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Apakah worker antrean benar-benar memproses, atau cuma tampak begitu.
 *
 * Antrean adalah satu-satunya bagian sistem ini yang gagal tanpa satu pun
 * gejala di mana pun. Kalau `queue:work` mati, tidak ada galat, tidak ada
 * pesan merah, dan tidak ada baris log baru — pesan cuma diam berstatus
 * `queued` selamanya. Pengirimnya melihat "Mengantre" dan menyimpulkan
 * gateway-nya lambat, lalu menunggu. Penerimanya tidak menerima apa-apa dan
 * tidak tahu ada yang mengirim.
 *
 * Yang sulit di sini adalah membedakannya dari antrean yang memang panjang.
 * Broadcast seribu nomor sah-sah saja menyisakan pekerjaan berjam-jam: engine
 * menahan tiap pesan 3–8 detik sebagai jeda anti-ban, jadi job yang menumpuk
 * BUKAN tanda kerusakan. Menghitung panjang antrean saja menghasilkan
 * peringatan palsu setiap kali ada yang berkirim massal — dan peringatan palsu
 * berhenti dibaca lebih cepat daripada tidak ada peringatan sama sekali.
 *
 * Yang membedakan keduanya bukan panjangnya, melainkan **gerakannya**. Antrean
 * sehat selalu bergerak: ada pesan yang berpindah dari `queued` dalam beberapa
 * menit terakhir. Antrean mati punya pekerjaan menunggu dan tidak ada satu pun
 * yang bergerak.
 */
class KesehatanAntrean
{
    /** Antrean dianggap macet kalau tidak bergerak selama ini. */
    private const AMBANG_MENIT = 10;

    /**
     * @return array{macet: bool, menunggu: int, gagal: int, tertua: ?Carbon, terakhirBergerak: ?Carbon}
     */
    public static function periksa(): array
    {
        $menunggu = DB::table('jobs')->count();
        $tertuaUnix = DB::table('jobs')->min('available_at');
        $tertua = $tertuaUnix ? Carbon::createFromTimestamp($tertuaUnix) : null;

        // Bukti gerakan: pesan terakhir yang benar-benar meninggalkan antrean.
        // `sent_at` dipakai, bukan `updated_at`, karena yang terakhir ikut
        // berubah oleh ack dari WhatsApp — dan ack bisa datang untuk pesan lama
        // sementara worker-nya sendiri sudah mati.
        $terakhirBergerak = Message::whereNotNull('sent_at')->max('sent_at');
        $terakhirBergerak = $terakhirBergerak ? Carbon::parse($terakhirBergerak) : null;

        $batas = now()->subMinutes(self::AMBANG_MENIT);

        $macet = $menunggu > 0
            && $tertua !== null
            && $tertua->lt($batas)
            && ($terakhirBergerak === null || $terakhirBergerak->lt($batas));

        return [
            'macet' => $macet,
            'menunggu' => $menunggu,
            'gagal' => DB::table('failed_jobs')->count(),
            'tertua' => $tertua,
            'terakhirBergerak' => $terakhirBergerak,
        ];
    }

    public static function ambangMenit(): int
    {
        return self::AMBANG_MENIT;
    }
}
