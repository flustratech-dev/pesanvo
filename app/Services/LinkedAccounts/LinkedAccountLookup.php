<?php

namespace App\Services\LinkedAccounts;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjemput akun dari aplikasi pesanvo saat orangnya mencoba masuk di sini.
 *
 * Pengiriman otomatis (LinkedAccountSync) hanya membawa akun yang lahir atau
 * berubah SETELAH penautan menyala. Akun pesanvo yang sudah ada sebelum itu
 * baru sampai ke sini kalau `akun-tertaut:tautkan` dijalankan — dan sampai
 * saat itu, orang dengan email dan kata sandi yang benar disuruh mendaftar
 * ulang, persis yang dijanjikan tidak akan terjadi. Penjemputan ini menutup
 * celah itu dari sisi yang paling terasa: saat masuk.
 *
 * Kata sandi yang diketik TIDAK pernah dikirim ke mana pun. Yang diminta dari
 * seberang hanya hash akun untuk email itu; pencocokannya terjadi di sini.
 *
 * Akun lokal yang sudah ada hanya diperbarui dari seberang kalau email akun
 * seberang sudah terverifikasi — aturan yang sama dengan LinkedAccountReceiver,
 * dan alasannya sama: akun seberang yang emailnya belum terbukti bisa saja
 * didaftarkan orang lain memakai email korban.
 */
class LinkedAccountLookup
{
    public const ENDPOINT = '/api/internal/akun-tertaut/cari';

    public function __construct(
        private readonly LinkedAccountSync $sync,
        private readonly LinkedAccountReceiver $receiver,
    ) {}

    /**
     * Masuk dengan email + kata sandi yang belum dikenal di sini, atau yang
     * kata sandinya di sini tertinggal dari pesanvo.
     *
     * Mengembalikan pengguna lokal yang sudah dibuat/diperbarui kalau kata
     * sandinya cocok dengan akun pesanvo, null kalau tidak.
     */
    public function masukDenganSandi(string $email, string $sandi): ?User
    {
        $email = mb_strtolower(trim($email));
        $lokal = User::where('email', $email)->first();

        // Admin gateway lahir dari env, bukan dari seberang.
        if ($lokal?->is_super_admin) {
            return null;
        }

        $akun = $this->cari($email);

        if (! $akun || ! Hash::check($sandi, $akun['password_hash'])) {
            return null;
        }

        return $this->terapkan($akun, $lokal ? LinkedAccountSync::MODE_SYNC : LinkedAccountSync::MODE_LINK);
    }

    /**
     * Masuk lewat Google dengan email yang belum dikenal di sini. Google sudah
     * membuktikan kepemilikan emailnya, jadi yang perlu dipastikan hanya bahwa
     * akunnya memang ada di pesanvo.
     */
    public function masukDenganGoogle(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        if (User::where('email', $email)->exists()) {
            return null;
        }

        $akun = $this->cari($email);

        if (! $akun) {
            return null;
        }

        // Google baru saja membuktikan email ini milik orang yang sedang masuk.
        $akun['email_verified_at'] ??= now()->toIso8601String();

        return $this->terapkan($akun, LinkedAccountSync::MODE_LINK);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cari(string $email): ?array
    {
        if (! $this->sync->enabled()) {
            return null;
        }

        // Email yang baru saja tidak ditemukan tidak ditanyakan lagi selama
        // semenit: formulir masuk yang dicoba berulang dengan email asal tidak
        // boleh berubah jadi banjir permintaan ke aplikasi seberang.
        $kunciKosong = 'akun-tertaut:tidak-ada:'.sha1($email);

        if (Cache::has($kunciKosong)) {
            return null;
        }

        $body = json_encode(['email' => $email], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $waktu = (string) now()->getTimestamp();

        try {
            $jawaban = Http::timeout(5)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeaders([
                    'X-Akun-Timestamp' => $waktu,
                    'X-Akun-Signature' => hash_hmac('sha256', $waktu.'.'.$body, config('services.linked_accounts.secret')),
                ])
                ->withBody($body, 'application/json')
                ->post(config('services.linked_accounts.url').self::ENDPOINT);
        } catch (Throwable $e) {
            Log::warning('Akun tertaut: pesanvo tidak terjangkau saat menjemput akun.', ['galat' => $e->getMessage()]);

            return null;
        }

        if ($jawaban->status() === 404) {
            Cache::put($kunciKosong, true, now()->addMinute());

            return null;
        }

        if (! $jawaban->successful() || ! is_array($jawaban->json('data'))) {
            Log::warning('Akun tertaut: pesanvo menolak permintaan akun.', ['status' => $jawaban->status()]);

            return null;
        }

        $akun = $jawaban->json('data');

        if (($akun['email'] ?? null) !== $email || blank($akun['password_hash'] ?? null)
            || Hash::info($akun['password_hash'])['algoName'] === 'unknown') {
            return null;
        }

        return $akun;
    }

    private function terapkan(array $akun, string $mode): ?User
    {
        try {
            $this->receiver->apply([
                'mode' => $mode,
                'email' => $akun['email'],
                'previous_email' => null,
                'name' => $akun['name'] ?? null,
                'phone' => $akun['phone'] ?? null,
                'password_hash' => $akun['password_hash'],
                'email_verified_at' => $akun['email_verified_at'] ?? null,
            ]);
        } catch (LinkedAccountConflict $e) {
            Log::error('Akun tertaut: akun dari pesanvo bentrok saat dijemput.', ['pesan' => $e->getMessage()]);

            return null;
        }

        return User::where('email', $akun['email'])->first();
    }
}
