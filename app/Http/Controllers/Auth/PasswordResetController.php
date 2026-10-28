<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Pemulihan kata sandi mandiri lewat email.
 *
 * Token berlaku 60 menit dan disimpan terenkripsi di `password_reset_tokens`.
 * Respon pengiriman sengaja tidak membedakan email terdaftar atau tidak
 * demi mencegah enumeration akun.
 */
class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $email,
            ]);

            try {
                Mail::to($user->email)->send(new ResetPasswordMail($user, $token, $resetUrl));
            } catch (\Throwable $e) {
                // Jangan biarkan kegagalan koneksi SMTP membocorkan error teknis
            }

            AuditLog::record('auth.password.reset_requested', $user, [
                'email' => $email,
            ]);
        }

        return back()->with('status', 'Jika alamat email terdaftar, tautan pemulihan kata sandi telah dikirimkan ke kotak masuk Anda.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record) {
            return back()->withErrors(['email' => 'Permintaan atur ulang kata sandi tidak ditemukan atau sudah digunakan.'])->withInput();
        }

        // Cek kedaluwarsa 60 menit
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return back()->withErrors(['email' => 'Tautan atur ulang kata sandi sudah kedaluwarsa (berlaku 60 menit). Silakan ajukan kembali.'])->withInput();
        }

        // Cek token hash
        if (! Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['email' => 'Token atur ulang kata sandi tidak valid.'])->withInput();
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Pengguna dengan email ini tidak ditemukan.'])->withInput();
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        AuditLog::record('auth.password.reset_completed', $user, [
            'email' => $email,
        ]);

        return redirect()->route('login')->with('status', 'Kata sandi Anda berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }
}
