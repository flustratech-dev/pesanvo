<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OtpRateLimited;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kirim dan cocokkan kode verifikasi lewat WhatsApp.
 *
 * Kodenya dikirim dari nomor workspace pemanggil dan memakai namanya, jadi
 * penerima melihat merek yang memang mereka kenal. Butuh scope `otp` — lihat
 * peringatan di halaman API Keys soal kenapa scope itu tidak diberikan ke
 * kunci integrasi biasa.
 */
class OtpController extends ApiController
{
    public function __construct(private readonly OtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $otp = $this->otp->send(
                $data['phone'],
                $data['purpose'] ?? 'phone_verification',
                $request->ip(),
                $this->workspace($request),
            );
        } catch (OtpRateLimited $e) {
            return $this->fail($e->getMessage(), 429);
        } catch (\RuntimeException $e) {
            // Bukan soal terlalu sering, melainkan permintaan yang memang belum
            // bisa dilayani — nomor tidak valid, atau workspace belum punya
            // nomor terhubung. 429 di sini menyuruh pemanggil menunggu untuk
            // keadaan yang tidak akan berubah hanya dengan menunggu.
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'expires_at' => $otp->expires_at->toIso8601String(),
            'resend_after_seconds' => config('gateway.otp.resend_cooldown_seconds'),
        ], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $verified = $this->otp->verify(
            $data['phone'],
            $data['purpose'] ?? 'phone_verification',
            $data['code'],
        );

        if (! $verified) {
            return $this->fail('Kode salah atau sudah kedaluwarsa.', 422);
        }

        return $this->ok(['verified' => true]);
    }
}
