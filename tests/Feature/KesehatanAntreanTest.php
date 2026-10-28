<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Support\KesehatanAntrean;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Deteksi antrean macet.
 *
 * Yang paling sulit di sini bukan mendeteksi worker mati, melainkan TIDAK
 * memberi peringatan palsu saat antreannya memang panjang. Broadcast seribu
 * nomor sah-sah saja menyisakan pekerjaan berjam-jam — engine menahan tiap
 * pesan 3–8 detik sebagai jeda anti-ban. Peringatan yang menyala tiap kali ada
 * yang berkirim massal berhenti dibaca lebih cepat daripada tidak ada
 * peringatan sama sekali, dan sesudah itu worker yang benar-benar mati pun
 * ikut tidak terlihat.
 */
class KesehatanAntreanTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

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
    }

    private function job(int $menitLalu): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes($menitLalu)->timestamp,
            'created_at' => now()->subMinutes($menitLalu)->timestamp,
        ]);
    }

    private function pesanTerkirim(int $menitLalu): void
    {
        Message::create([
            'workspace_id' => $this->workspace->id,
            'direction' => 'outbound',
            'to_number' => '6289999999999',
            'type' => 'text',
            'body' => 'halo',
            'status' => 'sent',
            'sent_at' => now()->subMinutes($menitLalu),
        ]);
    }

    public function test_antrean_kosong_tidak_pernah_macet(): void
    {
        $this->assertFalse(KesehatanAntrean::periksa()['macet']);
    }

    public function test_job_baru_belum_dianggap_macet(): void
    {
        $this->job(1);

        $this->assertFalse(
            KesehatanAntrean::periksa()['macet'],
            'Job berumur satu menit sudah dianggap macet — worker sehat pun akan dilaporkan mati.'
        );
    }

    public function test_worker_mati_terdeteksi(): void
    {
        $this->job(30);
        $this->pesanTerkirim(45);

        $hasil = KesehatanAntrean::periksa();

        $this->assertTrue($hasil['macet']);
        $this->assertSame(1, $hasil['menunggu']);
    }

    /**
     * Inti tes ini: antrean panjang yang BERGERAK bukan kerusakan.
     */
    public function test_broadcast_besar_yang_bergerak_tidak_dilaporkan_macet(): void
    {
        // Pekerjaan menumpuk berjam-jam — wajar untuk broadcast besar.
        for ($i = 0; $i < 50; $i++) {
            $this->job(120);
        }

        // Tapi ada yang baru saja terkirim: worker-nya jelas hidup.
        $this->pesanTerkirim(1);

        $hasil = KesehatanAntrean::periksa();

        $this->assertFalse(
            $hasil['macet'],
            'Broadcast besar yang sedang berjalan dilaporkan macet — peringatan palsu seperti ini '
            .'membuat peringatan sungguhan ikut berhenti dibaca.'
        );
        $this->assertSame(50, $hasil['menunggu']);
    }

    public function test_belum_pernah_ada_pesan_terkirim_dan_job_menua_dianggap_macet(): void
    {
        // Pemasangan baru yang worker-nya tidak pernah menyala sama sekali.
        $this->job(30);

        $hasil = KesehatanAntrean::periksa();

        $this->assertTrue($hasil['macet']);
        $this->assertNull($hasil['terakhirBergerak']);
    }

    public function test_ringkasan_admin_memperingatkan_saat_antrean_macet(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->job(30);
        $this->pesanTerkirim(60);

        $this->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertSee('Antrean tidak bergerak');
    }
}
