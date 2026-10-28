<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));

        return view('admin.users', [
            'users' => User::query()
                ->withCount('workspaces')
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('name', 'like', "%{$cari}%")
                        ->orWhere('email', 'like', "%{$cari}%");
                }))
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'cari' => $cari,
        ]);
    }

    /**
     * Mengatur ulang kata sandi seorang pengguna.
     *
     * Ini satu-satunya jalan kembali bagi pelanggan yang terkunci: pemulihan
     * mandiri sengaja belum dibuat karena email belum benar-benar terkirim,
     * dan halaman masuk mengarahkan mereka menghubungi kami. Tanpa tombol ini,
     * "hubungi admin" berujung buntu yang sama.
     *
     * Kata sandinya dibuat sistem, bukan diketik admin. Admin yang mengarang
     * kata sandi cenderung memakai pola yang sama berulang kali, dan pola itu
     * menyebar ke banyak akun sekaligus. Nilainya ditampilkan sekali di layar
     * untuk disampaikan lewat jalur yang sudah dipakai berbicara dengan
     * pelanggannya — kami tidak menyimpannya di mana pun.
     */
    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $sandi = Str::password(14, symbols: false);

        $user->forceFill([
            'password' => Hash::make($sandi),
            // Sesi "ingat saya" lama harus mati bersama kata sandi lamanya —
            // kalau tidak, peramban yang jadi alasan kata sandi ini diganti
            // tetap bisa masuk tanpa mengetik apa pun.
            'remember_token' => Str::random(60),
        ])->save();

        AuditLog::record('admin.password_reset', $user, [
            'email' => $user->email,
            'oleh' => $request->user()->email,
        ]);

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Kata sandi diatur ulang',
            'html' => 'Kata sandi baru untuk <strong>'.e($user->email).'</strong>:<br>'
                .'<code style="font-size:1.15rem;letter-spacing:.05em">'.e($sandi).'</code><br><br>'
                .'Salin sekarang — nilai ini tidak disimpan dan tidak bisa dilihat lagi. '
                .'Sampaikan lewat jalur yang sudah Anda pakai berbicara dengan mereka, '
                .'dan minta mereka menggantinya setelah masuk.',
            'confirmButtonText' => 'Sudah saya salin',
        ]);
    }

    /**
     * Memberi atau mencabut hak super admin.
     *
     * Mencabut hak diri sendiri ditolak. Bukan karena tidak boleh ada yang
     * mundur, tapi karena satu-satunya jalan masuk ke panel ini adalah lewat
     * super admin — orang terakhir yang mencabut haknya sendiri mengunci pintu
     * dari luar, dan memperbaikinya hanya bisa lewat akses langsung ke
     * database produksi.
     */
    public function toggleSuperAdmin(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'pengguna' => 'Hak super admin diri sendiri tidak bisa dicabut dari sini. Minta super admin lain yang melakukannya.',
            ]);
        }

        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();

        AuditLog::record('admin.super_admin_toggled', $user, [
            'email' => $user->email,
            'menjadi' => $user->is_super_admin,
            'oleh' => $request->user()->email,
        ]);

        return back()->with('status', $user->is_super_admin
            ? "{$user->email} sekarang super admin."
            : "Hak super admin {$user->email} dicabut.");
    }
}
