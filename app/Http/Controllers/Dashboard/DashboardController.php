<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $sessions = $workspace->sessions()->orderBy('name')->get();

        // Deret 7 hari dibangun dari hasil query yang dikelompokkan per tanggal,
        // lalu tanggal tanpa pesan diisi nol supaya grafik tidak bolong.
        $since = now()->subDays(6)->startOfDay();

        $daily = $workspace->messages()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, direction, COUNT(*) as total')
            ->groupBy('day', 'direction')
            ->get();

        $chart = collect(range(6, 0))->map(function (int $ago) use ($daily) {
            $day = now()->subDays($ago)->toDateString();

            return [
                'day' => $day,
                'label' => now()->subDays($ago)->translatedFormat('D'),
                'outbound' => (int) $daily->first(fn ($r) => (string) $r->day === $day && $r->direction === 'outbound')?->total,
                'inbound' => (int) $daily->first(fn ($r) => (string) $r->day === $day && $r->direction === 'inbound')?->total,
            ];
        });

        return view('dashboard.index', [
            'sessions' => $sessions,
            'usage' => $workspace->currentUsage(),
            'chart' => $chart,
            'chartMax' => max(1, $chart->max(fn ($d) => $d['outbound'] + $d['inbound'])),
            'statusCounts' => $workspace->messages()
                ->where('created_at', '>=', $since)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'recentMessages' => $workspace->messages()->with('session')->latest()->limit(10)->get(),
        ]);
    }
}
