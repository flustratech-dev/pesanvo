<?php

namespace App\Services\Billing;

use App\Models\BalanceTransaction;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat saldo berpindah.
 *
 * Aturannya sama dengan `SubscriptionService` untuk status langganan, dan
 * alasannya sama: dua tempat yang menulis angka yang sama akan menyimpang, dan
 * yang menyimpang di sini adalah uang pelanggan. Tidak ada controller, job,
 * atau perintah yang boleh menulis `workspaces.balance` sendiri.
 *
 * **Setiap perubahan saldo menulis dua hal sekaligus, di dalam satu
 * transaksi:** kolom `workspaces.balance` dan satu baris di
 * `balance_transactions`. Keduanya harus selalu sepakat — `rekonsiliasi()`
 * memeriksanya, dan panel admin menampilkan hasilnya. Saldo yang tidak bisa
 * direkonsiliasi adalah uang yang tidak bisa dipertanggungjawabkan.
 *
 * Rupiah penuh, bilangan bulat, tidak pernah float.
 */
class BalanceService
{
    public function hargaPerPesan(): int
    {
        return (int) config('billing.payg.price_per_message');
    }

    public function minimumTopup(): int
    {
        return (int) config('billing.payg.min_topup');
    }

    /**
     * Menambah saldo setelah tagihan topup ditandai lunas.
     *
     * Dipanggil HANYA dari `SubscriptionService::markPaid()`. Aman dipanggil dua
     * kali untuk tagihan yang sama: barisnya dijaga supaya tidak ganda, karena
     * panel admin memang mengizinkan menandai lunas tagihan yang sudah ditutup —
     * dan saldo yang bertambah dua kali dari satu pembayaran adalah uang yang
     * kami berikan tanpa ada yang membayarnya.
     */
    public function topUp(Workspace $workspace, int $jumlah, Invoice $invoice, ?User $oleh = null): ?BalanceTransaction
    {
        if ($jumlah <= 0) {
            throw new RuntimeException('Jumlah isi saldo harus lebih dari nol.');
        }

        if (BalanceTransaction::where('invoice_id', $invoice->id)->where('type', 'topup')->exists()) {
            return null;
        }

        return $this->tulis($workspace, 'topup', $jumlah, [
            'invoice_id' => $invoice->id,
            'note' => "Isi saldo lewat tagihan {$invoice->number}",
            'created_by' => $oleh?->id,
        ]);
    }

    /**
     * Memotong saldo untuk satu pesan yang BENAR-BENAR terkirim.
     *
     * Dipanggil dari `SendMessageJob` setelah pengiriman berhasil, bukan saat
     * pesan diantrekan. Bedanya penting: pesan yang gagal karena nomornya tidak
     * terdaftar tidak boleh memotong saldo pelanggan.
     *
     * Konsekuensi yang diterima sadar: broadcast besar bisa membuat saldo minus
     * sedikit, karena beberapa pesan berjalan bersamaan dan masing-masing sudah
     * lolos pemeriksaan saat antre. Itu sebabnya saldo diperiksa DUA kali — saat
     * antre dan saat kirim — dan sebabnya kolomnya `bigInteger` bertanda, bukan
     * unsigned: saldo minus yang tidak bisa disimpan akan gagal dengan galat
     * database di dalam job, dan pesan yang sudah telanjur terkirim jadi tidak
     * pernah tercatat memotong apa pun.
     *
     * Mengembalikan `null` kalau pesan ini sudah pernah memotong saldo. Job bisa
     * dijalankan ulang setelah gagal di tengah, dan pemotongan ganda untuk satu
     * pesan adalah uang pelanggan yang hilang tanpa jejak. Indeks unik
     * `(workspace_id, message_id)` menjaganya bahkan saat dua worker berlomba.
     */
    public function chargeMessage(Workspace $workspace, Message $message): ?BalanceTransaction
    {
        $harga = $this->hargaPerPesan();

        if ($harga <= 0) {
            return null;
        }

        try {
            return $this->tulis($workspace, 'charge', -$harga, [
                'message_id' => $message->id,
                'note' => 'Pengiriman ke '.$message->to_number,
            ]);
        } catch (QueryException $e) {
            // Indeks unik menolak: pesan ini sudah pernah memotong saldo.
            // Bukan kegagalan — justru penjagaan yang bekerja.
            if ($this->pelanggaranUnik($e)) {
                return null;
            }

            throw $e;
        }
    }

    /** Mengembalikan saldo, mis. saat pesan yang sudah dipotong ternyata gagal. */
    public function refund(Workspace $workspace, int $jumlah, string $catatan, ?Message $message = null): BalanceTransaction
    {
        return $this->tulis($workspace, 'refund', abs($jumlah), [
            'message_id' => $message?->id,
            'note' => $catatan,
        ]);
    }

    /** Koreksi manual dari panel admin. Selalu meninggalkan jejak siapa dan kenapa. */
    public function adjust(Workspace $workspace, int $jumlah, string $catatan, ?User $oleh = null): BalanceTransaction
    {
        if ($jumlah === 0) {
            throw new RuntimeException('Penyesuaian nol tidak menghasilkan apa pun.');
        }

        return $this->tulis($workspace, 'adjustment', $jumlah, [
            'note' => $catatan,
            'created_by' => $oleh?->id,
        ]);
    }

    /**
     * Apakah saldo tercatat sama dengan jumlah seluruh mutasinya.
     *
     * Ini bukan hiasan. Tanpa cara memeriksanya, satu baris yang hilang atau
     * tertulis dua kali baru ketahuan dari keluhan pelanggan — dan saat itu
     * tidak ada cara tahu sejak kapan atau berapa.
     *
     * @return array{cocok: bool, saldo: int, bukuBesar: int, selisih: int}
     */
    public function rekonsiliasi(Workspace $workspace): array
    {
        $saldo = (int) $workspace->balance;
        $bukuBesar = (int) BalanceTransaction::where('workspace_id', $workspace->id)->sum('amount');

        return [
            'cocok' => $saldo === $bukuBesar,
            'saldo' => $saldo,
            'bukuBesar' => $bukuBesar,
            'selisih' => $saldo - $bukuBesar,
        ];
    }

    /**
     * Seluruh workspace PAYG yang saldonya tidak cocok dengan buku besarnya.
     *
     * Dipakai halaman Sistem. Dihitung dengan satu kueri agregat, bukan dengan
     * memuat tiap workspace: halaman itu harus tetap terbuka cepat justru saat
     * ada yang tidak beres.
     *
     * @return Collection<int, object>
     */
    public function workspaceTidakCocok()
    {
        return Workspace::query()
            ->where('billing_mode', 'payg')
            ->leftJoin('balance_transactions', 'balance_transactions.workspace_id', '=', 'workspaces.id')
            ->groupBy('workspaces.id', 'workspaces.name', 'workspaces.balance')
            ->havingRaw('COALESCE(SUM(balance_transactions.amount), 0) <> workspaces.balance')
            ->get([
                'workspaces.id',
                'workspaces.name',
                'workspaces.balance',
                DB::raw('COALESCE(SUM(balance_transactions.amount), 0) as buku_besar'),
            ]);
    }

    /**
     * Menulis satu mutasi dan memperbarui saldo, sebagai satu kesatuan.
     *
     * `lockForUpdate()` bukan kehati-hatian berlebihan: pengiriman berjalan di
     * beberapa worker sekaligus, dan dua pemotongan yang membaca saldo yang sama
     * lalu menulisnya kembali akan kehilangan salah satunya — saldo berkurang
     * satu kali untuk dua pesan yang keduanya terkirim.
     */
    private function tulis(Workspace $workspace, string $jenis, int $jumlah, array $tambahan = []): BalanceTransaction
    {
        return DB::transaction(function () use ($workspace, $jenis, $jumlah, $tambahan) {
            $terkunci = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            $saldoBaru = (int) $terkunci->balance + $jumlah;

            $terkunci->forceFill(['balance' => $saldoBaru])->save();

            $mutasi = BalanceTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => $jenis,
                'amount' => $jumlah,
                'balance_after' => $saldoBaru,
            ] + $tambahan);

            // Objek yang dipegang pemanggil ikut diperbarui, kalau tidak ia
            // masih membawa saldo lama dan pemeriksaan berikutnya salah.
            $workspace->balance = $saldoBaru;

            return $mutasi;
        });
    }

    private function pelanggaranUnik(QueryException $e): bool
    {
        // 23000 di MySQL, 23505/'UNIQUE constraint failed' di SQLite.
        return $e->getCode() === '23000'
            || $e->getCode() === '23505'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
