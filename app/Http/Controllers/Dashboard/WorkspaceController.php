<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SessionService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function settings(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.settings', [
            'members' => $workspace->members()->orderBy('name')->get(),
            'usage' => $workspace->currentUsage(),
            'history' => $workspace->usageCounters()->orderByDesc('period')->limit(12)->get(),
            'ekspor' => DataExport::where('workspace_id', $workspace->id)
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function switch(Request $request, int $id): RedirectResponse
    {
        // Hanya workspace yang benar-benar diikuti user yang boleh dipilih —
        // tanpa cek ini, mengganti id di URL sama saja membuka data orang lain.
        $workspace = $request->user()->workspaces()->findOrFail($id);

        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }

    public function createForm(Request $request): View
    {
        // Halaman ini dipakai dua kali dengan arti berbeda: sambutan bagi
        // pengguna baru yang belum punya workspace, dan penambahan workspace
        // kedua bagi yang sudah lama memakai. Menyapa "selamat datang" pada
        // pemakaian kedua membuat orang ragu apakah mereka salah halaman.
        return view('dashboard.onboarding', [
            'workspacePertama' => $request->user()->workspaces()->doesntExist(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $user = $request->user();

        $workspace = Workspace::create([
            'name' => $data['name'],
            'slug' => Workspace::uniqueSlug($data['name']),
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => config('gateway.defaults.max_sessions'),
            'monthly_message_quota' => config('gateway.defaults.monthly_message_quota'),
            'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        session(['current_workspace_id' => $workspace->id]);

        AuditLog::record('workspace.created', $workspace, ['name' => $workspace->name], $workspace->id);

        return redirect()->route('sessions.index')
            ->with('status', 'Workspace dibuat. Langkah berikutnya: buat sesi dan scan QR.');
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403, 'Hanya owner atau admin yang bisa mengubah workspace.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'billing_phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Nomor tagihan dinormalkan seperti nomor tujuan pesan mana pun. Kalau
        // tidak, pengingat yang dikirim ke `08...` gagal diam-diam — dan
        // gagalnya baru ketahuan sebagai pelanggan yang tidak pernah tahu
        // langganannya habis.
        $nomor = filled($data['billing_phone'] ?? null)
            ? PhoneNumber::normalize($data['billing_phone'])
            : null;

        if (filled($data['billing_phone'] ?? null) && $nomor === null) {
            return back()->withErrors(['billing_phone' => 'Nomor WhatsApp tidak valid.']);
        }

        // Slug sengaja tidak ikut berubah. Ia dipakai sebagai pengenal yang
        // tahan lama di log dan audit; nama boleh berubah kapan saja, slug
        // tidak. Tidak ada satu pun URL atau API key yang bergantung padanya.
        $workspace->update([
            'name' => $data['name'],
            'billing_phone' => $nomor,
        ]);

        AuditLog::record('workspace.renamed', $workspace, ['name' => $data['name']], $workspace->id);

        return back()->with('status', 'Pengaturan workspace diperbarui.');
    }

    /**
     * Menghapus workspace beserta seluruh isinya.
     *
     * Diminta mengetik ulang namanya, bukan sekadar mengklik "yakin?": yang
     * hilang di sini bukan cuma satu baris, tapi seluruh riwayat pesan, API
     * key, dan tautan nomor — dan aplikasi yang memegang API key-nya akan
     * berhenti bisa mengirim tanpa pesan galat yang menjelaskan kenapa.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);
        $user = $request->user();

        if (! $user->is_super_admin && $user->roleIn($workspace) !== 'owner') {
            abort(403, 'Hanya owner yang bisa menghapus workspace.');
        }

        $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if ($request->input('confirm') !== $workspace->name) {
            return back()->withErrors([
                'confirm' => 'Nama yang diketik tidak cocok. Workspace tidak jadi dihapus.',
            ]);
        }

        // Sesi dihentikan lebih dulu supaya koneksi socket dan memorinya benar-benar dilepas di
        // engine. Tanpa ini, engine tetap memegang koneksi ke nomor yang
        // workspace-nya sudah tidak ada — memakan memori sampai engine
        // di-restart, dan pesan masuk tetap ditembakkan ke Laravel.
        foreach ($workspace->sessions as $session) {
            try {
                $this->sessions->logout($session);
            } catch (\Throwable) {
                // Engine tidak terjangkau. Penghapusan tetap diteruskan —
                // membiarkan workspace hidup hanya karena engine sedang
                // bermasalah justru membuat pengguna terjebak.
            }
        }

        $nama = $workspace->name;

        AuditLog::record('workspace.deleted', $workspace, ['name' => $nama], $workspace->id);

        $workspace->delete();
        session()->forget('current_workspace_id');

        return redirect()->route('dashboard')->with('status', "Workspace '{$nama}' dihapus.");
    }

    public function addMember(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403, 'Hanya owner atau admin yang bisa menambah anggota.');
        }

        if (! $workspace->canAddMember()) {
            return back()->withErrors([
                'email' => 'Paket '.$workspace->plan()->name().' hanya mengizinkan '
                    .$workspace->plan()->maxMembers().' anggota. Keluarkan salah satu, atau naikkan paket.',
            ]);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        // Undangan tidak membuat akun baru: yang diundang harus sudah pernah
        // login sendiri. Membuatkan akun berarti menetapkan kata sandi untuk
        // orang lain, dan alamat email yang salah ketik akan menghasilkan akun
        // hantu yang tidak pernah bisa diklaim siapa pun.
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'Belum ada pengguna dengan email ini. Minta mereka mendaftar dan login ke '
                    .config('app.url').' sekali dulu, baru undang lagi.',
            ]);
        }

        $workspace->members()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

        AuditLog::record('workspace.member_added', $user, ['email' => $user->email], $workspace->id);

        return back()->with('status', "{$user->name} ditambahkan sebagai {$data['role']}.");
    }

    public function removeMember(Request $request, int $userId): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403);
        }

        $role = $workspace->members()->where('users.id', $userId)->first()?->pivot->role;

        if ($role === 'owner') {
            return back()->withErrors(['member' => 'Owner tidak bisa dikeluarkan dari workspace-nya sendiri.']);
        }

        $workspace->members()->detach($userId);

        return back()->with('status', 'Anggota dikeluarkan.');
    }
}
