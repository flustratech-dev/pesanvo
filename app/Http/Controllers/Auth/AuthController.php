<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingExemption;
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LinkedAccounts\LinkedAccountLookup;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi milik gateway ini sendiri: tabel `users` dan alur login/register
 * sendiri, tanpa bergantung pada pintu masuk aplikasi lain.
 *
 * Bentuknya sengaja dijaga sederhana: cukup untuk dipakai dan diuji sekarang,
 * tanpa memutuskan lebih dulu hal-hal yang belum perlu diputuskan (verifikasi
 * email, dua faktor, masuk lewat penyedia identitas pihak ketiga).
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Akun tertaut: email dan kata sandi yang benar di pesanvo tapi belum
            // (atau belum terbaru) di sini dijemput dulu, bukan disuruh mendaftar
            // ulang. Hanya hash yang diminta dari seberang; kata sandinya dicocokkan
            // di sini.
            $dijemput = app(LinkedAccountLookup::class)->masukDenganSandi($credentials['email'], $credentials['password']);

            if (! $dijemput || ! Auth::attempt($credentials, $request->boolean('remember'))) {
                // Pesan yang sama untuk email tidak dikenal maupun password salah,
                // supaya form ini tidak bisa dipakai menebak email mana yang terdaftar.
                throw ValidationException::withMessages([
                    'email' => 'Email atau kata sandi tidak cocok.',
                ]);
            }
        }

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request): View
    {
        if ($request->filled('ref')) {
            $kode = ReferralCode::normalkan((string) $request->query('ref'));
            if (ReferralCode::where('code', $kode)->where('is_active', true)->exists()) {
                session(['referral_code' => $kode]);
            }
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'workspace' => ['required', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // Formulir menandai centang ini wajib, tapi atribut `required` di
            // HTML hanya berlaku di browser. Tanpa aturan di sini, pendaftaran
            // lewat curl bisa melewatinya — padahal ini persetujuan yang
            // seharusnya benar-benar tercatat.
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Anda harus menyetujui Syarat & Ketentuan serta Kebijakan Privasi.',
        ], [
            'workspace' => 'nama workspace',
        ]);

        // Pembebasan yang menunggu diterapkan SEBELUM workspace dibuat: batas
        // dan status langganan workspace baru ikut membaca `is_exempt` lewat
        // `Workspace::isExempt()`, dan yang dibebaskan setelahnya sempat lahir
        // sebagai pelanggan biasa lebih dulu.
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'google_id' => $request->input('google_id') ?: null,
            'avatar' => $request->input('google_avatar') ?: null,
            'email_verified_at' => $request->filled('google_id') ? now() : null,
        ]);

        // Workspace dibuat sekalian saat mendaftar. Tanpa ini pengguna baru
        // mendarat di dashboard yang belum bisa dipakai apa-apa dan harus
        // melewati satu langkah lagi sebelum melihat hasil apa pun.
        $workspace = Workspace::create([
            'name' => $data['workspace'],
            'slug' => Workspace::uniqueSlug($data['workspace']),
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => config('gateway.defaults.max_sessions'),
            'monthly_message_quota' => config('gateway.defaults.monthly_message_quota'),
            'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
        ]);

        PendingExemption::terapkanUntuk($user);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('sessions.index')
            ->with('status', 'Workspace dibuat. Langkah berikutnya: buat sesi lalu scan QR-nya.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
