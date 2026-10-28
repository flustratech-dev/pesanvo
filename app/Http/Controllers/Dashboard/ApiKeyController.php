<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.api-keys.index', [
            'keys' => $workspace->apiKeys()->latest()->get(),

            // Hanya owner dan admin yang boleh melihat nilai penuh kunci.
            // Anggota biasa cukup tahu kunci mana yang ada dan kapan terakhir
            // dipakai — memberi mereka nilainya sama saja memberi kemampuan
            // mengirim WhatsApp atas nama workspace dari mana pun, di luar
            // pengawasan dashboard.
            'bolehLihatKunci' => $request->user()->canManage($workspace),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:40'],
        ]);

        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $workspace->canAddApiKey()) {
            return back()->withErrors([
                'name' => 'Paket '.$workspace->plan()->name().' hanya mengizinkan '
                    .$workspace->plan()->maxApiKeys().' API key aktif. Cabut salah satu, atau naikkan paket.',
            ]);
        }

        // `?? []` bukan hiasan: checkbox yang tidak dicentang tidak dikirim sama
        // sekali, jadi kunci 'scopes' bisa tidak ada di hasil validasi. Tanpa
        // ini, membuat kunci sambil melepas centang "Semua" berakhir dengan
        // galat, bukan dengan scope bawaan.
        [$key] = ApiKey::issue(
            $workspace,
            $data['name'],
            ($data['scopes'] ?? []) ?: ['*'],
            $request->user()->id,
        );

        AuditLog::record('api_key.created', $key, ['name' => $key->name]);

        // Nilai kuncinya tidak perlu dititipkan lewat flash session lagi — ia
        // tersimpan terenkripsi dan bisa dibuka kapan saja dari halaman ini.
        // Yang dititipkan cuma id-nya, supaya kunci yang baru dibuat langsung
        // terbuka dan tidak tenggelam di antara kunci lama.
        return back()
            ->with('kunci_baru_id', $key->id)
            ->with('status', "API key '{$key->name}' dibuat.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403, 'Hanya owner atau admin yang bisa mencabut API key.');
        }

        $key = $workspace->apiKeys()->findOrFail($id);

        // Dicabut, bukan dihapus: baris tetap ada supaya jejak "kunci ini pernah
        // dipakai sampai tanggal sekian" tidak ikut hilang.
        //
        // Nilai terenkripsinya dibuang sekalian. Kunci yang sudah dicabut tidak
        // pernah berguna lagi, jadi menyimpannya hanya menambah hal yang bisa
        // bocor tanpa menambah kegunaan apa pun.
        $key->update(['revoked_at' => now(), 'key_ciphertext' => null]);

        AuditLog::record('api_key.revoked', $key, ['name' => $key->name]);

        return back()->with('status', "API key '{$key->name}' dicabut.");
    }
}
