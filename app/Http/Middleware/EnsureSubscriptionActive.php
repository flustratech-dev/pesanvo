<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menghentikan perubahan data saat langganan tidak berlaku, dan menyediakan
 * langganan itu ke seluruh halaman dashboard supaya spanduknya bisa muncul.
 *
 * Aturannya satu kalimat: **permintaan GET selalu lewat, selain GET ditolak.**
 *
 * Dipilih begitu, bukan dengan mendaftar rute mana saja yang dijaga, karena
 * daftar rute adalah daftar yang cepat atau lambat ketinggalan — rute baru yang
 * lupa didaftarkan akan diam-diam tetap bisa dipakai tanpa membayar, dan tidak
 * ada tes yang bisa menangkap sesuatu yang belum ada. Membaca boleh selamanya:
 * pelanggan yang berhenti berlangganan harus tetap bisa membuka riwayat
 * pesannya, mengunduh datanya, dan melihat kenapa layanannya berhenti.
 */
class EnsureSubscriptionActive
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        // Workspace internal Pesanvo tidak pernah menagih dirinya sendiri.
        // Dibebaskan lewat panel admin — nomor perusahaan sendiri, dan akun
        // yang memang tidak ditagih.
        if ($workspace->isExempt()) {
            view()->share('currentSubscription', null);
            view()->share('pendingInvoice', null);

            return $next($request);
        }

        $subscription = $this->subscriptions->ensureFor($workspace);
        $pendingInvoice = $workspace->invoices()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('due_at')->orWhere('due_at', '>', now());
            })
            ->latest()
            ->first();

        view()->share('currentSubscription', $subscription);
        view()->share('pendingInvoice', $pendingInvoice);

        if ($subscription->isUsable()) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        // Halaman tagihan sendiri harus tetap bisa menerima POST — di situlah
        // pelanggan memilih paket dan mengunggah bukti bayar. Menjaganya ikut
        // terblokir berarti satu-satunya jalan keluar dari keadaan ini tertutup.
        if ($request->routeIs('billing.*')) {
            return $next($request);
        }

        return redirect()
            ->route($subscription->isUnpaid() ? 'billing.plans' : 'billing.index')
            ->withErrors([
                'langganan' => $subscription->isUnpaid()
                    ? 'Pilih paket dulu untuk mulai memakai gateway.'
                    : 'Langganan workspace ini sedang tidak aktif. Perpanjang dulu untuk melanjutkan.',
            ]);
    }
}
