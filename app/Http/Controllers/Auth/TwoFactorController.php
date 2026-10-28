<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Autentikasi dua faktor berbasis aplikasi authenticator (TOTP).
 *
 * Wajib untuk super admin, opsional untuk pelanggan — alasannya di
 * `User::wajibDuaFaktor()`.
 *
 * ## Kunci sesi yang dipakai, dan kenapa masing-masing ada
 *
 * | Kunci | Artinya |
 * |---|---|
 * | `2fa.lolos` | Tantangan sudah dilewati pada sesi ini |
 * | `2fa.rahasia` | Rahasia yang sedang dipasang, belum dikonfirmasi |
 *
 * `2fa.rahasia` ditaruh di sesi dan **belum** ditulis ke kolom pengguna sampai
 * kode pertamanya cocok. Kalau ia langsung disimpan, orang yang membuka halaman
 * pemasangan lalu menutup tabnya akan punya rahasia yang tidak ada di aplikasi
 * mana pun — dan sejak itu diminta kode yang tidak bisa ia dapatkan dari mana
 * pun. Terkunci di luar akunnya sendiri oleh fitur yang belum sempat dinyalakan.
 */
class TwoFactorController extends Controller
{
    private const JUMLAH_KODE_PEMULIHAN = 8;

    /** Halaman pemasangan: QR untuk dipindai, lalu satu kode untuk membuktikan. */
    public function pasang(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->duaFaktorAktif()) {
            return redirect()->route('profile.show');
        }

        // Rahasia yang sama dipertahankan selama sesi berjalan. Menerbitkan
        // rahasia baru tiap kali halaman dimuat berarti QR yang sudah dipindai
        // menjadi tidak berlaku begitu pengguna menekan tombol muat ulang —
        // dan kodenya tidak akan pernah cocok tanpa penjelasan apa pun.
        $rahasia = $request->session()->get('2fa.rahasia') ?: Totp::rahasiaBaru();
        $request->session()->put('2fa.rahasia', $rahasia);

        return view('auth.two-factor-setup', [
            'rahasia' => $rahasia,
            'uri' => Totp::uri($rahasia, $user->email),
            'wajib' => $user->wajibDuaFaktor(),
        ]);
    }

    public function nyalakan(Request $request): RedirectResponse
    {
        $request->validate(['kode' => ['required', 'string']], [], ['kode' => 'kode']);

        $user = $request->user();
        $rahasia = $request->session()->get('2fa.rahasia');

        if (blank($rahasia)) {
            return redirect()->route('two-factor.setup');
        }

        if (! Totp::sah($rahasia, $request->input('kode'))) {
            return back()->withErrors([
                'kode' => 'Kode tidak cocok. Pastikan jam di ponsel Anda otomatis — '
                    .'jam yang meleset lebih dari satu menit membuat setiap kode ditolak.',
            ]);
        }

        $pemulihan = self::kodePemulihanBaru();

        $user->forceFill([
            'two_factor_secret' => $rahasia,
            'two_factor_recovery_codes' => $pemulihan,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('2fa.rahasia');
        $request->session()->put('2fa.lolos', true);

        AuditLog::record('user.2fa.enabled', $user);

        // Kode pemulihan ditampilkan SEKALI, lewat flash, bukan disimpan di
        // halaman yang bisa dibuka lagi. Kalau bisa dibuka kapan saja, ia
        // berhenti menjadi faktor kedua: siapa pun yang memegang sesi peramban
        // yang tertinggal terbuka bisa membacanya.
        return redirect()->route('profile.show')->with('kodePemulihan', $pemulihan);
    }

    /** Unduh seluruh kode pemulihan yang tersisa dalam format CSV. */
    public function unduhKodePemulihanCsv(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->duaFaktorAktif(), 403, '2FA belum aktif.');

        $codes = $user->two_factor_recovery_codes ?? [];
        $csv = "No,Kode Pemulihan\r\n";
        foreach ($codes as $idx => $code) {
            $csv .= ($idx + 1).",{$code}\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="pesanvo-kode-pemulihan-2fa.csv"',
        ]);
    }

    /** Menampilkan kembali kode pemulihan ke layar dengan konfirmasi kata sandi. */
    public function tampilkanKodePemulihan(Request $request): RedirectResponse
    {
        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'Kata sandi tidak cocok.'],
        );

        $user = $request->user();

        abort_unless($user->duaFaktorAktif(), 403, '2FA belum aktif.');

        return back()->with('kodePemulihan', $user->two_factor_recovery_codes ?? []);
    }

    /** Menerbitkan 8 kode pemulihan baru dan membuang yang lama. */
    public function buatUlangKodePemulihan(Request $request): RedirectResponse
    {
        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'Kata sandi tidak cocok. Kode pemulihan tidak diubah.'],
        );

        $user = $request->user();

        abort_unless($user->duaFaktorAktif(), 403, '2FA belum aktif.');

        $pemulihanBaru = self::kodePemulihanBaru();

        $user->forceFill([
            'two_factor_recovery_codes' => $pemulihanBaru,
        ])->save();

        AuditLog::record('user.2fa.recovery_regenerated', $user);

        return back()->with('kodePemulihan', $pemulihanBaru)->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kode pemulihan diperbarui',
            'pesan' => '8 kode pemulihan baru telah diterbitkan. Kode lama sudah tidak berlaku lagi.',
        ]);
    }

    /** Layar tantangan setelah kata sandi benar. */
    public function tantangan(Request $request): View|RedirectResponse
    {
        if (! $request->user()->duaFaktorAktif() || $request->session()->get('2fa.lolos')) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifikasi(Request $request): RedirectResponse
    {
        $request->validate(['kode' => ['required', 'string']], [], ['kode' => 'kode']);

        $user = $request->user();
        $kode = trim($request->input('kode'));

        if (Totp::sah((string) $user->two_factor_secret, $kode)) {
            $request->session()->put('2fa.lolos', true);

            return redirect()->intended(route('dashboard'));
        }

        if ($this->pakaiKodePemulihan($user, $kode)) {
            $request->session()->put('2fa.lolos', true);

            AuditLog::record('user.2fa.recovery_used', $user, [
                'sisa' => count($user->two_factor_recovery_codes ?? []),
            ]);

            return redirect()->route('profile.show')->with('swal', [
                'tipe' => 'warning',
                'judul' => 'Kode pemulihan terpakai',
                'pesan' => 'Kode itu sudah tidak berlaku lagi. Sisa '
                    .count($user->two_factor_recovery_codes ?? []).' kode. '
                    .'Kalau Anda kehilangan akses ke aplikasi authenticator, matikan lalu pasang ulang 2FA sekarang.',
            ]);
        }

        return back()->withErrors(['kode' => 'Kode tidak cocok.']);
    }

    public function matikan(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Kata sandi diminta lagi untuk keamanan. Tanpa ini, sesi peramban yang
        // tertinggal terbuka bisa mematikan faktor kedua dalam satu klik.
        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'Kata sandi tidak cocok. 2FA tetap aktif.'],
        );

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('2fa.lolos');

        AuditLog::record('user.2fa.disabled', $user);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => '2FA Berhasil Dilepas',
            'pesan' => 'Autentikasi dua faktor telah dinonaktifkan. Akun Anda sekarang dilindungi dengan kata sandi.',
        ]);
    }

    /** @return array<int, string> */
    private static function kodePemulihanBaru(): array
    {
        return collect(range(1, self::JUMLAH_KODE_PEMULIHAN))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Memakai satu kode pemulihan, lalu membuangnya.
     *
     * Dibuang, bukan ditandai terpakai: kode sekali pakai yang barisnya tetap
     * ada adalah kode yang suatu saat lolos lagi karena satu pemeriksaan
     * terlewat saat kode dirapikan.
     */
    private function pakaiKodePemulihan($user, string $kode): bool
    {
        $tersisa = $user->two_factor_recovery_codes ?? [];

        foreach ($tersisa as $i => $tersimpan) {
            if (hash_equals($tersimpan, Str::lower($kode))) {
                unset($tersisa[$i]);

                $user->forceFill(['two_factor_recovery_codes' => array_values($tersisa)])->save();

                return true;
            }
        }

        return false;
    }
}
