<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Services\Billing\BalanceService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Saldo pay as you go: sisa, riwayat mutasi, dan tombol isi saldo.
 *
 * Riwayat mutasinya ditampilkan penuh dan bukan diringkas jadi satu angka.
 * Saldo adalah uang pelanggan yang sudah dibayar di depan, dan angka tunggal
 * tanpa rinciannya tidak bisa dibantah maupun diperiksa oleh yang memilikinya.
 */
class BalanceController extends Controller
{
    public function __construct(
        private readonly BalanceService $balances,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.balance', [
            'workspace' => $workspace,
            'saldo' => (int) $workspace->balance,
            'sisaPesan' => $workspace->sisaPesanPayg(),
            'harga' => $this->balances->hargaPerPesan(),
            'minimum' => $this->balances->minimumTopup(),

            'mutasi' => $workspace->balanceTransactions()
                ->with('invoice')
                ->latest('id')
                ->limit(100)
                ->get(),

            // Tagihan isi saldo yang belum dibayar. Tanpa ini, pelanggan yang
            // menutup halaman bayar kehilangan jejak tagihannya dan menerbitkan
            // yang baru — dua tagihan untuk satu niat, dan admin yang harus
            // menebak mana yang dibayar.
            'menunggu' => $workspace->invoices()
                ->where('plan_slug', 'payg')
                ->where('status', 'pending')
                ->latest()
                ->get(),

            'bolehBayar' => $request->user()->canManage($workspace),
        ]);
    }

    public function topUp(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengisi saldo.');

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
        ], [], ['jumlah' => 'jumlah isi saldo']);

        try {
            $invoice = $this->subscriptions->issueTopupInvoice($workspace, $data['jumlah']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['jumlah' => $e->getMessage()])->withInput();
        }

        // Mendarat di halaman tagihan yang sudah ada, bukan di alur pembayaran
        // kedua: QRIS, unggah bukti, dan verifikasinya sama persis.
        return redirect()->route('billing.invoice', $invoice->id);
    }
}
