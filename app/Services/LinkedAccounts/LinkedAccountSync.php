<?php

namespace App\Services\LinkedAccounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim perubahan akun ke aplikasi pesanvo — sisi keluar akun tertaut.
 *
 * Yang dikirim HANYA identitas masuk: email, hash kata sandi, status
 * verifikasi, plus nama dan nomor untuk mengisi akun baru. Kata sandi tidak
 * pernah dikirim dalam bentuk asli; kedua aplikasi memakai bcrypt Laravel,
 * jadi hash-nya bisa dipakai apa adanya di seberang.
 *
 * Tidak pernah melempar galat ke pemanggilnya. Pendaftaran, penggantian kata
 * sandi, dan pemulihan akun di aplikasi ini tidak boleh gagal hanya karena
 * aplikasi seberang sedang tidak menjawab — yang gagal ditandai tertunda dan
 * diulang `akun-tertaut:kirim` tiap menit.
 */
class LinkedAccountSync
{
    public const ENDPOINT = '/api/internal/akun-tertaut';

    /** Kirim perubahan: kata sandi seberang ikut diganti. */
    public const MODE_SYNC = 'sync';

    /** Penautan awal: akun yang sudah ada di seberang tidak diubah kata sandinya. */
    public const MODE_LINK = 'link';

    public const TERKIRIM = 'terkirim';

    public const DITOLAK = 'ditolak';

    public const GAGAL = 'gagal';

    public const MATI = 'mati';

    public function enabled(): bool
    {
        return filled(config('services.linked_accounts.url'))
            && filled(config('services.linked_accounts.secret'));
    }

    /**
     * Menandai akun punya perubahan yang belum sampai ke seberang.
     *
     * Ditulis lewat query builder, bukan model, supaya tidak memicu observer
     * yang memanggilnya. Email lama yang sudah tercatat tidak ditimpa: kalau
     * email diganti dua kali sebelum sempat terkirim, yang dicari di seberang
     * tetap email yang benar-benar ada di sana.
     */
    public static function markPending(User $user, ?string $previousEmail = null): void
    {
        $ubah = ['linked_sync_pending_at' => now()];

        if ($previousEmail !== null) {
            $ubah['linked_sync_previous_email'] = DB::raw(
                'COALESCE(linked_sync_previous_email, '.DB::getPdo()->quote(mb_strtolower($previousEmail)).')'
            );
        }

        DB::table('users')->where('id', $user->id)->update($ubah);
    }

    public function payload(User $user, string $mode = self::MODE_SYNC): array
    {
        $user->refresh();

        return [
            'mode' => $mode,
            'origin' => 'wa',
            'email' => mb_strtolower((string) $user->email),
            'previous_email' => $user->linked_sync_previous_email,
            'name' => $user->name,
            'phone' => $user->phone,
            'password_hash' => (string) $user->getRawOriginal('password'),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, action: ?string}
     */
    public function push(User $user, string $mode = self::MODE_SYNC): array
    {
        if (! $this->enabled()) {
            return ['status' => self::MATI, 'action' => null];
        }

        $payload = $this->payload($user, $mode);
        $tandaSaatKirim = $user->linked_sync_pending_at;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $waktu = (string) now()->getTimestamp();

        try {
            $jawaban = Http::timeout(config('services.linked_accounts.timeout'))
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'X-Akun-Timestamp' => $waktu,
                    'X-Akun-Signature' => hash_hmac('sha256', $waktu.'.'.$body, config('services.linked_accounts.secret')),
                ])
                ->withBody($body, 'application/json')
                ->post(config('services.linked_accounts.url').self::ENDPOINT);
        } catch (Throwable $e) {
            Log::warning('Akun tertaut: pesanvo tidak terjangkau, diulang nanti.', [
                'user_id' => $user->id,
                'galat' => $e->getMessage(),
            ]);

            return ['status' => self::GAGAL, 'action' => null];
        }

        // Penautan awal (mode link) tidak mengganti kata sandi di seberang, jadi
        // tidak boleh melepas penanda perubahan kata sandi yang masih menunggu.
        $lepasPenanda = $mode === self::MODE_SYNC;

        if ($jawaban->successful()) {
            if ($lepasPenanda) {
                $this->clearPending($user, $tandaSaatKirim);
            }

            return ['status' => self::TERKIRIM, 'action' => $jawaban->json('action')];
        }

        // 4xx berarti seberang menolak ISINYA (email bentrok, data tidak sah) —
        // mengulangnya tiap menit tidak akan mengubah jawabannya, cuma memenuhi
        // log. Penandanya dilepas dan penolakannya dicatat keras.
        //
        // Kecuali 401 dan 429: itu soal KONFIGURASI atau beban (rahasia yang
        // belum disamakan, terlalu banyak kiriman), bukan soal akunnya. Melepas
        // penanda di situ berarti satu salah ketik rahasia menghapus seluruh
        // perubahan yang menunggu — jadi keduanya diulang seperti galat 5xx.
        if ($jawaban->clientError() && ! in_array($jawaban->status(), [401, 429], true)) {
            Log::error('Akun tertaut: pesanvo menolak perubahan akun.', [
                'user_id' => $user->id,
                'status' => $jawaban->status(),
                'pesan' => $jawaban->json('error.message'),
            ]);

            if ($lepasPenanda) {
                $this->clearPending($user, $tandaSaatKirim);
            }

            return ['status' => self::DITOLAK, 'action' => null];
        }

        Log::warning('Akun tertaut: pesanvo galat, diulang nanti.', [
            'user_id' => $user->id,
            'status' => $jawaban->status(),
        ]);

        return ['status' => self::GAGAL, 'action' => null];
    }

    /**
     * Penanda hanya dilepas kalau tidak ada perubahan baru sejak payload
     * disusun — kata sandi yang diganti lagi selagi pengiriman berjalan tetap
     * tertunda dan ikut terkirim pada putaran berikutnya.
     */
    private function clearPending(User $user, $tandaSaatKirim): void
    {
        $kueri = DB::table('users')->where('id', $user->id);

        if ($tandaSaatKirim !== null) {
            $kueri->where('linked_sync_pending_at', '<=', $tandaSaatKirim);
        }

        $kueri->update([
            'linked_sync_pending_at' => null,
            'linked_sync_previous_email' => null,
        ]);
    }
}
