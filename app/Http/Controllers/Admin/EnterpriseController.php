<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EnterpriseLead;
use App\Models\EnterprisePlan;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Permintaan Enterprise dan kesepakatan yang menjawabnya.
 *
 * Saringan bawaannya **baru**, mengikuti pola yang sudah terbukti di halaman
 * Tagihan dan Tiket: yang menentukan sebuah baris perlu dilihat manusia bukan
 * seluruh daftarnya, melainkan adanya sesuatu yang belum dijawab. Permintaan
 * penawaran yang tenggelam adalah calon pelanggan terbesar yang pergi tanpa
 * pernah dijawab.
 */
class EnterpriseController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $saringan = $request->query('saringan', 'baru');

        return view('admin.enterprise.index', [
            'leads' => EnterpriseLead::with(['workspace', 'penangan'])
                ->when($saringan !== 'semua', fn ($q) => $q->where('status', $saringan))
                // Terlama di atas untuk yang belum dijawab: yang paling lama
                // menunggu adalah yang paling mungkin sudah pergi ke tempat lain.
                ->orderBy('created_at', $saringan === 'baru' ? 'asc' : 'desc')
                ->limit(200)
                ->get(),

            'saringan' => $saringan,
            'jumlahBaru' => EnterpriseLead::where('status', 'baru')->count(),

            'kesepakatan' => EnterprisePlan::with(['workspace', 'lead'])
                ->where('is_active', true)
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(int $id): View
    {
        $lead = EnterpriseLead::with(['workspace', 'penangan', 'plans.workspace'])->findOrFail($id);

        return view('admin.enterprise.show', [
            'lead' => $lead,

            // Hanya workspace yang bisa dipilih sebagai penerima kesepakatan.
            // Daftar penuh dengan pemiliknya supaya admin tidak salah menunjuk
            // workspace bernama mirip — dan kesepakatan yang salah tunjuk
            // memberi batas mahal ke orang yang tidak membayarnya.
            'kandidat' => Workspace::orderBy('name')->get(['id', 'name', 'owner_email']),
            'kesepakatanAktif' => $lead->workspace_id
                ? EnterprisePlan::berlakuUntuk($lead->workspace_id)
                : null,
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $lead = EnterpriseLead::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['baru', 'diproses', 'selesai', 'ditolak'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [], ['admin_note' => 'catatan']);

        $lead->forceFill([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? $lead->admin_note,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        AuditLog::record('enterprise.lead.status', $lead, [
            'status' => $data['status'],
            'oleh' => $request->user()->email,
        ], $lead->workspace_id);

        return back()->with('status', 'Permintaan ditandai '.$lead->labelStatus().'.');
    }

    /**
     * Menyusun kesepakatan untuk satu workspace.
     *
     * Kesepakatan lama TIDAK dihapus, hanya dimatikan — riwayat harga yang
     * pernah disepakati adalah hal pertama yang dicari orang saat ada
     * perselisihan tagihan.
     */
    public function storePlan(Request $request, int $id): RedirectResponse
    {
        $lead = EnterpriseLead::findOrFail($id);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:80'],
            'price_monthly' => ['required', 'integer', 'min:1'],
            'price_yearly' => ['required', 'integer', 'min:1'],
            'max_sessions' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_message_quota' => ['required', 'integer', 'min:0'],
            'max_api_keys' => ['required', 'integer', 'min:0'],
            'max_members' => ['required', 'integer', 'min:0'],
            'message_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'api_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'workspace_id' => 'workspace',
            'name' => 'nama kesepakatan',
            'price_monthly' => 'harga bulanan',
            'price_yearly' => 'harga tahunan',
            'max_sessions' => 'jumlah nomor',
            'monthly_message_quota' => 'kuota pesan',
            'max_api_keys' => 'batas API key',
            'max_members' => 'batas anggota',
            'message_retention_days' => 'retensi pesan',
            'api_rate_limit_per_minute' => 'batas API per menit',
        ]);

        /*
         | Jumlah nomor diperiksa terhadap kapasitas engine, bukan dibiarkan.
         |
         | Platform ini sanggup menjalankan WA_MAX_SESSIONS nomor untuk SELURUH
         | pelanggan sekaligus. Menjanjikan sepuluh nomor kepada satu pelanggan
         | enterprise berarti menjual sesuatu yang tidak ada — dan yang menemukan
         | kekurangannya adalah pelanggan yang sudah membayar mahal, saat nomor
         | kesebelas gagal tersambung tanpa penjelasan.
        */
        $kapasitas = (int) config('gateway.engine.max_sessions');

        if ($kapasitas > 0 && $data['max_sessions'] > $kapasitas) {
            return back()->withInput()->withErrors([
                'max_sessions' => "Kapasitas seluruh platform saat ini {$kapasitas} nomor aktif "
                    .'(WA_MAX_SESSIONS). Menjanjikan lebih dari itu kepada satu pelanggan berarti '
                    .'menjual sesuatu yang belum ada — naikkan kapasitas engine lebih dulu.',
            ]);
        }

        EnterprisePlan::where('workspace_id', $data['workspace_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $plan = EnterprisePlan::create($data + [
            'lead_id' => $lead->id,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        // Permintaannya otomatis pindah ke `diproses`: kesepakatan sudah
        // disusun, tinggal menunggu pelanggan membayarnya.
        if ($lead->status === 'baru') {
            $lead->forceFill([
                'status' => 'diproses',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ])->save();
        }

        AuditLog::record('enterprise.plan.created', $plan, [
            'nama' => $plan->name,
            'bulanan' => $plan->price_monthly,
            'tahunan' => $plan->price_yearly,
            'nomor' => $plan->max_sessions,
            'oleh' => $request->user()->email,
        ], $plan->workspace_id);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kesepakatan tersimpan',
            'pesan' => 'Batasnya baru benar-benar berlaku setelah tagihannya lunas — '
                .'sama seperti paket biasa. Terbitkan tagihannya dari halaman ini.',
        ]);
    }

    /** Menerbitkan tagihan untuk kesepakatan yang sudah disusun. */
    public function issueInvoice(Request $request, int $id, int $planId): RedirectResponse
    {
        $plan = EnterprisePlan::where('lead_id', $id)->findOrFail($planId);

        $data = $request->validate([
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        try {
            $invoice = $this->subscriptions->issueEnterpriseInvoice($plan, $data['period']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return redirect()->route('admin.invoices')->with('swal', [
            'tipe' => 'success',
            'judul' => 'Tagihan '.$invoice->number.' terbit',
            'pesan' => 'Pelanggan menerimanya lewat email dan bisa membayarnya lewat QRIS, '
                .'sama seperti tagihan paket biasa.',
        ]);
    }

    public function deactivatePlan(Request $request, int $id, int $planId): RedirectResponse
    {
        $plan = EnterprisePlan::where('lead_id', $id)->findOrFail($planId);

        $plan->forceFill(['is_active' => false])->save();

        AuditLog::record('enterprise.plan.deactivated', $plan, [
            'nama' => $plan->name,
            'oleh' => $request->user()->email,
        ], $plan->workspace_id);

        // Batas yang sudah tersalin ke kolom workspace TIDAK ikut dicabut, dan
        // itu disengaja: pelanggan sudah membayar untuk periode berjalan.
        return back()->with('status', 'Kesepakatan dimatikan. '
            .'Batas yang sedang berlaku tidak dicabut — periode yang sudah dibayar tetap utuh.');
    }
}
