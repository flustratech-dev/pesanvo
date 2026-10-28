<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Support\PaymentGatewaySetting;
use App\Support\Plan;
use App\Support\Qris;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengelolaan terpadu pembayaran, QRIS, rekening bank/VA, 4 payment gateway,
 * dan harga paket langsung dari antarmuka web tanpa menyentuh .env atau kode sumber.
 */
class PaymentSettingController extends Controller
{
    public function index(): View
    {
        $qrisPayload = AppSetting::ambil('qris_payload', config('billing.qris.payload'));
        $qrisMerchant = AppSetting::ambil('qris_merchant_name', config('billing.qris.merchant', 'Pesanvo'));
        $qrisValid = filled($qrisPayload) && Qris::valid($qrisPayload);

        $bankAccounts = BankAccount::orderBy('sort_order')->orderBy('id')->get();

        $gateways = [
            'mayar' => PaymentGatewaySetting::mayar(),
            'midtrans' => PaymentGatewaySetting::midtrans(),
            'xendit' => PaymentGatewaySetting::xendit(),
            'ipaymu' => PaymentGatewaySetting::ipaymu(),
            'doku' => PaymentGatewaySetting::doku(),
        ];

        $catalog = Plan::catalog();
        $yearlyMultiplier = (int) AppSetting::ambil('plans_yearly_multiplier', config('plans.yearly_multiplier', 10));
        $paygPrice = (int) AppSetting::ambil('payg_price_per_message', config('billing.payg.price_per_message', 200));
        $paygMinTopup = (int) AppSetting::ambil('payg_min_topup', config('billing.payg.min_topup', 50_000));
        $taxPercent = (float) AppSetting::ambil('billing_tax_percent', config('billing.tax_percent', 0));

        return view('admin.payment-settings.index', [
            'qrisPayload' => $qrisPayload,
            'qrisMerchant' => $qrisMerchant,
            'qrisValid' => $qrisValid,
            'bankAccounts' => $bankAccounts,
            'gateways' => $gateways,
            'catalog' => $catalog,
            'yearlyMultiplier' => $yearlyMultiplier,
            'paygPrice' => $paygPrice,
            'paygMinTopup' => $paygMinTopup,
            'taxPercent' => $taxPercent,
        ]);
    }

    public function saveQris(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payload' => ['nullable', 'string'],
            'merchant' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = trim($data['payload'] ?? '');
        $merchant = trim($data['merchant'] ?? '');

        if ($payload !== '' && ! Qris::valid($payload)) {
            return back()->withInput()->with('swal', [
                'tipe' => 'error',
                'judul' => 'Payload QRIS tidak valid',
                'pesan' => 'String payload QRIS tidak lolos pemeriksaan CRC atau format EMVCo. Pastikan seluruh string tersalin lengkap tanpa terpotong.',
            ]);
        }

        AppSetting::simpan('qris_payload', $payload !== '' ? $payload : null);
        AppSetting::simpan('qris_merchant_name', $merchant !== '' ? $merchant : null);

        AuditLog::record('settings.qris.saved', null, [
            'merchant' => $merchant,
            'has_payload' => $payload !== '',
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Pengaturan QRIS disimpan',
            'pesan' => $payload !== ''
                ? 'Payload QRIS valid dan siap digunakan di seluruh tagihan checkout.'
                : 'Payload QRIS dikosongkan. Halaman checkout akan menggunakan metode transfer bank.',
        ]);
    }

    public function storeBank(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:bank,va'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $bank = BankAccount::create([
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_holder' => $data['account_holder'],
            'type' => $data['type'],
            'instructions' => $data['instructions'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        AuditLog::record('settings.bank.created', $bank, [
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'type' => $bank->type,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Rekening berhasil ditambahkan',
            'pesan' => "{$bank->bank_name} ({$bank->account_number}) sekarang aktif dan akan muncul di checkout tagihan.",
        ]);
    }

    public function updateBank(Request $request, int $id): RedirectResponse
    {
        $bank = BankAccount::findOrFail($id);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:bank,va'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $bank->update([
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_holder' => $data['account_holder'],
            'type' => $data['type'],
            'instructions' => $data['instructions'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        AuditLog::record('settings.bank.updated', $bank, [
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Rekening diperbarui',
            'pesan' => "Perubahan pada rekening {$bank->bank_name} berhasil disimpan.",
        ]);
    }

    public function toggleBank(Request $request, int $id): RedirectResponse
    {
        $bank = BankAccount::findOrFail($id);
        $bank->forceFill(['is_active' => ! $bank->is_active])->save();

        AuditLog::record('settings.bank.toggled', $bank, [
            'bank_name' => $bank->bank_name,
            'is_active' => $bank->is_active,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => $bank->is_active ? 'Rekening diaktifkan' : 'Rekening dinonaktifkan',
            'pesan' => $bank->is_active
                ? "{$bank->bank_name} sekarang muncul di halaman checkout pelanggan."
                : "{$bank->bank_name} disembunyikan dari halaman checkout pelanggan.",
        ]);
    }

    public function destroyBank(Request $request, int $id): RedirectResponse
    {
        $bank = BankAccount::findOrFail($id);

        AuditLog::record('settings.bank.deleted', $bank, [
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
        ]);

        $bank->delete();

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Rekening dihapus',
            'pesan' => 'Rekening telah dihapus dari daftar pilihan pembayaran.',
        ]);
    }

    public function saveGateways(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Mayar
            'mayar_active' => ['nullable', 'boolean'],
            'mayar_api_key' => ['nullable', 'string'],
            'mayar_api_url' => ['nullable', 'string', 'max:255'],
            'mayar_webhook_token' => ['nullable', 'string', 'max:255'],

            // Midtrans
            'midtrans_active' => ['nullable', 'boolean'],
            'midtrans_environment' => ['nullable', 'in:sandbox,production'],
            'midtrans_merchant_id' => ['nullable', 'string', 'max:100'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_snap_url' => ['nullable', 'string', 'max:255'],

            // Xendit
            'xendit_active' => ['nullable', 'boolean'],
            'xendit_environment' => ['nullable', 'in:sandbox,production'],
            'xendit_secret_key' => ['nullable', 'string', 'max:255'],
            'xendit_public_key' => ['nullable', 'string', 'max:255'],
            'xendit_webhook_token' => ['nullable', 'string', 'max:255'],
            'xendit_base_url' => ['nullable', 'string', 'max:255'],

            // iPaymu
            'ipaymu_active' => ['nullable', 'boolean'],
            'ipaymu_environment' => ['nullable', 'in:sandbox,production'],
            'ipaymu_va_number' => ['nullable', 'string', 'max:100'],
            'ipaymu_api_key' => ['nullable', 'string', 'max:255'],
            'ipaymu_base_url' => ['nullable', 'string', 'max:255'],

            // DOKU
            'doku_active' => ['nullable', 'boolean'],
            'doku_environment' => ['nullable', 'in:sandbox,production'],
            'doku_client_id' => ['nullable', 'string', 'max:100'],
            'doku_secret_key' => ['nullable', 'string', 'max:255'],
            'doku_base_url' => ['nullable', 'string', 'max:255'],
        ]);

        // Simpan Mayar
        AppSetting::simpan('gateway_mayar', json_encode([
            'is_active' => (bool) ($data['mayar_active'] ?? false),
            'api_key' => trim($data['mayar_api_key'] ?? ''),
            'api_url' => trim($data['mayar_api_url'] ?? 'https://api.mayar.id/hl/v2'),
            'webhook_token' => trim($data['mayar_webhook_token'] ?? ''),
        ]));

        // Simpan Midtrans
        AppSetting::simpan('gateway_midtrans', json_encode([
            'is_active' => (bool) ($data['midtrans_active'] ?? false),
            'environment' => $data['midtrans_environment'] ?? 'sandbox',
            'merchant_id' => trim($data['midtrans_merchant_id'] ?? ''),
            'client_key' => trim($data['midtrans_client_key'] ?? ''),
            'server_key' => trim($data['midtrans_server_key'] ?? ''),
            'snap_url' => trim($data['midtrans_snap_url'] ?? ''),
        ]));

        // Simpan Xendit
        AppSetting::simpan('gateway_xendit', json_encode([
            'is_active' => (bool) ($data['xendit_active'] ?? false),
            'environment' => $data['xendit_environment'] ?? 'sandbox',
            'secret_key' => trim($data['xendit_secret_key'] ?? ''),
            'public_key' => trim($data['xendit_public_key'] ?? ''),
            'webhook_token' => trim($data['xendit_webhook_token'] ?? ''),
            'base_url' => trim($data['xendit_base_url'] ?? 'https://api.xendit.co'),
        ]));

        // Simpan iPaymu
        AppSetting::simpan('gateway_ipaymu', json_encode([
            'is_active' => (bool) ($data['ipaymu_active'] ?? false),
            'environment' => $data['ipaymu_environment'] ?? 'sandbox',
            'va_number' => trim($data['ipaymu_va_number'] ?? ''),
            'api_key' => trim($data['ipaymu_api_key'] ?? ''),
            'base_url' => trim($data['ipaymu_base_url'] ?? ''),
        ]));

        // Simpan DOKU
        AppSetting::simpan('gateway_doku', json_encode([
            'is_active' => (bool) ($data['doku_active'] ?? false),
            'environment' => $data['doku_environment'] ?? 'sandbox',
            'client_id' => trim($data['doku_client_id'] ?? ''),
            'secret_key' => trim($data['doku_secret_key'] ?? ''),
            'base_url' => trim($data['doku_base_url'] ?? ''),
        ]));

        AuditLog::record('settings.gateways.saved', null, [
            'mayar' => (bool) ($data['mayar_active'] ?? false),
            'midtrans' => (bool) ($data['midtrans_active'] ?? false),
            'xendit' => (bool) ($data['xendit_active'] ?? false),
            'ipaymu' => (bool) ($data['ipaymu_active'] ?? false),
            'doku' => (bool) ($data['doku_active'] ?? false),
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Konfigurasi payment gateway disimpan',
            'pesan' => 'Kredensial dan pengaturan Mayar, Midtrans, Xendit, iPaymu, dan DOKU berhasil disimpan.',
        ]);
    }

    public function savePrices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.price_monthly' => ['required', 'integer', 'min:0'],
            'plans.*.intro_price_monthly' => ['nullable', 'integer', 'min:0'],
            'plans.*.intro_price_yearly' => ['nullable', 'integer', 'min:0'],

            'yearly_multiplier' => ['required', 'integer', 'min:1', 'max:24'],
            'payg_price_per_message' => ['required', 'integer', 'min:1'],
            'payg_min_topup' => ['required', 'integer', 'min:1000'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Simpan harga per paket
        AppSetting::simpan('plan_prices', json_encode($data['plans']));

        // Simpan kebijakan harga global
        AppSetting::simpan('plans_yearly_multiplier', $data['yearly_multiplier']);
        AppSetting::simpan('payg_price_per_message', $data['payg_price_per_message']);
        AppSetting::simpan('payg_min_topup', $data['payg_min_topup']);
        AppSetting::simpan('billing_tax_percent', $data['tax_percent']);

        AuditLog::record('settings.prices.saved', null, [
            'yearly_multiplier' => $data['yearly_multiplier'],
            'payg_price' => $data['payg_price_per_message'],
            'tax_percent' => $data['tax_percent'],
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Harga paket berhasil disimpan',
            'pesan' => 'Perubahan harga langsung berlaku di seluruh halaman harga, tagihan, dan checkout.',
        ]);
    }
}
