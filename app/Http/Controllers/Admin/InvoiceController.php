<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Antrean pemeriksaan pembayaran.
 *
 * Selama saluran pembayaran masih QRIS manual, halaman ini adalah bagian
 * sistem yang tidak boleh dilewati siapa pun: tidak ada notifikasi otomatis,
 * jadi tagihan hanya menjadi lunas kalau ada orang yang membukanya di sini.
 * Karena itu urutannya bukan berdasarkan tanggal, melainkan berdasarkan
 * tagihan mana yang sudah ada buktinya dan sedang ditunggu pelanggannya.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly WhatsAppNotifier $notifier,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'perlu-diperiksa');
        $cari = trim((string) $request->query('cari'));

        return view('admin.invoices', [
            /*
             | Saringan bawaan `perlu-diperiksa`, bukan `pending`.
             |
             | Ini menutup kegagalan yang sudah benar-benar terjadi: pelanggan
             | mengunggah bukti, lalu tagihannya berpindah status — dibatalkan
             | sendiri, atau kedaluwarsa karena lewat batas bayar — dan seketika
             | itu juga ia lenyap dari layar admin. Uangnya sudah masuk, layanan
             | tetap mati, dan tidak ada satu pun tempat yang menunjukkannya.
             |
             | Karena itu yang menentukan sebuah tagihan perlu dilihat manusia
             | bukan statusnya, melainkan ADA BUKTI yang belum dijawab.
             */
            'invoices' => Invoice::query()
                ->with(['workspace', 'paidBy'])
                ->when($status === 'perlu-diperiksa', fn ($q) => $q->where(function ($q) {
                    $q->where(fn ($q) => $q->whereNotNull('proof_path')->where('status', '!=', 'paid'))
                        ->orWhere(fn ($q) => $q->whereNotNull('payment_confirmed_at')->where('status', '!=', 'paid'))
                        ->orWhere('status', 'pending');
                }))
                ->when(! in_array($status, ['perlu-diperiksa', 'semua'], true), fn ($q) => $q->where('status', $status))
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('number', 'like', "%{$cari}%")
                        ->orWhere('total', 'like', "%{$cari}%")
                        ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$cari}%"));
                }))
                // Yang sudah dikonfirmasi pelanggan atau ada buktinya diprioritaskan di atas
                ->orderByRaw("case when status = 'pending' and (payment_confirmed_at is not null or proof_path is not null) then 0 else 1 end")
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'cari' => $cari,
        ]);
    }

    /**
     * Menandai lunas. Di sinilah langganan benar-benar diperpanjang, batas
     * paket diterapkan, dan workspace yang tertangguh menyala kembali.
     */
    public function markPaid(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid()) {
            return back()->with('swal', [
                'icon' => 'info',
                'title' => 'Sudah lunas',
                'text' => 'Tagihan ini sudah pernah ditandai lunas, jadi tidak ada yang berubah.',
            ]);
        }

        $this->subscriptions->markPaid($invoice, $request->user(), $data['catatan'] ?? null);

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Tagihan ditandai lunas',
            'text' => "{$invoice->number} lunas. Langganannya sudah diperpanjang dan batas paketnya berlaku sekarang.",
        ]);
    }

    /**
     * Menolak bukti dan membuka kembali tagihannya.
     *
     * Tanpa ini, bukti yang tidak terbaca — tangkapan layar terpotong, nominal
     * tidak cocok, transfer atas nama orang lain — berujung jalan buntu: admin
     * tidak bisa menandainya lunas dengan jujur, dan pelanggan tidak bisa
     * mengunggah ulang karena tagihannya sudah telanjur ditutup atau lewat
     * tempo. Yang tersisa hanya percakapan di luar sistem yang tidak
     * meninggalkan jejak apa pun.
     *
     * Buktinya dibuang dari catatan tapi berkasnya sengaja TIDAK dihapus dari
     * disk: kalau ternyata admin yang keliru menilai, satu-satunya bukti bahwa
     * pelanggan pernah membayar tidak boleh ikut lenyap bersama keputusan itu.
     */
    public function requestNewProof(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ], [], ['alasan' => 'alasan penolakan']);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid()) {
            return back()->with('swal', [
                'icon' => 'info',
                'title' => 'Sudah lunas',
                'text' => 'Tagihan yang sudah lunas tidak perlu bukti baru.',
            ]);
        }

        $invoice->forceFill([
            'proof_path' => null,
            'status' => 'pending',
            'note' => $data['alasan'],
            // Tagihannya dibuka lagi dengan tenggat baru; mengembalikannya ke
            // pending sambil membiarkan due_at yang sudah lewat berarti job
            // harian menutupnya lagi keesokan paginya.
            'due_at' => now()->addDays(config('billing.invoice_due_days')),
        ])->save();

        AuditLog::record('admin.proof_rejected', $invoice, [
            'alasan' => $data['alasan'],
            'oleh' => $request->user()->email,
        ], $invoice->workspace_id);

        // Penolakan tanpa alasan yang sampai ke pelanggan cuma membuat tagihan
        // terbuka lagi tanpa ada yang tahu kenapa.
        $terkirim = $invoice->workspace
            ? $this->notifier->toWorkspace(
                $invoice->workspace,
                BillingMessages::proofRejected($invoice, $data['alasan']),
                "proof-rejected:{$invoice->id}:".now()->timestamp,
            )
            : false;

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Bukti ditolak, tagihan dibuka kembali',
            'text' => $terkirim
                ? "{$invoice->number} kembali menunggu pembayaran, dan alasannya sudah dikirim ke WhatsApp pelanggan."
                : "{$invoice->number} kembali menunggu pembayaran. Pemberitahuan WhatsApp tidak terkirim — beri tahu pelanggannya sendiri.",
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['expired', 'canceled'])],
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid()) {
            return back()->withErrors(['tagihan' => 'Tagihan yang sudah lunas tidak bisa diubah statusnya.']);
        }

        $invoice->forceFill(['status' => $data['status']])->save();

        AuditLog::record('admin.invoice_status_changed', $invoice, [
            'status' => $data['status'],
            'oleh' => $request->user()->email,
        ], $invoice->workspace_id);

        return back()->with('status', "Tagihan {$invoice->number} ditandai {$data['status']}.");
    }

    public function proof(int $id)
    {
        $invoice = Invoice::findOrFail($id);

        abort_if(blank($invoice->proof_path), 404);

        return response()->file(Storage::disk('media')->path($invoice->proof_path));
    }
}
