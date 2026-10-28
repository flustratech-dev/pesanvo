<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PaymentGatewaySetting;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelanggan;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->admin = User::create([
            'name' => 'Pesanvo Admin',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->pelanggan = User::create([
            'name' => 'Owner Customer',
            'email' => 'ryan@customer.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => false,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Bisnis Customer',
            'slug' => 'bisnis-customer',
            'owner_id' => $this->pelanggan->id,
            'owner_email' => $this->pelanggan->email,
            'max_sessions' => 2,
            'monthly_message_quota' => 5000,
        ]);

        $this->workspace->members()->attach($this->pelanggan->id, ['role' => 'owner']);
    }

    private function validQrisPayload(): string
    {
        $isi = '000201'
            .'010211'
            .'26370014ID.CO.QRIS.WWW0215ID1234567890123'
            .'52045812'
            .'5303360'
            .'5802ID'
            .'5908PESANVO'
            .'6007JAKARTA'
            .'6304';

        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($isi); $i < $n; $i++) {
            $crc ^= ord($isi[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $isi.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function test_non_admin_cannot_access_payment_settings(): void
    {
        $response = $this->actingAs($this->pelanggan)
            ->get(route('admin.payment-settings'));

        $response->assertStatus(404);
    }

    public function test_super_admin_can_view_payment_settings_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.payment-settings'));

        $response->assertStatus(200);
        $response->assertSee('Metode Bayar & Paket');
        $response->assertSee('QRIS & Rekening / VA');
        $response->assertSee('Payment Gateways');
        $response->assertSee('Harga Paket');
    }

    public function test_super_admin_can_save_valid_qris_payload(): void
    {
        $payload = $this->validQrisPayload();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.qris'), [
                'payload' => $payload,
                'merchant' => 'PT Pesanvo Digital',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('swal.tipe', 'success');

        $this->assertEquals($payload, AppSetting::ambil('qris_payload'));
        $this->assertEquals('PT Pesanvo Digital', AppSetting::ambil('qris_merchant_name'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.qris.saved',
        ]);
    }

    public function test_invalid_qris_payload_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.qris'), [
                'payload' => 'payload-sembarangan-tanpa-crc',
                'merchant' => 'Invalid QRIS',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('swal.tipe', 'error');
        $this->assertNull(AppSetting::ambil('qris_payload'));
    }

    public function test_bank_accounts_crud(): void
    {
        // 1. Create standard Bank Account
        $storeBank = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.bank.store'), [
                'bank_name' => 'Bank Central Asia (BCA)',
                'account_number' => '1234567890',
                'account_holder' => 'PT Pesanvo Media',
                'type' => 'bank',
                'instructions' => 'Transfer manual dan konfirmasi',
                'is_active' => '1',
                'sort_order' => 1,
            ]);

        $storeBank->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', [
            'bank_name' => 'Bank Central Asia (BCA)',
            'account_number' => '1234567890',
            'type' => 'bank',
            'is_active' => true,
        ]);

        // 2. Create Virtual Account
        $storeVa = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.bank.store'), [
                'bank_name' => 'BCA Virtual Account',
                'account_number' => '8800012345678',
                'account_holder' => 'Pesanvo VA',
                'type' => 'va',
                'instructions' => 'Bayar via menu Virtual Account',
                'is_active' => '1',
                'sort_order' => 2,
            ]);

        $storeVa->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', [
            'account_number' => '8800012345678',
            'type' => 'va',
        ]);

        $bank = BankAccount::where('account_number', '1234567890')->first();
        $this->assertNotNull($bank);

        // 3. Update Bank
        $updateRes = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.bank.update', $bank->id), [
                'bank_name' => 'Bank Mandiri',
                'account_number' => '9876543210',
                'account_holder' => 'PT Pesanvo Baru',
                'type' => 'bank',
                'instructions' => 'Gunakan ATM atau Livin Mandiri',
                'is_active' => '1',
                'sort_order' => 5,
            ]);

        $updateRes->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bank->id,
            'bank_name' => 'Bank Mandiri',
            'account_number' => '9876543210',
        ]);

        // 4. Toggle Active
        $toggleRes = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.bank.toggle', $bank->id));

        $toggleRes->assertRedirect();
        $this->assertFalse((bool) $bank->fresh()->is_active);

        // 5. Delete Bank
        $deleteRes = $this->actingAs($this->admin)
            ->delete(route('admin.payment-settings.bank.destroy', $bank->id));

        $deleteRes->assertRedirect();
        $this->assertDatabaseMissing('bank_accounts', [
            'id' => $bank->id,
        ]);
    }

    public function test_active_bank_and_va_appear_on_customer_checkout_page(): void
    {
        // Seed active bank and active VA
        BankAccount::create([
            'bank_name' => 'Bank Rakyat Indonesia (BRI)',
            'account_number' => '001122334455',
            'account_holder' => 'PT Pesanvo Checkout Test',
            'type' => 'bank',
            'instructions' => 'Transfer via ATM BRI / BRImo',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        BankAccount::create([
            'bank_name' => 'Mandiri Virtual Account',
            'account_number' => '89000888999',
            'account_holder' => 'Pesanvo VA Service',
            'type' => 'va',
            'instructions' => 'Transfer bayar via Livin Mandiri Menu Multipayment',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Non-active bank should not show
        BankAccount::create([
            'bank_name' => 'Bank Permata Tidak Aktif',
            'account_number' => '99999999',
            'account_holder' => 'Hidden',
            'type' => 'bank',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $invoice = Invoice::create([
            'workspace_id' => $this->workspace->id,
            'external_id' => (string) Str::ulid(),
            'number' => 'INV-TEST-001',
            'plan_slug' => 'essentials',
            'period' => 'monthly',
            'amount' => 149_000,
            'unique_code' => 123,
            'total' => 149_123,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->pelanggan)
            ->get(route('billing.invoice', $invoice->id));

        $response->assertStatus(200);
        $response->assertSee('Bank Rakyat Indonesia (BRI)');
        $response->assertSee('001122334455');
        $response->assertSee('Mandiri Virtual Account');
        $response->assertSee('89000888999');
        $response->assertDontSee('Bank Permata Tidak Aktif');
    }

    public function test_save_payment_gateway_credentials(): void
    {
        $payload = [
            // Mayar
            'mayar_active' => '1',
            'mayar_api_key' => 'mayar_jwt_token_test_123',
            'mayar_webhook_token' => 'wh_mayar_secret_xyz',
            'mayar_api_url' => 'https://api.mayar.id/hl/v2',

            // Midtrans
            'midtrans_active' => '1',
            'midtrans_environment' => 'production',
            'midtrans_merchant_id' => 'G123456789',
            'midtrans_client_key' => 'Mid-client-AbCdEf123456',
            'midtrans_server_key' => 'Mid-server-GhIjKl789012',
            'midtrans_snap_url' => 'https://app.midtrans.com/snap/v1/transactions',

            // Xendit
            'xendit_active' => '1',
            'xendit_environment' => 'sandbox',
            'xendit_secret_key' => 'xnd_development_SecretKey123',
            'xendit_public_key' => 'xnd_public_123',
            'xendit_webhook_token' => 'wh_token_abc',
            'xendit_base_url' => 'https://api.xendit.co',

            // iPaymu
            'ipaymu_active' => '1',
            'ipaymu_environment' => 'sandbox',
            'ipaymu_va_number' => '0000001234567890',
            'ipaymu_api_key' => 'SANDBOX-IPAYMU-KEY-12345',
            'ipaymu_base_url' => 'https://sandbox.ipaymu.com/api/v2',

            // DOKU
            'doku_active' => '1',
            'doku_environment' => 'sandbox',
            'doku_client_id' => 'MALLID123456',
            'doku_secret_key' => 'SK-DOKU-SECRET-1234',
            'doku_base_url' => 'https://api-sandbox.doku.com',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.gateways'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('swal.tipe', 'success');

        // Check helper readings
        $mayar = PaymentGatewaySetting::mayar();
        $this->assertTrue($mayar['is_active']);
        $this->assertEquals('mayar_jwt_token_test_123', $mayar['api_key']);
        $this->assertEquals('wh_mayar_secret_xyz', $mayar['webhook_token']);

        $midtrans = PaymentGatewaySetting::midtrans();
        $this->assertTrue($midtrans['is_active']);
        $this->assertEquals('production', $midtrans['environment']);
        $this->assertEquals('G123456789', $midtrans['merchant_id']);
        $this->assertEquals('Mid-client-AbCdEf123456', $midtrans['client_key']);

        $xendit = PaymentGatewaySetting::xendit();
        $this->assertTrue($xendit['is_active']);
        $this->assertEquals('xnd_development_SecretKey123', $xendit['secret_key']);

        $ipaymu = PaymentGatewaySetting::ipaymu();
        $this->assertTrue($ipaymu['is_active']);
        $this->assertEquals('SANDBOX-IPAYMU-KEY-12345', $ipaymu['api_key']);

        $doku = PaymentGatewaySetting::doku();
        $this->assertTrue($doku['is_active']);
        $this->assertEquals('MALLID123456', $doku['client_id']);
    }

    public function test_save_dynamic_plan_prices_modifies_catalog_instantly(): void
    {
        $data = [
            'plans' => [
                'essentials' => [
                    'price_monthly' => 199_000,
                    'intro_price_monthly' => 99_000,
                    'intro_price_yearly' => 990_000,
                ],
                'prime' => [
                    'price_monthly' => 399_000,
                    'intro_price_monthly' => 249_000,
                    'intro_price_yearly' => 2_490_000,
                ],
                'elite' => [
                    'price_monthly' => 799_000,
                    'intro_price_monthly' => null,
                    'intro_price_yearly' => null,
                ],
            ],
            'yearly_multiplier' => 11, // 11 bulan bayar setahun
            'payg_price_per_message' => 175,
            'payg_min_topup' => 75_000,
            'tax_percent' => 11,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.payment-settings.prices'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('swal.tipe', 'success');

        // Test dynamic Plan object without touching config/plans.php
        $essentials = Plan::get('essentials');
        $this->assertEquals(199_000, $essentials->price('monthly'));
        $this->assertEquals(199_000 * 11, $essentials->price('yearly'));
        $this->assertEquals(99_000, $essentials->introPrice('monthly'));
        $this->assertEquals(990_000, $essentials->introPrice('yearly'));

        $prime = Plan::get('prime');
        $this->assertEquals(399_000, $prime->price('monthly'));

        $this->assertEquals(175, AppSetting::ambil('payg_price_per_message'));
        $this->assertEquals(75_000, AppSetting::ambil('payg_min_topup'));
        $this->assertEquals(11, AppSetting::ambil('billing_tax_percent'));
    }
}
