<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Arahkan pengguna ke halaman OAuth Google.
     */
    public function redirect(): RedirectResponse
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google OAuth belum dikonfigurasi. Pastikan GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET sudah diatur di file .env.',
            ]);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Tangani panggilan balik (callback) dari Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google OAuth belum dikonfigurasi.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (Throwable $e2) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Proses autentikasi dengan akun Google dibatalkan atau gagal. Silakan coba lagi.',
                ]);
            }
        }

        $email = mb_strtolower(trim($googleUser->getEmail() ?? ''));
        $googleId = (string) $googleUser->getId();
        $name = $googleUser->getName() ?: Str::before($email, '@');
        $avatar = $googleUser->getAvatar();

        if (empty($email)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Google Anda tidak membagikan alamat email. Tidak dapat melanjutkan autentikasi.',
            ]);
        }

        // Cari user yang sudah terdaftar via google_id atau email
        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        // Akun tertaut: email yang sudah punya akun di pesanvo tidak disuruh
        // mendaftar ulang di sini. Google sudah membuktikan emailnya milik orang ini.
        $user ??= app(LinkedAccountLookup::class)->masukDenganGoogle($email);

        // Jika akun belum terdaftar, arahkan ke form pendaftaran dengan membawa data dari Google
        if (! $user) {
            return redirect()->route('register')
                ->with('google_email', $email)
                ->with('google_name', $name)
                ->with('google_id', $googleId)
                ->with('google_avatar', $avatar)
                ->with('info', 'Akun Google Anda belum terdaftar. Silakan lengkapi nama workspace dan kata sandi untuk menyelesaikan pendaftaran.');
        }

        // Tautkan google_id / avatar ke user yang sudah ada bila sebelumnya belum tertaut
        $updates = [];
        if (! $user->google_id) {
            $updates['google_id'] = $googleId;
        }
        if (! $user->avatar && $avatar) {
            $updates['avatar'] = $avatar;
        }
        $updates['last_login_at'] = now();
        $user->forceFill($updates)->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        $firstWorkspace = $user->workspaces()->first();
        if ($firstWorkspace) {
            session(['current_workspace_id' => $firstWorkspace->id]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
