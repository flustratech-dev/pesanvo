<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lonceng dan halaman riwayatnya, untuk kedua aliran.
 *
 * Satu controller untuk pelanggan dan admin, dengan `audience` yang menentukan
 * mana yang dilihat. Dua controller berarti dua tempat yang harus dijaga tetap
 * sama — dan yang menyimpang di sini berarti "tandai sudah dibaca" bekerja di
 * satu aliran tapi tidak di aliran lain, tanpa gejala.
 *
 * Aliran admin dijaga `EnsureSuperAdmin` di rutenya, bukan di sini.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $audience = $this->audience($request);

        return view('notifications.index', [
            'audience' => $audience,
            'notifikasi' => Notification::untuk($request->user(), $audience)
                ->with('workspace')
                ->latest('id')
                ->paginate(30),
            'belumDibaca' => Notification::untuk($request->user(), $audience)->belumDibaca()->count(),
        ]);
    }

    /**
     * Membuka satu notifikasi: ditandai dibaca lalu diarahkan ke tautannya.
     *
     * Ditandai di sini, bukan lewat tombol terpisah — yang membaca notifikasi
     * adalah orang yang menekannya, dan menuntut satu klik lagi untuk
     * mengakuinya menghasilkan lonceng yang angkanya tidak pernah turun.
     */
    public function open(Request $request, int $id): RedirectResponse
    {
        $notifikasi = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        if (! $notifikasi->sudahDibaca()) {
            $notifikasi->forceFill(['read_at' => now()])->save();
        }

        $target = Notifier::bersihkanUrl($notifikasi->url);

        return redirect($target ?: route('notifications.index', [
            'audience' => $notifikasi->audience,
        ]));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $audience = $this->audience($request);

        Notification::untuk($request->user(), $audience)
            ->belumDibaca()
            ->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Aliran mana yang sedang dilihat.
     *
     * Aliran admin hanya bisa diminta oleh super admin. Tanpa penjagaan ini,
     * `?audience=admin` di URL cukup untuk membaca kabar yang menyebut nama
     * workspace dan nominal tagihan pelanggan lain.
     */
    private function audience(Request $request): string
    {
        $diminta = $request->query('audience', 'workspace');

        return $diminta === 'admin' && $request->user()->is_super_admin
            ? 'admin'
            : 'workspace';
    }
}
