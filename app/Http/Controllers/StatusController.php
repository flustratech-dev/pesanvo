<?php

namespace App\Http\Controllers;

use App\Models\StatusHarian;
use App\Models\StatusIncident;
use App\Support\StatusLayanan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Halaman status publik.
 *
 * Terbuka tanpa login, dan itu bukan kelalaian: yang paling butuh halaman ini
 * adalah orang yang sedang tidak bisa masuk. Halaman status di balik login
 * adalah halaman status yang mati persis saat ia diperlukan.
 */
class StatusController extends Controller
{
    private const HARI_RIWAYAT = 90;

    public function __invoke(): View
    {
        $komponen = $this->komponen();

        return view('status', [
            'komponen' => $komponen,
            'ringkas' => StatusLayanan::ringkas($komponen),
            'riwayat' => $this->riwayat(),
            'uptime' => $this->uptime(),
            'berjalan' => StatusIncident::berjalan()->with('updates')->latest('started_at')->get(),
            'lampau' => StatusIncident::whereNotNull('resolved_at')
                ->where('started_at', '>=', now()->subDays(self::HARI_RIWAYAT))
                ->with('updates')
                ->latest('started_at')
                ->limit(20)
                ->get(),
            'hariRiwayat' => self::HARI_RIWAYAT,
        ]);
    }

    /**
     * Bentuk JSON dari halaman yang sama.
     *
     * Ada supaya pelanggan bisa memasang lencana status di dashboard mereka
     * sendiri tanpa mengurai HTML kami — dan supaya perubahan tata letak halaman
     * ini tidak diam-diam merusak integrasi orang.
     */
    public function json(): JsonResponse
    {
        $komponen = $this->komponen();

        return response()->json([
            'status' => StatusLayanan::ringkas($komponen),
            'komponen' => collect($komponen)->map(fn (array $d): array => [
                'nama' => $d['nama'],
                'keadaan' => $d['keadaan'],
                'catatan' => $d['catatan'],
            ]),
            'insiden_berjalan' => StatusIncident::berjalan()->count(),
            'diperiksa_pada' => now()->toIso8601String(),
        ]);
    }

    /**
     * Hasil pemeriksaan, ditahan sebentar di cache.
     *
     * Pemeriksaan memanggil engine lewat jaringan. Halaman ini justru paling
     * ramai dibuka saat sedang gangguan — persis saat engine paling lambat
     * menjawab — dan tanpa penahan ini setiap pengunjung menambah satu panggilan
     * ke komponen yang sedang sekarat. Sepuluh detik cukup pendek untuk terasa
     * seketika dan cukup panjang untuk meratakan lonjakannya.
     */
    private function komponen(): array
    {
        return Cache::remember('status:komponen', now()->addSeconds(10), fn () => StatusLayanan::periksa());
    }

    /**
     * Persentase sehat per hari per komponen, untuk deretan batang.
     *
     * @return array<string, array<string, ?float>>
     */
    private function riwayat(): array
    {
        $baris = StatusHarian::where('day', '>=', now()->subDays(self::HARI_RIWAYAT - 1)->toDateString())->get();

        $peta = [];

        foreach ($baris as $b) {
            $peta[$b->component][$b->day] = $b->persen();
        }

        $hasil = [];

        foreach (array_keys(StatusLayanan::daftarKomponen()) as $kunci) {
            for ($i = self::HARI_RIWAYAT - 1; $i >= 0; $i--) {
                $hari = now()->subDays($i)->toDateString();

                // Hari tanpa sampel bernilai null, bukan 0 — halaman
                // menggambarnya abu-abu. Sistem yang baru dipasang punya 89 hari
                // seperti itu, dan menggambarnya merah adalah riwayat gangguan
                // yang tidak pernah terjadi.
                $hasil[$kunci][$hari] = $peta[$kunci][$hari] ?? null;
            }
        }

        return $hasil;
    }

    /** Ketersediaan gabungan 30 dan 90 hari, angka yang dirujuk SLA. */
    private function uptime(): array
    {
        $hitung = function (int $hari): ?float {
            $baris = StatusHarian::where('day', '>=', now()->subDays($hari - 1)->toDateString())->get();

            $ok = $baris->sum('ok_count');
            $total = $ok + $baris->sum('fail_count');

            return $total === 0 ? null : round($ok / $total * 100, 2);
        };

        return ['30' => $hitung(30), '90' => $hitung(self::HARI_RIWAYAT)];
    }
}
