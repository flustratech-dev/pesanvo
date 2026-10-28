<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Jobs\SusunEksporDataJob;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Services\PenghapusanAkun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hak atas data: mengunduh salinannya, dan menghapus akun.
 *
 * Keduanya diminta UU PDP dan keduanya ditanya di kuesioner vendor perusahaan.
 * Tapi alasan sebenarnya lebih sederhana: hak yang hanya bisa dipakai dengan
 * mengirim tiket lalu menunggu jawaban manusia adalah hak yang sebagian besar
 * orang tidak pernah pakai, dan yang tidak bisa kami buktikan pernah kami
 * layani.
 */
class DataRightsController extends Controller
{
    /**
     * Meminta ekspor data workspace yang sedang dipilih.
     *
     * Dibatasi satu permintaan per 24 jam per workspace. Batas ini bukan
     * penghematan biaya: tiap ekspor menyusun berkas yang bisa ratusan megabyte
     * dan menahan satu pekerja antrean selama itu — pekerja yang sama yang
     * mengirim pesan seluruh pelanggan.
     */
    public function ekspor(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $terakhir = DataExport::where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();

        if ($terakhir) {
            return back()->with('swal', [
                'tipe' => 'info',
                'judul' => 'Sudah ada permintaan hari ini',
                'pesan' => $terakhir->status === 'siap'
                    ? 'Berkas terakhir masih bisa diunduh di halaman ini.'
                    : 'Permintaan sebelumnya masih disusun. Kami mengabari Anda begitu selesai.',
            ]);
        }

        $ekspor = DataExport::create([
            'workspace_id' => $workspace->id,
            'requested_by' => $request->user()->id,
            'status' => 'menunggu',
        ]);

        SusunEksporDataJob::dispatch($ekspor->id);

        AuditLog::record('workspace.data.exported', $workspace, ['ekspor' => $ekspor->id], $workspace->id);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Ekspor sedang disusun',
            'pesan' => 'Kami mengabari Anda lewat notifikasi begitu berkasnya siap diunduh. '
                .'Untuk workspace dengan riwayat panjang, ini bisa memakan beberapa menit.',
        ]);
    }

    public function unduh(Request $request, int $id): StreamedResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        /*
         | Dicari DARI workspace yang sedang dipilih, bukan `find($id)` lalu
         | diperiksa kepemilikannya. Pola kedua adalah pola yang suatu saat
         | kehilangan pemeriksaannya saat kode dirapikan — dan yang bocor di
         | sini bukan satu baris, melainkan seluruh isi percakapan sebuah
         | workspace dalam satu berkas.
        */
        $ekspor = DataExport::where('workspace_id', $workspace->id)->findOrFail($id);

        abort_unless($ekspor->bisaDiunduh(), 410, 'Berkas ekspor ini sudah tidak tersedia.');

        return Storage::disk('local')->download($ekspor->path, basename($ekspor->path));
    }

    /** Meminta penghapusan akun. Belum menghapus apa pun. */
    public function mintaHapus(Request $request, PenghapusanAkun $penghapusan): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->is_super_admin, 403, 'Akun administrator tidak dapat dihapus sendiri.');

        /*
         | Akun yang masuk lewat Google tidak punya kata sandi untuk dicocokkan,
         | jadi konfirmasinya mengetik alamat email sendiri. Meloloskan mereka
         | tanpa konfirmasi apa pun berarti satu klik di perangkat yang
         | tertinggal terbuka menghapus seluruh workspace.
        */
        if (filled($user->password)) {
            $request->validate(
                ['password' => ['required', 'current_password']],
                ['password.current_password' => 'Kata sandi tidak cocok. Akun tidak jadi dihapus.'],
            );
        } else {
            $request->validate(['confirm_email' => ['required', 'string']]);

            if (! hash_equals(mb_strtolower($user->email), mb_strtolower(trim($request->input('confirm_email'))))) {
                return back()->withErrors([
                    'confirm_email' => 'Alamat email yang diketik tidak cocok. Akun tidak jadi dihapus.',
                ]);
            }
        }

        $hari = (int) config('legal.retention.account_grace_days');

        $user->forceFill([
            'deletion_requested_at' => now(),
            // Tanggalnya DIBEKUKAN di sini, bukan dihitung ulang dari config
            // tiap kali dibaca. Mengubah masa tunggu di config tidak boleh
            // menggeser tanggal eksekusi orang yang sudah diberi tahu
            // tanggalnya.
            'deletion_scheduled_for' => now()->addDays($hari),
        ])->save();

        AuditLog::record('user.deletion.requested', $user, [
            'dijadwalkan' => $user->deletion_scheduled_for->toDateString(),
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Penghapusan dijadwalkan',
            'pesan' => 'Akun Anda dihapus permanen pada '
                .$user->deletion_scheduled_for->translatedFormat('j F Y')
                .'. Sampai tanggal itu Anda masih bisa membatalkannya dari halaman ini.',
        ]);
    }

    public function batalHapus(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'deletion_requested_at' => null,
            'deletion_scheduled_for' => null,
        ])->save();

        AuditLog::record('user.deletion.canceled', $user);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Penghapusan dibatalkan',
            'pesan' => 'Akun Anda tidak jadi dihapus. Tidak ada data yang hilang.',
        ]);
    }
}
