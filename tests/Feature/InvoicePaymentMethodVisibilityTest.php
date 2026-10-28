<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoicePaymentMethodVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    public function test_hanya_mayar_aktif_tidak_menampilkan_pilih_cara_pembayaran(): void
    {
        config([
            'billing.qris.payload' => null,
            'billing.bank.account_number' => null,
            'billing.bank.name' => null,
            'services.mayar.api_key' => 'dummy-api-key',
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $response = $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id));

        $response->assertOk();
        $response->assertDontSee('Pilih Cara Pembayaran:');
        $response->assertSee('Pembayaran Instan &amp; Otomatis', false);
        $response->assertSee('Pilih Pembayaran');
        $response->assertDontSee('Verifikasi Status:');
        $response->assertDontSee('Otomatis 24/7 (Tanpa Perlu Kirim Bukti)');
        $response->assertSee('images/payments/bca.svg');
        $response->assertSee('images/payments/mandiri.svg');
        $response->assertSee('images/payments/qris.svg');
    }

    public function test_jika_rekening_bank_diisi_kembali_maka_pilih_cara_pembayaran_muncul_lagi(): void
    {
        config([
            'billing.qris.payload' => null,
            'billing.bank.account_number' => null,
            'services.mayar.api_key' => 'dummy-api-key',
        ]);

        BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'PT Pesanvo',
            'type' => 'bank',
            'is_active' => true,
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $response = $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id));

        $response->assertOk();
        $response->assertSee('Pilih Cara Pembayaran:');
        $response->assertSee('Bayar Otomatis (Mayar)');
        $response->assertSee('Transfer Bank (Manual)');
    }

    public function test_jika_qris_diisi_kembali_maka_pilih_cara_pembayaran_muncul_lagi(): void
    {
        $qrisContoh = 'QRIS_PAYLOAD_PLACEHOLDER Tangerang 61051522463045BF1';

        config([
            'billing.qris.payload' => null,
            'billing.bank.account_number' => null,
            'services.mayar.api_key' => 'dummy-api-key',
        ]);

        AppSetting::simpan('qris_payload', $qrisContoh);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $response = $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id));

        $response->assertOk();
        $response->assertSee('Pilih Cara Pembayaran:');
        $response->assertSee('Bayar Otomatis (Mayar)');
        $response->assertSee('QRIS (Manual)');
    }
}
