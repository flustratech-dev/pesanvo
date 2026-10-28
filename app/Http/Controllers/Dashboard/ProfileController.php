<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PenghapusanAkun;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Pengaturan profil, foto profil, dan kata sandi akun pengguna.
 */
class ProfileController extends Controller
{
    public function show(Request $request, PenghapusanAkun $penghapusan): View
    {
        return view('dashboard.profile', [
            'user' => $request->user(),

            // Dihitung di muka, bukan saat tombolnya ditekan. Penghapusan
            // permanen yang tidak menyebutkan apa saja yang ikut hilang adalah
            // persetujuan yang tidak diberikan dengan sadar — terutama saat yang
            // ikut hilang adalah workspace berisi setahun riwayat percakapan.
            'dampak' => $penghapusan->ringkasDampak($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 80 karakter.',
            'avatar.image' => 'Berkas foto profil harus berupa gambar.',
            'avatar.mimes' => 'Format foto profil harus JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto profil maksimal 2 MB.',
        ]);

        $user = $request->user();
        $fieldsToUpdate = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'bio' => $data['bio'] ?? null,
        ];

        if ($request->hasFile('avatar')) {
            // Hapus avatar lama jika file lokal
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }
            $fieldsToUpdate['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($fieldsToUpdate);

        AuditLog::record('user.profile.updated', $user, [
            'name' => $user->name,
            'phone' => $user->phone,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Profil Diperbarui',
            'pesan' => 'Data profil dan foto akun Anda berhasil disimpan.',
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        AuditLog::record('user.password.updated', $user);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kata Sandi Diubah',
            'pesan' => 'Kata sandi akun Anda berhasil diperbarui.',
        ]);
    }
}
