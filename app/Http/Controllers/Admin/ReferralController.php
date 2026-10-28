<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PayoutPaidMail;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use App\Services\Billing\InvoicePdfService;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kode referal, konfirmasi permohonan mitra (ACC), dan pencairan komisi.
 *
 * Pembayaran komisi manual, dan itu keputusan sadar. Uang masuk saja masih
 * dicocokkan manusia lewat QRIS; admin memeriksa nomor rekening dan bukti
 * transfer secara cermat sebelum menandai status sudah dibayar.
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly InvoicePdfService $pdfService,
        private readonly WhatsAppNotifier $notifier,
    ) {}

    public function index(): View
    {
        $kode = ReferralCode::with('owner')
            ->withCount([
                'redemptions as dipakai' => fn ($q) => $q->whereIn('status', ['approved', 'paid']),
            ])
            ->latest()
            ->get();

        // Permohonan reseller baru yang menunggu konfirmasi (ACC)
        $permohonanMitra = ReferralCode::with('owner')
            ->where('approval_status', 'pending')
            ->latest()
            ->get();

        // Permintaan pencairan dana komisi yang menunggu transfer admin
        $payoutRequests = PayoutRequest::with(['user', 'referralCode'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.referrals', [
            'kode' => $kode,
            'permohonanMitra' => $permohonanMitra,
            'payoutRequests' => $payoutRequests,

            // Komisi yang menunggu ditransfer
            'terutang' => ReferralRedemption::with(['code.owner', 'workspace', 'invoice'])
                ->where('status', 'approved')
                ->latest('approved_at')
                ->get(),

            'totalTerutang' => (int) ReferralRedemption::where('status', 'approved')->sum('commission_amount'),
            'totalDibayar' => (int) ReferralRedemption::where('status', 'paid')->sum('commission_amount'),
            'totalDiskon' => (int) ReferralRedemption::whereIn('status', ['approved', 'paid'])->sum('discount_amount'),

            'kandidatPemilik' => User::whereDoesntHave('referralCode')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id', 'unique:referral_codes,owner_user_id'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $kode = ReferralCode::create($data + [
            'code' => ReferralCode::buatKode(),
            'created_by' => $request->user()->id,
            'approval_status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        AuditLog::record('referral.created', $kode, [
            'code' => $kode->code,
            'owner' => $kode->owner?->email,
            'diskon' => $kode->discount_percent,
            'komisi' => $kode->commission_percent,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kode dibuat: '.$kode->code,
            'pesan' => 'Pembeli dapat potongan '.$kode->discount_percent.'%, '
                .($kode->owner?->name ?? 'pemiliknya').' dapat komisi '.$kode->commission_percent.'% '
                .'dari total setelah potongan — dan komisinya baru terutang setelah tagihannya lunas.',
        ]);
    }

    /**
     * Konfirmasi / ACC Permohonan Mitra Baru.
     */
    public function approveApplication(Request $request, int $id): RedirectResponse
    {
        $kode = ReferralCode::findOrFail($id);

        $data = $request->validate([
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'commission_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $kode->update([
            'discount_percent' => $data['discount_percent'] ?? $kode->discount_percent,
            'commission_percent' => $data['commission_percent'] ?? $kode->commission_percent,
            'approval_status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        AuditLog::record('referral.application.approved', $kode, [
            'code' => $kode->code,
            'owner' => $kode->owner?->email,
            'by' => $request->user()->email,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Permohonan Mitra Disetujui (ACC)',
            'pesan' => 'Kode referal '.$kode->code.' untuk '.$kode->owner?->name.' telah diaktifkan dengan diskon '.$kode->discount_percent.'% dan komisi '.$kode->commission_percent.'%.',
        ]);
    }

    /**
     * Tolak Permohonan Mitra Baru.
     */
    public function rejectApplication(Request $request, int $id): RedirectResponse
    {
        $kode = ReferralCode::findOrFail($id);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan permohonan wajib diisi.',
        ]);

        $kode->update([
            'approval_status' => 'rejected',
            'is_active' => false,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        AuditLog::record('referral.application.rejected', $kode, [
            'code' => $kode->code,
            'owner' => $kode->owner?->email,
            'reason' => $data['rejection_reason'],
            'by' => $request->user()->email,
        ]);

        return back()->with('swal', [
            'tipe' => 'info',
            'judul' => 'Permohonan Mitra Ditolak',
            'pesan' => 'Permohonan untuk '.$kode->owner?->name.' telah ditolak dengan alasan: '.$data['rejection_reason'],
        ]);
    }

    /**
     * Tandai Permintaan Pencairan Dana Komisi Sudah Ditransfer.
     */
    public function markPayoutPaid(Request $request, int $id): RedirectResponse
    {
        $payout = PayoutRequest::with('referralCode')->findOrFail($id);

        if ($payout->status !== 'pending') {
            return back()->withErrors(['payout' => 'Permintaan ini sudah diproses sebelumnya.']);
        }

        DB::transaction(function () use ($payout, $request) {
            $payout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
                'admin_notes' => $request->input('admin_notes'),
            ]);

            // Tandai seluruh redemption yang approved untuk kode ini menjadi paid
            ReferralRedemption::where('referral_code_id', $payout->referral_code_id)
                ->where('status', 'approved')
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
        });

        AuditLog::record('mitra.payout.paid', $payout, [
            'amount' => $payout->amount,
            'owner' => $payout->user?->email,
            'by' => $request->user()->email,
        ]);

        $payout->loadMissing(['user', 'referralCode', 'payer']);

        // Terbitkan ulang dokumen invoice PDF resmi berstatus Lunas / Ditransfer
        $pdfPath = null;
        try {
            $pdfPath = $this->pdfService->generatePayoutInvoice($payout);
        } catch (\Throwable $e) {
            Log::warning('Gagal menerbitkan ulang PDF invoice lunas', ['error' => $e->getMessage()]);
        }

        $pdfMedia = $pdfPath ? [
            'type' => 'document',
            'media_path' => $pdfPath,
            'media_mime' => 'application/pdf',
            'media_filename' => "Invoice-Lunas-{$payout->payout_number}.pdf",
        ] : [];

        // 1. Notifikasi WhatsApp ke Mitra (disertai invoice lunas PDF)
        $targetWaUser = $payout->referralCode?->whatsapp_number ?: $payout->user?->phone;
        if (! blank($targetWaUser)) {
            $pesanWaUser = "Halo {$payout->user?->name}, dana komisi kemitraan Anda telah berhasil ditransfer!\n\n"
                ."No. Invoice: {$payout->payout_number}\n"
                .'Nominal Ditransfer (Net): Rp '.number_format($payout->net_amount, 0, ',', '.')."\n"
                ."Rekening Tujuan: {$payout->bank_name} - {$payout->bank_account_number} a.n {$payout->bank_account_name}\n"
                .($payout->admin_notes ? "Catatan Finance: {$payout->admin_notes}\n\n" : "\n")
                ."Terlampir dokumen invoice PDF resmi berstatus DITRANSFER / LUNAS sebagai bukti sah pelunasan. Terima kasih atas kerja sama Anda bersama Pesanvo!\n\n"
                .'Lihat invoice online: '.route('mitra.payout.invoice', $payout->id);
            $this->notifier->toPhone($targetWaUser, $pesanWaUser, media: $pdfMedia);
        }

        // 2. Notifikasi Email ke Mitra (disertai lampiran PDF invoice lunas)
        if ($payout->user?->email) {
            try {
                Mail::to($payout->user->email)->send(new PayoutPaidMail($payout));
            } catch (\Throwable $e) {
                // Kegagalan email tidak membatalkan proses
            }
        }

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Pencairan Komisi Selesai',
            'pesan' => 'Pencairan dana sebesar Rp '.number_format($payout->amount, 0, ',', '.')
                .' untuk '.$payout->user?->name.' berhasil ditandai sudah ditransfer. Invoice pelunasan telah dikirimkan ke WhatsApp dan Email mitra.',
        ]);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $kode = ReferralCode::findOrFail($id);

        $kode->forceFill(['is_active' => ! $kode->is_active])->save();

        AuditLog::record($kode->is_active ? 'referral.activated' : 'referral.deactivated', $kode, [
            'code' => $kode->code,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => $kode->is_active ? 'Kode diaktifkan' : 'Kode dimatikan',
            'pesan' => $kode->is_active
                ? $kode->code.' bisa ditukar lagi.'
                : $kode->code.' tidak bisa ditukar lagi. Komisi yang sudah disetujui tetap terutang.',
        ]);
    }

    /**
     * Menandai satu komisi satuan sudah ditransfer.
     */
    public function markPaid(Request $request, int $id): RedirectResponse
    {
        $redemption = ReferralRedemption::with('code')->findOrFail($id);

        if ($redemption->status !== 'approved') {
            return back()->withErrors([
                'komisi' => 'Hanya komisi yang sudah disetujui yang bisa ditandai dibayar.',
            ]);
        }

        $redemption->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        AuditLog::record('referral.commission.paid', $redemption, [
            'code' => $redemption->code?->code,
            'jumlah' => $redemption->commission_amount,
            'oleh' => $request->user()->email,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Komisi ditandai dibayar',
            'pesan' => 'Rp '.number_format($redemption->commission_amount, 0, ',', '.')
                .' untuk kode '.$redemption->code?->code.'.',
        ]);
    }

    /**
     * Halaman Invoice Resmi Pencairan Komisi Mitra untuk Admin (Standar Enterprise).
     */
    public function payoutInvoice(int $id): View
    {
        $payout = PayoutRequest::with(['user', 'referralCode', 'payer'])->findOrFail($id);

        return view('dashboard.mitra.payout-invoice', [
            'payout' => $payout,
            'isAdmin' => true,
        ]);
    }

    /**
     * Unduh Berkas Dokumen Invoice PDF Resmi untuk Admin.
     */
    public function downloadInvoice(int $id)
    {
        $payout = PayoutRequest::with(['user', 'referralCode', 'payer'])->findOrFail($id);

        return $this->pdfService->streamPayoutInvoice($payout)
            ->download("Invoice-{$payout->payout_number}.pdf");
    }
}
