<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan penukaran kode referal, di satu tempat.
 *
 * Seluruh penolakan dikumpulkan di sini dan bukan disebar ke controller karena
 * kode yang sama diperiksa di dua tempat dengan tuntutan berbeda: halaman bayar
 * memvalidasinya lewat AJAX untuk **menampilkan potongannya sebelum tagihan
 * terbit**, dan checkout memvalidasinya lagi saat benar-benar menukar. Dua
 * daftar aturan yang ditulis terpisah akan menyimpang, dan yang menyimpang di
 * sini berarti pelanggan melihat potongan yang tidak pernah ia terima.
 *
 * Empat penolakannya, dan kenapa masing-masing ada:
 *
 * 1. **Diskon hanya untuk tagihan pertama sebuah workspace.** Tanpa ini, satu
 *    kode memotong seluruh pendapatan dari pelanggan itu selamanya — reseller
 *    dibayar sekali untuk mendatangkan orang, tapi diskonnya menempel tiap
 *    bulan sampai pelanggan itu berhenti.
 * 2. **Satu workspace, satu kode, sekali seumur hidup.**
 * 3. **Reseller tidak bisa memakai kodenya sendiri.** Kalau bisa, "komisi"
 *    berubah jadi potongan harga pribadi yang dibiayai kami.
 * 4. **Kode kedaluwarsa, mati, atau habis jatahnya ditolak.**
 */
class ReferralService
{
    /**
     * Memeriksa sebuah kode untuk sebuah workspace, tanpa menukarnya.
     *
     * Melempar `RuntimeException` dengan kalimat yang bisa langsung ditampilkan
     * ke pelanggan — tiap penolakan punya sebabnya sendiri, dan "kode tidak
     * berlaku" untuk empat keadaan yang berbeda memaksa orang menebak.
     */
    public function periksa(string $kodeMentah, Workspace $workspace): ReferralCode
    {
        $kode = ReferralCode::normalkan($kodeMentah);

        if (strlen($kode) !== 5) {
            throw new RuntimeException('Kode referal terdiri dari 5 huruf.');
        }

        $referral = ReferralCode::where('code', $kode)->first();

        if (! $referral) {
            throw new RuntimeException('Kode referal tidak dikenali. Periksa lagi ejaannya.');
        }

        if (! $referral->is_active) {
            throw new RuntimeException('Kode referal ini sudah tidak berlaku.');
        }

        if ($referral->expires_at !== null && $referral->expires_at->isPast()) {
            throw new RuntimeException('Kode referal ini kedaluwarsa pada '
                .$referral->expires_at->translatedFormat('j F Y').'.');
        }

        if (! $referral->bisaDitukar()) {
            throw new RuntimeException('Kode referal ini sudah mencapai batas pemakaiannya.');
        }

        // Diperiksa terhadap PEMILIK workspace, bukan terhadap yang sedang
        // login: anggota tim yang menekan tombolnya bisa saja orang lain,
        // sementara yang menikmati diskonnya tetap pemilik.
        if ($referral->owner_user_id === $workspace->owner_id) {
            throw new RuntimeException('Anda tidak bisa memakai kode referal milik Anda sendiri.');
        }

        if (ReferralRedemption::where('workspace_id', $workspace->id)->exists()) {
            throw new RuntimeException('Workspace ini sudah pernah memakai kode referal. '
                .'Kode referal hanya bisa dipakai sekali.');
        }

        if ($workspace->invoices()->where('status', 'paid')->exists()) {
            throw new RuntimeException('Kode referal hanya berlaku untuk tagihan pertama, '
                .'dan workspace ini sudah pernah membayar.');
        }

        return $referral;
    }

    /**
     * Sama seperti `periksa()`, tapi menjawab dengan array alih-alih melempar.
     *
     * Dipakai halaman bayar lewat AJAX: di sana penolakan bukan galat melainkan
     * jawaban yang wajar, dan pelanggan harus melihat potongannya **sebelum**
     * tagihan terbit.
     *
     * @return array{sah: bool, pesan: string, diskon?: int, persen?: int}
     */
    public function tinjau(string $kodeMentah, Workspace $workspace, int $nominal): array
    {
        try {
            $referral = $this->periksa($kodeMentah, $workspace);
        } catch (RuntimeException $e) {
            return ['sah' => false, 'pesan' => $e->getMessage()];
        }

        $diskon = $referral->potongan($nominal);

        return [
            'sah' => true,
            'persen' => $referral->discount_percent,
            'diskon' => $diskon,
            'pesan' => 'Potongan '.$referral->discount_percent.'% — hemat Rp '
                .number_format($diskon, 0, ',', '.').'.',
        ];
    }

    /**
     * Mencatat penukaran untuk sebuah tagihan yang baru terbit.
     *
     * Statusnya `pending`, dan `redeemed_count` belum naik. Keduanya baru
     * berubah saat tagihannya lunas — kode yang ditukar tapi tagihannya tidak
     * pernah dibayar tidak boleh menghabiskan jatah reseller.
     */
    public function catat(ReferralCode $referral, Invoice $invoice): ReferralRedemption
    {
        return ReferralRedemption::create([
            'referral_code_id' => $referral->id,
            'workspace_id' => $invoice->workspace_id,
            'invoice_id' => $invoice->id,
            'discount_amount' => $invoice->discount_amount,
            // Dari total yang benar-benar dibayar, bukan dari harga penuh.
            'commission_amount' => $referral->komisi($invoice->total),
            'status' => 'pending',
        ]);
    }

    /**
     * Tagihannya lunas: komisi jadi terutang, dan jatah kode baru sekarang
     * berkurang.
     *
     * Dipanggil dari `SubscriptionService::markPaid()`. Aman dipanggil dua kali
     * — penukaran yang sudah lewat `pending` tidak disentuh lagi, kalau tidak
     * `redeemed_count` naik dua kali untuk satu pembayaran.
     */
    public function setujui(Invoice $invoice): void
    {
        $redemption = ReferralRedemption::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->first();

        if (! $redemption) {
            return;
        }

        DB::transaction(function () use ($redemption) {
            $redemption->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
            ])->save();

            // increment mentah, bukan baca-tambah-tulis: dua pembayaran yang
            // dikonfirmasi bersamaan pada kode yang sama akan saling menimpa.
            ReferralCode::whereKey($redemption->referral_code_id)->increment('redeemed_count');
        });
    }

    /**
     * Tagihannya kedaluwarsa atau dibatalkan: penukarannya batal.
     *
     * `void`, bukan dihapus — workspace-nya tetap terhitung sudah pernah
     * memakai kode referal. Kalau barisnya dibuang, pelanggan yang sama bisa
     * membiarkan tagihannya kedaluwarsa lalu menukar kode lain berulang kali
     * sampai menemukan yang diskonnya paling besar.
     */
    public function batalkan(Invoice $invoice): void
    {
        ReferralRedemption::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->update(['status' => 'void']);
    }
}
