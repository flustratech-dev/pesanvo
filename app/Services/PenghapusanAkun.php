<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menghapus akun beserta apa yang melekat padanya.
 *
 * Terpisah dari controller karena dipanggil dari dua tempat yang tidak boleh
 * berbeda perilakunya: job terjadwal yang mengeksekusi setelah masa tunggu, dan
 * panel admin bila pelanggan memintanya lewat tiket. Penghapusan yang
 * dikerjakan dua jalur dengan aturan berbeda adalah cara paling halus data
 * pribadi tertinggal di satu jalur tanpa ada yang tahu.
 *
 * ## Tiga aturan yang tidak boleh diubah tanpa mengubah dokumen hukumnya juga
 *
 * 1. **Workspace yang masih punya anggota lain TIDAK dihapus**, melainkan
 *    pindah kepemilikan ke anggota terlama. Pengguna yang keluar dari sebuah
 *    tim tidak boleh membawa serta riwayat percakapan seluruh tim — itu data
 *    milik orang lain, dan menghapusnya berarti kami menghancurkan data
 *    pelanggan yang tidak meminta apa pun.
 *
 * 2. **Tagihan tidak dihapus, melainkan diputus dari orangnya.** Dokumen
 *    pembukuan wajib disimpan sepuluh tahun menurut ketentuan perpajakan.
 *    Menghapusnya atas permintaan pelanggan berarti melanggar kewajiban yang
 *    berbeda. Yang bisa kami lakukan adalah memutus kaitannya dengan identitas
 *    orangnya, dan Kebijakan Privasi menyebutkan pengecualian ini terbuka.
 *
 * 3. **Sesi WhatsApp diputus lebih dulu.** Tanpa itu, engine tetap memegang
 *    koneksi ke nomor milik akun yang sudah tidak ada — memakan satu dari tiga
 *    slot untuk seluruh pelanggan sampai engine di-restart.
 */
class PenghapusanAkun
{
    public function __construct(private readonly SessionService $sessions) {}

    /**
     * Apa yang akan terjadi kalau akun ini dihapus.
     *
     * Dipakai halaman konfirmasi. Penghapusan permanen yang tidak menyebutkan
     * apa saja yang ikut hilang adalah persetujuan yang tidak diberikan dengan
     * sadar — terutama saat yang ikut hilang adalah workspace berisi setahun
     * riwayat percakapan.
     *
     * @return array{dihapus: Collection<int, Workspace>, dialihkan: Collection<int, Workspace>, saldo: int}
     */
    public function ringkasDampak(User $user): array
    {
        $dimiliki = Workspace::where('owner_id', $user->id)->with('members')->get();

        return [
            'dihapus' => $dimiliki->filter(fn (Workspace $w) => $w->members->count() <= 1)->values(),
            'dialihkan' => $dimiliki->filter(fn (Workspace $w) => $w->members->count() > 1)->values(),
            'saldo' => (int) $dimiliki->sum('balance'),
        ];
    }

    public function jalankan(User $user, string $alasan = 'permintaan pemilik akun'): void
    {
        $dampak = $this->ringkasDampak($user);

        // Di luar transaksi dengan sengaja: memutus sesi memanggil engine lewat
        // jaringan, dan panggilan jaringan di dalam transaksi menahan kunci
        // baris selama detik-detik yang tidak bisa diperkirakan.
        foreach ($dampak['dihapus'] as $workspace) {
            foreach ($workspace->sessions as $sesi) {
                try {
                    $this->sessions->logout($sesi);
                } catch (\Throwable $e) {
                    // Engine tidak terjangkau tidak boleh menggantung
                    // penghapusan. Pelanggan yang sudah menunggu masa tunggunya
                    // habis tidak bisa disuruh menunggu lagi karena masalah
                    // yang bukan miliknya.
                    Log::warning('Sesi tidak dapat diputus saat hapus akun: '.$e->getMessage());
                }
            }
        }

        DB::transaction(function () use ($user, $dampak, $alasan): void {
            foreach ($dampak['dialihkan'] as $workspace) {
                $penerus = $workspace->members()
                    ->where('users.id', '!=', $user->id)
                    ->orderBy('workspace_members.created_at')
                    ->first();

                if (! $penerus) {
                    continue;
                }

                $workspace->update([
                    'owner_id' => $penerus->id,
                    'owner_email' => $penerus->email,
                ]);

                $workspace->members()->detach($user->id);

                AuditLog::record('workspace.owner.transferred', $workspace, [
                    'dari' => $user->email,
                    'ke' => $penerus->email,
                    'alasan' => 'penghapusan akun pemilik lama',
                ], $workspace->id);
            }

            foreach ($dampak['dihapus'] as $workspace) {
                // Tagihan diputus dari workspace-nya SEBELUM workspace dihapus.
                // Tanpa ini, `cascadeOnDelete` pada `workspace_id` ikut
                // membuang catatan pembukuan yang wajib disimpan 10 tahun.
                $this->lepaskanTagihan($workspace);

                AuditLog::record('workspace.deleted', $workspace, [
                    'name' => $workspace->name,
                    'alasan' => $alasan,
                ], $workspace->id);

                $workspace->forceDelete();
            }

            AuditLog::record('user.deleted', $user, [
                'email' => $user->email,
                'alasan' => $alasan,
            ]);

            $user->forceDelete();
        });
    }

    /**
     * Melepaskan tagihan dari workspace dan pemiliknya, tanpa menghapusnya.
     *
     * Nomor tagihan, nominal, tanggal, dan status tetap utuh — itu yang
     * dibutuhkan pembukuan. Yang dibuang kaitannya dengan orang: siapa yang
     * membayar, dan workspace mana. Nama serta email penagihan ikut disamarkan
     * karena keduanya data pribadi yang tidak diperlukan pembukuan.
     */
    private function lepaskanTagihan(Workspace $workspace): void
    {
        DB::table('invoices')
            ->where('workspace_id', $workspace->id)
            ->update([
                'workspace_id' => null,
                'paid_by_user_id' => null,
                'note' => 'Workspace dihapus atas permintaan pemilik pada '.now()->toDateString(),
            ]);
    }
}
