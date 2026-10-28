<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\KesehatanAntrean;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan platform dan kesehatannya.
 *
 * Satu angka di halaman ini lebih penting dari sisanya: sesi yang sedang hidup
 * dibanding `WA_MAX_SESSIONS`. Batasnya ditegakkan engine, bukan Laravel.
 * Begitu keduanya bertemu, pelanggan berikutnya yang membayar tidak akan bisa
 * menautkan nomornya — dan tanpa angka ini di layar, hal itu baru ketahuan dari keluhan.
 */
class OverviewController extends Controller
{
    public function __construct(private readonly WhatsAppNotifier $notifier) {}

    public function __invoke(): View
    {
        $bulanIni = now()->format('Y-m');

        return view('admin.overview', [
            'workspaceTotal' => Workspace::count(),
            'workspaceAktif' => Workspace::where('status', 'active')->count(),

            'langgananPerStatus' => Subscription::query()
                ->selectRaw('status, count(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status')
                ->all(),

            // Pendapatan bulan ini dihitung dari tagihan yang benar-benar
            // ditandai lunas, bukan dari langganan yang berstatus aktif:
            // langganan yang diperpanjang admin lewat kelonggaran juga aktif,
            // dan uangnya tidak pernah masuk.
            'pendapatanBulanIni' => (int) Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total'),

            'tagihanMenunggu' => Invoice::where('status', 'pending')->count(),
            // Semua status kecuali lunas: bukti yang menempel pada tagihan
            // yang telanjur dibatalkan atau kedaluwarsa tetap berarti ada orang
            // yang sudah membayar dan layanannya masih mati.
            'tagihanPerluDiperiksa' => Invoice::whereNotNull('proof_path')
                ->where('status', '!=', 'paid')
                ->count(),

            'sesiHidup' => WaSession::whereIn('status', ['connected', 'connecting', 'qr'])->count(),
            'sesiTersambung' => WaSession::where('status', 'connected')->count(),
            'kapasitasSesi' => (int) config('gateway.engine.max_sessions'),

            'pesanBulanIni' => (int) DB::table('usage_counters')
                ->where('period', $bulanIni)
                ->sum('messages_sent'),

            /*
             | Apakah pemberitahuan WhatsApp benar-benar bisa dikirim.
             |
             | Ini keadaan yang paling sulit disadari kalau tidak ditampilkan:
             | begitu `BILLING_NOTIFY_WORKSPACE_ID` kosong atau nomor Pesanvo
             | terputus, SELURUH pemberitahuan berhenti tanpa satu pun gejala —
             | pelanggan tidak diberi tahu tagihannya lunas, tim tidak diberi
             | tahu ada bukti masuk, dan tidak ada yang gagal secara terlihat.
            */
            'notifikasiSiap' => $this->notifier->ready(),
            'nomorAdminTerisi' => filled(config('billing.admin_phone')),

            'antrean' => DB::table('jobs')->count(),
            'antreanGagal' => DB::table('failed_jobs')->count(),

            // Antrean adalah satu-satunya bagian sistem ini yang gagal tanpa
            // gejala di mana pun: worker mati = pesan diam `queued` selamanya,
            // tanpa galat, tanpa log baru.
            'kesehatanAntrean' => KesehatanAntrean::periksa(),

            'akanBerakhir' => Subscription::with('workspace')
                ->whereIn('status', ['trialing', 'active'])
                ->whereBetween('current_period_end', [now(), now()->addDays(7)])
                ->orderBy('current_period_end')
                ->limit(10)
                ->get(),
        ]);
    }
}
