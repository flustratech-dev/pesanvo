<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Membuka setiap halaman dashboard sebagai pengguna sungguhan.
 *
 * Tes lain memeriksa logika; tes ini memeriksa bahwa halamannya benar-benar
 * bisa dirender. Galat pada Blade — variabel yang tidak dikirim controller,
 * relasi yang salah nama, komponen yang tidak ada — hanya muncul saat view
 * dijalankan, dan tidak akan tertangkap tes yang berhenti di lapisan service.
 */
class DashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Uji',
            'slug' => 'toko-uji',
            'owner_id' => $this->user->id,
            'owner_email' => $this->user->email,
            'max_sessions' => 3,
            'monthly_message_quota' => 1000,
            'api_rate_limit_per_minute' => 60,
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        // Workspace baru lahir `unpaid`; tes ini menguji hal lain,
        // jadi penagihannya tidak boleh ikut menghalangi.
        $this->berlangganan($this->workspace);

        $this->actingAs($this->user);
        $this->withSession(['current_workspace_id' => $this->workspace->id]);
    }

    public function test_semua_halaman_dashboard_bisa_dibuka(): void
    {
        $halaman = [
            'dashboard' => route('dashboard'),
            'sessions.index' => route('sessions.index'),
            'messages.index' => route('messages.index'),
            'messages.compose' => route('messages.compose'),
            'templates.index' => route('templates.index'),
            'api-keys.index' => route('api-keys.index'),
            'webhooks.index' => route('webhooks.index'),
            'settings' => route('settings'),
        ];

        foreach ($halaman as $nama => $url) {
            $this->get($url)->assertOk("Halaman {$nama} gagal dirender.");
        }
    }

    /**
     * Kunci yang baru dibuat harus datang bersama cuplikan .env-nya.
     *
     * Menyerahkan kunci tanpa memberi tahu ditempel ke mana adalah titik henti
     * paling umum saat menyambungkan aplikasi: pemakainya punya kunci, tapi
     * tidak tahu nama variabelnya, dan tidak ada tempat bertanya.
     */
    public function test_kunci_baru_disertai_cuplikan_env(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir', 'scopes' => ['*']])
            ->assertRedirect();

        $this->get(route('api-keys.index'))
            ->assertOk()
            ->assertSee('WA_GATEWAY_URL='.rtrim(config('app.url'), '/'))
            ->assertSee('WA_GATEWAY_SESSION=');
    }

    public function test_halaman_dashboard_menampilkan_isi_ketika_ada_data(): void
    {
        // Halaman kosong sering lolos padahal versi berisinya rusak — misalnya
        // relasi yang salah nama baru meledak saat barisnya benar-benar ada.
        $session = $this->workspace->sessions()->create([
            'name' => 'CS Utama',
            'status' => 'connected',
            'phone_number' => '6281234567890',
            'push_name' => 'Toko Uji',
            'connected_at' => now(),
        ]);

        $message = Message::create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $session->id,
            'direction' => 'outbound',
            'to_number' => '6289999999999',
            'chat_id' => '6289999999999@c.us',
            'type' => 'text',
            'body' => 'Halo dari tes',
            'status' => 'delivered',
            'wa_message_id' => 'true_628_ABC',
            'provider_response' => ['ok' => true],
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->workspace->messages()->create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $session->id,
            'direction' => 'inbound',
            'from_number' => '6289999999999',
            'type' => 'text',
            'body' => 'Balasan pelanggan',
            'status' => 'delivered',
        ]);

        $this->workspace->templates()->create([
            'name' => 'Pengingat Invoice',
            'slug' => 'pengingat-invoice',
            'body' => 'Halo {{ nama }}, faktur {{ nomor }} jatuh tempo.',
            'variables' => ['nama', 'nomor'],
        ]);

        $webhook = $this->workspace->webhooks()->create([
            'url' => 'https://contoh.id/webhook',
            'secret' => Str::random(48),
            'events' => ['message.received'],
        ]);

        $webhook->deliveries()->create([
            'event' => 'message.received',
            'payload' => ['data' => []],
            'response_code' => 200,
            'attempts' => 1,
            'delivered_at' => now(),
        ]);

        ApiKey::issue($this->workspace, 'kunci uji');

        foreach ([
            route('dashboard'),
            route('sessions.index'),
            route('messages.index'),
            route('messages.compose'),
            route('messages.show', $message->id),
            route('templates.index'),
            route('api-keys.index'),
            route('webhooks.index'),
            route('settings'),
        ] as $url) {
            $this->get($url)->assertOk("Gagal merender {$url} saat ada data.");
        }
    }

    public function test_pengguna_tanpa_workspace_diarahkan_ke_onboarding(): void
    {
        $baru = User::create([
            'name' => 'Tanpa Workspace',
            'email' => 'kosong@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->actingAs($baru)
            ->withSession([])
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.create'));

        $this->actingAs($baru)->get(route('onboarding.create'))->assertOk();
    }

    /**
     * Satu akun boleh memegang beberapa workspace — satu per cabang, atau satu
     * nomor pelanggan dan satu nomor CS. Relasinya memang many-to-many sejak
     * awal, tapi jalan masuknya sempat cuma lewat middleware "belum punya
     * workspace", sehingga membuat yang kedua tampak mustahil dan orang beralih
     * membuat akun baru — yang justru memecah kepemilikan dan tagihan.
     */
    public function test_pengguna_yang_sudah_punya_workspace_bisa_membuat_workspace_kedua(): void
    {
        $this->get(route('onboarding.create'))->assertOk();

        $this->post(route('onboarding.store'), ['name' => 'Workspace CS'])
            ->assertRedirect(route('sessions.index'));

        $this->assertSame(2, $this->user->workspaces()->count());
        $this->assertSame('Workspace CS', $this->user->fresh()->workspaces()->orderByDesc('workspaces.id')->first()->name);
    }

    /**
     * Pemilih workspace di header adalah satu-satunya jalan berpindah, dan
     * tautan "+ Workspace" di sebelahnya satu-satunya jalan menambah.
     */
    public function test_header_memuat_pemilih_workspace_dan_tautan_workspace_baru(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Toko Uji')
            ->assertSee(route('onboarding.create'));
    }

    /**
     * Halaman detail pesan mengambil dari relasi workspace, bukan model global.
     * Tanpa itu, menebak ULID milik workspace lain akan menampilkan isinya.
     */
    public function test_pesan_milik_workspace_lain_tidak_bisa_dibuka(): void
    {
        $lain = Workspace::create(['name' => 'Workspace Lain', 'slug' => 'workspace-lain', 'max_sessions' => 1]);

        $pesan = Message::create([
            'workspace_id' => $lain->id,
            'direction' => 'outbound',
            'to_number' => '6281111111111',
            'type' => 'text',
            'body' => 'Rahasia workspace lain',
            'status' => 'sent',
        ]);

        $this->get(route('messages.show', $pesan->id))->assertNotFound();
    }
}
