<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\Payment\MayarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MayarPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.mayar.api_key', 'test_api_key_123');
        Config::set('services.mayar.api_url', 'https://api.mayar.id/hl/v2');
        Config::set('services.mayar.webhook_token', 'test_webhook_token_secret');

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Pesanvo',
            'slug' => 'toko-pesanvo',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'billing_type' => 'individu',
            'billing_name' => 'Owner',
            'billing_email' => 'budi@contoh.id',
            'billing_phone' => '6281234567890',
            'billing_bank_name' => 'Bank Central Asia (BCA)',
            'billing_bank_account' => '1234567890',
            'billing_bank_holder' => 'Owner',
            'billing_province' => 'DKI Jakarta',
            'billing_city' => 'Jakarta Selatan',
            'billing_district' => 'Kebayoran Baru',
            'billing_address' => 'Jl. Sudirman No. 1',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    private function terbitkanTagihan(): Invoice
    {
        return app(SubscriptionService::class)->issueInvoice(
            $this->workspace,
            'essentials',
            'monthly'
        );
    }

    public function test_mayar_service_create_invoice(): void
    {
        $invoice = $this->terbitkanTagihan();

        Http::fake([
            '*/invoices/create' => Http::response([
                'statusCode' => 200,
                'messages' => 'success',
                'data' => [
                    'id' => 'mayar-inv-12345',
                    'transactionId' => 'tx-abc-987',
                    'link' => 'https://mayar.link/invoices/demo123',
                ],
            ], 200),
        ]);

        $service = app(MayarService::class);
        $result = $service->createInvoice($invoice, $this->workspace);

        $this->assertEquals('https://mayar.link/invoices/demo123', $result['link']);
        $this->assertEquals('mayar-inv-12345', $result['id']);

        $invoice->refresh();
        $this->assertEquals('mayar', $invoice->payment_gateway);
        $this->assertEquals('mayar-inv-12345', $invoice->payment_reference);
        $this->assertEquals('https://mayar.link/invoices/demo123', $invoice->payment_url);
    }

    public function test_endpoint_pay_with_mayar_returns_checkout_link(): void
    {
        $invoice = $this->terbitkanTagihan();

        Http::fake([
            '*/invoices/create' => Http::response([
                'statusCode' => 200,
                'messages' => 'success',
                'data' => [
                    'id' => 'mayar-inv-12345',
                    'transactionId' => 'tx-abc-987',
                    'link' => 'https://mayar.link/invoices/demo123',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('billing.invoice.mayar', $invoice->id));

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'payment_url' => 'https://mayar.link/invoices/demo123',
                'mayar_id' => 'mayar-inv-12345',
            ]);
    }

    public function test_webhook_rejects_unauthorized_token(): void
    {
        $response = $this->postJson(route('api.webhooks.mayar'), [
            'event' => 'payment.received',
        ], [
            'x-mayar-token' => 'token_salah',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_payment_received_marks_invoice_paid(): void
    {
        $invoice = $this->terbitkanTagihan();
        $invoice->update([
            'payment_reference' => 'mayar-inv-12345',
        ]);

        $this->assertFalse($invoice->isPaid());

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-inv-12345',
                'status' => true,
                'amount' => $invoice->total,
                'paymentMethod' => 'qris',
                'extraData' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.mayar'), $payload, [
            'x-mayar-token' => 'test_webhook_token_secret',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertEquals('mayar', $invoice->channel);
        $this->assertNotNull($invoice->paid_at);

        // Verifikasi log tercatat
        $this->assertDatabaseHas('payment_gateway_logs', [
            'gateway' => 'mayar',
            'event' => 'payment.received',
            'invoice_id' => $invoice->id,
            'status' => 'processed',
        ]);
    }

    public function test_webhook_is_idempotent_on_duplicate_event(): void
    {
        $invoice = $this->terbitkanTagihan();
        $invoice->update([
            'payment_reference' => 'mayar-inv-12345',
        ]);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-inv-12345',
                'status' => true,
                'amount' => $invoice->total,
                'paymentMethod' => 'va/bca',
                'extraData' => [
                    'invoice_id' => $invoice->id,
                ],
            ],
        ];

        // Webhook pertama
        $res1 = $this->postJson(route('api.webhooks.mayar'), $payload, [
            'x-mayar-token' => 'test_webhook_token_secret',
        ]);
        $res1->assertOk()->assertJson(['status' => 'success']);

        // Webhook kedua (duplikat)
        $res2 = $this->postJson(route('api.webhooks.mayar'), $payload, [
            'x-mayar-token' => 'test_webhook_token_secret',
        ]);
        $res2->assertOk()->assertJson(['status' => 'already_paid']);

        // Status invoice tetap lunas tanpa error
        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_webhook_accepts_hmac_signature(): void
    {
        $invoice = $this->terbitkanTagihan();
        $invoice->update([
            'payment_reference' => 'mayar-inv-hmac-1',
        ]);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-inv-hmac-1',
                'status' => true,
                'amount' => $invoice->total,
                'extraData' => [
                    'invoice_id' => $invoice->id,
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload, 'test_webhook_token_secret');

        $response = $this->call(
            'POST',
            route('api.webhooks.mayar'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MAYAR_SIGNATURE' => $signature,
            ],
            $jsonPayload
        );

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_webhook_accepts_query_token(): void
    {
        $invoice = $this->terbitkanTagihan();
        $invoice->update([
            'payment_reference' => 'mayar-inv-query-1',
        ]);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-inv-query-1',
                'status' => true,
                'amount' => $invoice->total,
                'extraData' => [
                    'invoice_id' => $invoice->id,
                ],
            ],
        ];

        $url = route('api.webhooks.mayar').'?token=test_webhook_token_secret';

        $response = $this->postJson($url, $payload);
        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_webhook_handles_testing_event(): void
    {
        $payload = [
            'event' => 'testing',
            'data' => [
                'id' => '123456789',
                'status' => 'SUCCESS',
            ],
        ];

        $url = route('api.webhooks.mayar').'?token=test_webhook_token_secret';
        $response = $this->postJson($url, $payload);

        $response->assertOk()
            ->assertJson([
                'statusCode' => 200,
                'status' => 'success',
            ]);
    }

    public function test_webhook_accepts_body_token(): void
    {
        $invoice = $this->terbitkanTagihan();
        $invoice->update([
            'payment_reference' => 'mayar-inv-body-1',
        ]);

        $payload = [
            'event' => 'payment.received',
            'token' => 'test_webhook_token_secret',
            'data' => [
                'id' => 'mayar-inv-body-1',
                'status' => true,
                'amount' => $invoice->total,
                'extraData' => [
                    'invoice_id' => $invoice->id,
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.mayar'), $payload);
        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertTrue($invoice->fresh()->isPaid());
    }
}
