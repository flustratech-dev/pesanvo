<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StatusIncident;
use App\Support\StatusLayanan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Menulis insiden yang tampil di halaman status publik.
 *
 * Yang otomatis hanya lencana komponen. Insiden ditulis manusia, dan itu bukan
 * kekurangan yang menunggu diotomatiskan: pemeriksaan mesin tahu sebuah
 * komponen tidak sehat, tapi tidak tahu apa yang terjadi, siapa yang terdampak,
 * dan kapan kira-kira selesai — tiga hal yang justru dicari orang saat membuka
 * halaman status di tengah gangguan.
 */
class StatusController extends Controller
{
    private const STATUS = ['menyelidiki', 'teridentifikasi', 'memantau', 'selesai'];

    public function index(): View
    {
        return view('admin.status', [
            'komponen' => StatusLayanan::periksa(),
            'daftarKomponen' => StatusLayanan::daftarKomponen(),
            'berjalan' => StatusIncident::berjalan()->with('updates')->latest('started_at')->get(),
            'lampau' => StatusIncident::whereNotNull('resolved_at')->latest('started_at')->limit(30)->get(),
            'heartbeat' => config('monitoring.heartbeat.url'),
            'daftarStatus' => self::STATUS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:2000'],
            'component' => ['nullable', 'string', 'in:'.implode(',', array_keys(StatusLayanan::daftarKomponen()))],
            'kind' => ['required', 'in:gangguan,pemeliharaan'],
            'impact' => ['required', 'in:sebagian,total'],
            'started_at' => ['nullable', 'date'],
        ], [], [
            'title' => 'judul',
            'summary' => 'ringkasan',
            'component' => 'komponen',
            'kind' => 'jenis',
            'impact' => 'dampak',
        ]);

        StatusIncident::create($data + [
            'status' => 'menyelidiki',
            // Bawaannya sekarang, bukan wajib diisi: saat gangguan sedang
            // berlangsung, memaksa admin mengetik tanggal adalah satu langkah
            // yang membuat pengumuman terlambat beberapa menit.
            'started_at' => $data['started_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        $this->segarkan();

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Insiden diumumkan',
            'pesan' => 'Sudah tampil di halaman status publik.',
        ]);
    }

    /** Menambah kabar susulan, sekaligus memindahkan status insidennya. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $insiden = StatusIncident::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUS)],
            'body' => ['required', 'string', 'max:2000'],
        ], [], ['body' => 'kabar']);

        $insiden->updates()->create($data + ['created_by' => $request->user()->id]);

        $insiden->status = $data['status'];

        /*
         | Status "selesai" DAN `resolved_at` diisi bersamaan, selalu.
         |
         | Keduanya dibaca di tempat yang berbeda — label dibaca dari `status`,
         | sedangkan halaman publik memilah berjalan/lampau dari `resolved_at` —
         | jadi mengisi salah satunya saja menghasilkan insiden yang tertulis
         | "Selesai" tapi tetap berkedip sebagai gangguan aktif di puncak
         | halaman, berhari-hari, di depan setiap pengunjung.
        */
        if ($data['status'] === 'selesai') {
            $insiden->resolved_at ??= now();
        } else {
            $insiden->resolved_at = null;
        }

        $insiden->save();

        $this->segarkan();

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kabar ditambahkan',
            'pesan' => $insiden->selesai()
                ? 'Insiden ditandai selesai dan pindah ke riwayat.'
                : 'Sudah tampil di halaman status publik.',
        ]);
    }

    /**
     * Halaman publik menahan hasil pemeriksaan 10 detik. Tanpa pembersihan ini,
     * admin yang mengumumkan gangguan lalu memuat halaman status untuk
     * memastikannya bisa melihat keadaan lama dan menyimpulkan pengumumannya
     * gagal — lalu mengumumkannya dua kali.
     */
    private function segarkan(): void
    {
        Cache::forget('status:komponen');
    }
}
