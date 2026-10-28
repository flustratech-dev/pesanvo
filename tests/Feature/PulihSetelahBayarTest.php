<?php

namespace Tests\Feature;

use App\Jobs\SyncSessionStatusJob;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Janji yang paling menentukan apakah pelanggan kembali: bayar, lalu nomornya
 * hidup lagi sendiri — tanpa scan QR, tanpa menekan apa pun.
 *
 * Seluruh tangga penangguhan berdiri di atas janji ini. Kalau ia tidak benar,
 * melepas nomor setelah masa tenggang berubah dari "menghemat kapasitas" jadi
 * "menghukum pelanggan yang telat bayar", dan memendekkan masa tenggang dari 30
 * hari ke 14 menjadi keputusan yang salah.
 *
 * Yang menjaganya ada dua dan keduanya mudah rusak tanpa terasa:
 * `suspend()` harus memakai `disconnect()` (bukan `logout()`, yang membuang
 * kredensial di kedua sisi), dan `SyncSessionStatusJob` harus mau menjalankan
 * ulang sesi milik workspace yang baru saja membayar.
 */
class PulihSetelahBayarTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
        ]);

        $this->workspace->members()->attach($pemilik->id, ['role' => 'owner']);
        $this->berlangganan($this->workspace, 'prime');

        $this->session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'driver' => 'wwebjs',
            'auto_reconnect' => true,
            'phone_number' => '6281111111111',
            'connected_at' => now()->subDays(30),
        ]);

        // Cadangan kredensial — inilah yang membuat sambung ulang tidak perlu QR.
        $this->session->backups()->create([
            'disk' => 'local',
            'path' => 'wa-backups/'.$this->session->id.'.zip',
            'size' => 1024,
            'backed_up_at' => now(),
        ]);
    }

    private function tangguhkan(): void
    {
        $subscription = $this->workspace->subscription;

        $subscription->forceFill([
            'status' => 'past_due',
            'past_due_at' => now()->subDays(config('billing.grace_days') + 1),
        ])->save();

        app(SubscriptionService::class)->suspend($subscription->fresh());
    }

    public function test_menangguhkan_melepas_nomor_tanpa_membuang_kredensialnya(): void
    {
        $this->tangguhkan();

        $this->assertSame('disconnected', $this->session->fresh()->status);

        // Yang paling penting: cadangannya TIDAK ikut terhapus. Kalau ia hilang,
        // pelanggan yang kembali membayar diminta scan QR ulang.
        $this->assertSame(1, $this->session->backups()->count());

        // Dan nomornya tidak dilepas dari barisnya — ini `disconnect`, bukan
        // `logout`; tautan perangkatnya masih ada di sisi WhatsApp.
        $this->assertSame('6281111111111', $this->session->fresh()->phone_number);
    }

    public function test_nomor_yang_dilepas_menjelaskan_alasannya(): void
    {
        $this->tangguhkan();

        $alasan = $this->workspace->fresh()->subscription->alasanNomorBerhenti();

        $this->assertNotNull($alasan, 'Nomor berhenti tanpa satu kata pun penjelasan.');
        $this->assertStringContainsString('langganan', $alasan['judul']);
        $this->assertStringContainsString('tanpa perlu scan QR', $alasan['pesan']);
    }

    public function test_membayar_menyambungkan_kembali_nomor_dengan_sendirinya(): void
    {
        $this->tangguhkan();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace->fresh(), 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        // Pembayaran menghidupkan workspace-nya; yang menyambungkan nomornya
        // adalah penjadwal yang berjalan tiap menit — pelanggan tidak menekan
        // apa pun.
        $this->assertTrue($this->workspace->fresh()->isActive());
        $this->assertNull($this->workspace->fresh()->subscription->alasanNomorBerhenti());

        $dipanggil = [];
        Http::fake(function ($request) use (&$dipanggil) {
            $dipanggil[] = $request->url();

            return Http::response(['success' => true, 'data' => ['status' => 'disconnected']], 200);
        });

        (new SyncSessionStatusJob)->handle(app(SessionService::class));

        $this->assertNotEmpty(
            array_filter($dipanggil, fn ($url) => str_contains($url, '/start')),
            'Nomor tidak tersambung sendiri setelah tagihan lunas.'
        );

        // Dan kredensialnya masih ada untuk dipulihkan engine — tanpa ini,
        // "tersambung sendiri" berarti memunculkan QR yang tidak ada yang scan.
        $this->assertSame(1, $this->session->backups()->count());
    }
}
