<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\MessageDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pemberitahuan WhatsApp.
 *
 * Produk ini menjual pengiriman notifikasi WhatsApp; tidak memakainya untuk
 * memberi tahu pelanggannya sendiri adalah tukang kayu yang rumahnya bocor.
 * Lebih dari itu, di sini WhatsApp adalah satu-satunya jalur yang benar sampai:
 * `users` tidak menyimpan nomor telepon, email belum dikonfigurasi, dan spanduk
 * dashboard hanya terlihat oleh yang kebetulan sedang membukanya.
 *
 * Yang diperiksa di sini bukan bunyi kalimatnya, melainkan bahwa pesannya
 * benar-benar mengantre — ke nomor yang benar, dari sesi Pesanvo, sekali saja.
 */
class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $pesanvo;

    private Workspace $pelanggan;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Queue::fake();

        // Workspace milik Pesanvo sendiri, dengan satu nomor tersambung.
        $this->pesanvo = Workspace::create([
            'name' => 'Pesanvo Internal',
            'slug' => 'pesanvo-internal',
            'is_internal' => true,
            'max_sessions' => 1,
            'monthly_message_quota' => 0,
        ]);

        $this->pesanvo->sessions()->create([
            'name' => 'Notifikasi',
            'status' => 'connected',
            'phone_number' => '6289999999999',
            'connected_at' => now(),
        ]);

        config([
            'billing.notify_workspace_id' => $this->pesanvo->id,
            'billing.admin_phone' => '6288888888888',
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->pelanggan = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'billing_phone' => '6281234567890',
            'max_sessions' => 1,
            'monthly_message_quota' => 10,
        ]);

        $this->pelanggan->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    /** @return Collection<int, Message> */
    private function pesanKe(string $nomor)
    {
        return Message::where('to_number', $nomor)->get();
    }

    public function test_pembayaran_dikonfirmasi_dikabarkan_ke_pelanggan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->pelanggan, 'prime', 'monthly');

        app(SubscriptionService::class)->markPaid($invoice);

        $pesan = $this->pesanKe('6281234567890');

        $this->assertCount(1, $pesan);
        $this->assertStringContainsString('Pembayaran dikonfirmasi', $pesan->first()->body);
        $this->assertStringContainsString($invoice->number, $pesan->first()->body);

        // Dikirim dari nomor Pesanvo, bukan dari nomor pelanggan mana pun.
        $this->assertSame(
            $this->pesanvo->sessions()->first()->id,
            $pesan->first()->wa_session_id,
        );
    }

    public function test_bukti_masuk_mengabari_pelanggan_dan_tim(): void
    {
        Storage::fake('media');

        $invoice = app(SubscriptionService::class)->issueInvoice($this->pelanggan, 'prime', 'monthly');

        $this->actingAs($this->owner)->post(route('billing.proof.upload', $invoice->id), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $this->assertStringContainsString(
            'Bukti pembayaran diterima',
            $this->pesanKe('6281234567890')->first()->body,
        );

        // Tanpa pesan ke tim, tidak ada apa pun yang memberi tahu bahwa ada
        // tagihan yang perlu dibuka — dan tagihan hanya lunas kalau dibuka.
        $this->assertStringContainsString(
            'Bukti pembayaran baru',
            $this->pesanKe('6288888888888')->first()->body,
        );
    }

    public function test_pengiriman_berhenti_dikabarkan(): void
    {
        $subscription = app(SubscriptionService::class)->ensureFor($this->pelanggan);

        app(SubscriptionService::class)->markPastDue($subscription);

        $this->assertStringContainsString(
            'Pengiriman pesan dihentikan sementara',
            $this->pesanKe('6281234567890')->first()->body,
        );
    }

    public function test_sesi_terputus_dikabarkan_ke_pemiliknya(): void
    {
        $sesi = $this->pelanggan->sessions()->create([
            'name' => 'CS Utama',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        $this->kirimEventEngine($sesi, 'disconnected');

        $this->assertStringContainsString(
            'Nomor WhatsApp Anda terputus',
            $this->pesanKe('6281234567890')->first()->body,
        );
    }

    /**
     * Sesi yang memang belum pernah discan bukan gangguan. Mengabarkannya
     * berarti mengganggu orang dengan sesuatu yang sudah mereka ketahui.
     */
    public function test_sesi_yang_belum_pernah_tersambung_tidak_dikabarkan(): void
    {
        $sesi = $this->pelanggan->sessions()->create([
            'name' => 'Belum Discan',
            'status' => 'qr',
        ]);

        $this->kirimEventEngine($sesi, 'disconnected');

        $this->assertCount(0, $this->pesanKe('6281234567890'));
    }

    public function test_kuota_hampir_habis_dikabarkan_sekali_saja(): void
    {
        $sesi = $this->pelanggan->sessions()->create([
            'name' => 'CS', 'status' => 'connected', 'phone_number' => '6281111111111', 'connected_at' => now(),
        ]);

        $dispatcher = app(MessageDispatcher::class);

        // Kuota 10; ambang peringatan di 80% = pesan ke-8.
        for ($i = 1; $i <= 9; $i++) {
            $dispatcher->queue($sesi, '6282222222222', ['body' => "halo {$i}"]);
        }

        $peringatan = $this->pesanKe('6281234567890')
            ->filter(fn ($m) => str_contains($m->body, 'Kuota pesan hampir habis'));

        $this->assertCount(1, $peringatan, 'Peringatan kuota harus dikirim sekali, bukan tiap pesan.');
    }

    public function test_tanpa_nomor_tagihan_tidak_ada_yang_dikirim(): void
    {
        $this->pelanggan->forceFill(['billing_phone' => null])->save();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->pelanggan->fresh(), 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        $this->assertSame(0, Message::where('workspace_id', $this->pesanvo->id)->count());
    }

    /**
     * Tanpa sesi pengirim, seluruh pemberitahuan diam — dan diam tidak boleh
     * menjatuhkan apa pun. Pembayaran tetap harus tercatat lunas.
     */
    public function test_tanpa_sesi_pengirim_pembayaran_tetap_tercatat(): void
    {
        $this->pesanvo->sessions()->update(['status' => 'disconnected']);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->pelanggan, 'elite', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $this->pelanggan->fresh()->subscription->status);
        $this->assertCount(0, $this->pesanKe('6281234567890'));
    }

    /**
     * Menandatangani seperti engine sungguhan: timestamp + titik + body mentah.
     * Body dikirim apa adanya supaya byte-nya sama persis dengan yang
     * ditandatangani — json_encode ulang bisa menghasilkan escaping berbeda.
     */
    private function kirimEventEngine(WaSession $session, string $event): void
    {
        $body = json_encode(['session_id' => $session->id, 'event' => $event]);
        $timestamp = (string) now()->getTimestamp();

        $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Engine-Timestamp' => $timestamp,
                'X-Engine-Signature' => hash_hmac('sha256', "{$timestamp}.{$body}", config('gateway.engine.hmac_secret')),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $body,
        )->assertOk();
    }
}
