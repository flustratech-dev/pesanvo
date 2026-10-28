<?php

namespace App\Services\Billing;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Support\Qris;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Saluran pembayaran yang berlaku sekarang: QRIS dinamis, dicocokkan manusia.
 *
 * Tidak ada notifikasi otomatis dari mana pun. Yang menghubungkan uang masuk
 * dengan tagihan cuma dua: nominal yang dibuat unik lewat kode tiga digit, dan
 * bukti transfer yang diunggah pelanggan. Admin yang memutuskan.
 */
class QrisManual
{
    public function rawPayload(): ?string
    {
        return AppSetting::ambil('qris_payload', config('billing.qris.payload'));
    }

    /**
     * Apakah kode QR bisa ditampilkan.
     *
     * Payload yang salah ketik atau terpotong saat disalin menghasilkan kode QR
     * yang tampak wajar tapi ditolak setiap aplikasi bank. Diperiksa di sini,
     * sekali, supaya kegagalannya muncul sebagai instruksi transfer bank yang
     * benar — bukan sebagai pelanggan yang memindai kode yang tidak akan terbaca.
     */
    public function available(): bool
    {
        $payload = $this->rawPayload();

        if (blank($payload)) {
            return false;
        }

        if (! Qris::valid($payload)) {
            Log::warning('QRIS_PAYLOAD tidak lolos pemeriksaan CRC — kode QR disembunyikan.');

            return false;
        }

        return true;
    }

    /**
     * Payload QRIS dinamis untuk satu tagihan, siap dirender jadi kode QR.
     * Null berarti QRIS belum disiapkan; halaman pembayaran menampilkan
     * instruksi transfer bank sebagai gantinya.
     */
    public function payload(Invoice $invoice): ?string
    {
        if (! $this->available()) {
            return null;
        }

        try {
            return Qris::dinamis($this->rawPayload(), $invoice->total);
        } catch (\Throwable $e) {
            Log::warning('Gagal menyusun QRIS dinamis.', [
                'invoice' => $invoice->number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function merchantName(): string
    {
        return (string) AppSetting::ambil('qris_merchant_name', config('billing.qris.merchant', 'Pesanvo'));
    }

    /**
     * Seluruh rekening bank dan Virtual Account yang aktif.
     *
     * @return Collection<int, BankAccount>
     */
    public function bankAccounts(): Collection
    {
        $accounts = BankAccount::active()->get();

        if ($accounts->isNotEmpty()) {
            return $accounts;
        }

        // Fallback jika belum ada baris di database tapi config/env terisi
        $legacy = config('billing.bank');
        if (filled($legacy['account_number'] ?? null)) {
            $synthetic = new BankAccount([
                'bank_name' => $legacy['name'] ?? 'Transfer Bank',
                'account_number' => $legacy['account_number'],
                'account_holder' => $legacy['account_holder'] ?? 'Pesanvo',
                'type' => 'bank',
                'is_active' => true,
            ]);
            $synthetic->id = 0;

            return collect([$synthetic]);
        }

        return collect();
    }

    /**
     * Rekening bank tunggal pertama untuk kompatibilitas ke belakang.
     *
     * @return array{name: ?string, account_number: ?string, account_holder: ?string}|null
     */
    public function bankAccount(): ?array
    {
        $first = $this->bankAccounts()->first();

        if ($first) {
            return [
                'name' => $first->bank_name,
                'account_number' => $first->account_number,
                'account_holder' => $first->account_holder,
            ];
        }

        return null;
    }
}
