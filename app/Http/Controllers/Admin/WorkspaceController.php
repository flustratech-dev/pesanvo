<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = (string) $request->query('status', '');

        $workspaces = Workspace::query()
            ->with(['subscription', 'owner'])
            ->withCount('sessions')
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('name', 'like', "%{$cari}%")
                    ->orWhere('slug', 'like', "%{$cari}%")
                    ->orWhere('owner_email', 'like', "%{$cari}%");
            }))
            ->when($status !== '', fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('status', $status)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Pemakaian diambil sekali untuk seluruh halaman, bukan lewat relasi
        // per baris: dua puluh lima workspace berarti dua puluh lima kueri
        // tambahan pada tabel yang tumbuh paling cepat di sistem ini.
        $pemakaian = DB::table('usage_counters')
            ->where('period', now()->format('Y-m'))
            ->whereIn('workspace_id', $workspaces->pluck('id'))
            ->pluck('messages_sent', 'workspace_id');

        return view('admin.workspaces', [
            'workspaces' => $workspaces,
            'pemakaian' => $pemakaian,
            'plans' => Plan::all(),
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    /**
     * Halaman satu workspace.
     *
     * Seluruh pengelolaan pindah ke sini, keluar dari daftar. Sebelumnya tiap
     * baris di daftar bisa dibuka menjadi panel berisi empat form sekaligus —
     * daftar yang berubah bentuk saat disentuh sulit dipindai, dan form yang
     * bersembunyi di dalam baris membuat orang tidak yakin sedang mengubah
     * workspace yang mana. Daftar sekarang hanya untuk mencari; halaman ini
     * untuk bertindak.
     */
    public function show(Request $request, int $id): View
    {
        $workspace = Workspace::with(['owner', 'members', 'sessions'])->findOrFail($id);

        $subscription = $this->subscriptions->ensureFor($workspace);

        return view('admin.workspace-detail', [
            'workspace' => $workspace,
            'subscription' => $subscription,
            'plans' => Plan::all(),
            'invoices' => $workspace->invoices()->latest()->limit(10)->get(),

            // Dua belas bulan terakhir, supaya pola pemakaian terlihat — bukan
            // cuma angka bulan berjalan yang tidak bisa dibandingkan dengan apa pun.
            'pemakaian' => $workspace->usageCounters()
                ->orderByDesc('period')
                ->limit(12)
                ->get(),

            'audit' => AuditLog::with('user:id,email')
                ->where('workspace_id', $workspace->id)
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /**
     * Memindahkan workspace ke paket lain tanpa tagihan.
     *
     * Dipakai untuk memperbaiki kesalahan dan untuk kesepakatan di luar
     * halaman harga. Batas paket langsung berlaku, dan periodenya tidak
     * disentuh — memindahkan paket bukan memperpanjang langganan.
     *
     * Karena periodenya tidak disentuh, tombol ini hanya aman untuk workspace
     * yang MEMANG punya periode. Dulu ia menerima workspace coba gratis juga,
     * dan hasilnya paket berbayar berstatus `trialing` tanpa tanggal berakhir —
     * alias gratis selamanya tanpa ada yang memutuskannya. Workspace seperti
     * itu diarahkan ke "Aktifkan tanpa pembayaran".
     */
    public function changePlan(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in($this->paketBoleh())],
        ]);

        $workspace = Workspace::findOrFail($id);
        $subscription = $this->subscriptions->ensureFor($workspace);

        if ($alasan = $this->alasanTanpaPeriode($workspace)) {
            return back()->withErrors(['plan' => $alasan]);
        }

        $subscription->forceFill(['plan_slug' => $data['plan']])->save();

        $this->subscriptions->applyPlanLimits($workspace, Plan::get($data['plan']));

        AuditLog::record('admin.plan_changed', $workspace, [
            'plan' => $data['plan'],
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "Paket {$workspace->name} diubah ke ".Plan::get($data['plan'])->name().'.');
    }

    /**
     * Menambah hari pada periode yang sudah ada.
     *
     * Dijaga dengan aturan yang sama seperti `changePlan()`: memperpanjang
     * workspace coba gratis memberinya tanggal berakhir tanpa paket berbayar
     * (tetap lima pesan, lalu ditandai menunggak saat tanggalnya lewat), dan
     * memperpanjang workspace PAYG memberinya tanggal berakhir yang mematikan
     * layanan yang seharusnya hanya dibatasi saldo.
     */
    public function extend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'hari' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $workspace = Workspace::findOrFail($id);
        $subscription = $this->subscriptions->ensureFor($workspace);

        if ($alasan = $this->alasanTanpaPeriode($workspace)) {
            return back()->withErrors(['hari' => $alasan]);
        }

        $this->subscriptions->extend($subscription, $data['hari'], $request->user());

        return back()->with('status', "Langganan {$workspace->name} diperpanjang {$data['hari']} hari.");
    }

    /**
     * Mengaktifkan — atau memperpanjang — paket berbayar tanpa pembayaran
     * (rekanan). Hasilnya sama persis dengan langganan yang dibayar: status
     * aktif, tanggal berakhir, batas paket. Bedanya cuma tidak ada tagihan,
     * dan tagihan perpanjangan tidak terbit otomatis.
     */
    public function grantPartner(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in($this->paketBoleh())],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['plan' => 'paket', 'period' => 'durasi']);

        $workspace = Workspace::findOrFail($id);
        $perpanjang = $workspace->isPartner();

        $subscription = $this->subscriptions->grantPartner(
            $workspace, $data['plan'], $data['period'], $request->user(), $data['note'] ?? null,
        );

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => $perpanjang ? 'Rekanan diperpanjang' : 'Paket diaktifkan tanpa pembayaran',
            'pesan' => "{$workspace->name} memakai paket {$subscription->plan()->name()} sampai "
                .$subscription->current_period_end->translatedFormat('j F Y').'. Tidak ada tagihan yang dibuat.',
        ]);
    }

    public function revokePartner(Request $request, int $id): RedirectResponse
    {
        $workspace = Workspace::whereNotNull('partner_since')->findOrFail($id);

        $this->subscriptions->revokePartner($workspace, $request->user());

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Status rekanan dicabut',
            'pesan' => "{$workspace->name} kembali ke paket coba gratis. Untuk mengirim lagi, pemiliknya perlu membeli paket.",
        ]);
    }

    /**
     * Paket yang boleh dipilih admin: yang dijual per bulan saja. Coba gratis,
     * PAYG, dan Enterprise punya jalurnya sendiri — memindahkan ke sana lewat
     * sekadar mengganti slug menghasilkan keadaan setengah jadi (PAYG tanpa
     * `billing_mode`, Enterprise tanpa kesepakatan).
     *
     * @return array<int, string>
     */
    private function paketBoleh(): array
    {
        return array_map(fn (Plan $plan) => $plan->slug, Plan::all());
    }

    /** Kenapa workspace ini tidak punya periode yang bisa diubah, atau `null`. */
    private function alasanTanpaPeriode(Workspace $workspace): ?string
    {
        if ($workspace->isPayg()) {
            return 'Workspace ini memakai PAYG (dibatasi saldo, bukan tanggal). Pakai "Aktifkan tanpa pembayaran" untuk memindahkannya ke paket bulanan.';
        }

        if ($workspace->isFreeTier() || $workspace->subscription?->current_period_end === null) {
            return 'Workspace ini belum punya masa berlangganan. Pakai "Aktifkan tanpa pembayaran" supaya paketnya punya tanggal berakhir.';
        }

        return null;
    }

    /**
     * Menangguhkan atau memulihkan langganan dengan tangan.
     *
     * Memulihkan hanya mengembalikan status dan mengaktifkan workspace; sesi
     * yang sudah terputus tidak dinyalakan otomatis dari sini. Menyalakan
     * beberapa sesi engine sekaligus dari satu klik berisiko lonjakan beban —
     * pemiliknya yang menekan Hubungkan.
     */
    public function toggleSuspend(Request $request, int $id): RedirectResponse
    {
        $workspace = Workspace::findOrFail($id);
        $subscription = $this->subscriptions->ensureFor($workspace);

        if ($subscription->status === 'suspended' || $subscription->status === 'past_due') {
            $subscription->forceFill([
                'status' => 'active',
                'past_due_at' => null,
                'suspended_at' => null,
            ])->save();

            $workspace->forceFill(['status' => 'active'])->save();

            AuditLog::record('admin.workspace_restored', $workspace, [
                'oleh' => $request->user()->email,
            ], $workspace->id);

            return back()->with('status', "{$workspace->name} diaktifkan kembali.");
        }

        $this->subscriptions->suspend($subscription);

        AuditLog::record('admin.workspace_suspended', $workspace, [
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "{$workspace->name} ditangguhkan.");
    }

    /**
     * Kelonggaran batas untuk satu workspace, di luar paketnya.
     *
     * Sengaja hanya menyentuh kolom di tabel workspaces, bukan paket: satu
     * pelanggan yang butuh kuota lebih tidak boleh mengubah apa yang didapat
     * seluruh pelanggan pada paket yang sama.
     */
    public function overrideLimits(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'max_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'monthly_message_quota' => ['required', 'integer', 'min:0'],
        ]);

        $workspace = Workspace::findOrFail($id);

        $workspace->forceFill($data)->save();

        AuditLog::record('admin.limits_overridden', $workspace, $data + [
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "Batas {$workspace->name} disesuaikan.");
    }
}
