<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\LinkedAccounts\LinkedAccountConflict;
use App\Services\LinkedAccounts\LinkedAccountReceiver;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Menerima perubahan akun dari aplikasi pesanvo. Tanda tangannya sudah
 * diperiksa middleware sebelum sampai di sini.
 */
class LinkedAccountController extends Controller
{
    public function __invoke(Request $request, LinkedAccountReceiver $penerima): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in([LinkedAccountSync::MODE_SYNC, LinkedAccountSync::MODE_LINK])],
            'email' => ['required', 'email', 'max:180'],
            'previous_email' => ['nullable', 'email', 'max:180'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password_hash' => ['required', 'string', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
        ]);

        // Yang boleh masuk hanya hash yang dikenali. Kata sandi polos yang
        // terkirim karena salah kode di seberang akan tersimpan apa adanya
        // dan — lebih buruk — tidak pernah cocok saat dipakai masuk.
        if (Hash::info($data['password_hash'])['algoName'] === 'unknown') {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'password_hash bukan hash kata sandi yang dikenali.'],
            ], 422);
        }

        try {
            $tindakan = $penerima->apply($data);
        } catch (LinkedAccountConflict $e) {
            Log::error('Akun tertaut: perubahan dari pesanvo bentrok.', ['pesan' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()],
            ], 409);
        }

        return response()->json(['success' => true, 'action' => $tindakan]);
    }
}
