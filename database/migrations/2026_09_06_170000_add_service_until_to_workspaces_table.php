<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal berakhirnya layanan, disalin ke baris workspace.
 *
 * Sampai sekarang satu-satunya yang memutus layanan adalah `BillingCycleJob`
 * yang berjalan **pukul 08:00**, sementara masa berlaku habis pukul 23:59:59.
 * Di antara keduanya `workspaces.status` masih `active`, dan seluruh penegakan
 * yang ada — `MessageDispatcher::guardWorkspace()` dan `AuthenticateApiKey` —
 * membaca kolom itu. Artinya setiap pelanggan yang tidak memperpanjang tetap
 * bisa mengirim sepuasnya selama delapan jam, tiap periode, tanpa satu pun
 * gejala di mana pun. Yang paling mungkin memakainya bukan orang yang sengaja
 * curang, melainkan aplikasi terintegrasi yang memang mengirim otomatis
 * semalaman dan tidak tahu langganannya sudah habis.
 *
 * Dengan tanggalnya ikut di baris workspace, penolakannya menjadi seketika dan
 * tetap tanpa kueri tambahan di jalur pengiriman. Job harian tetap ada — ia
 * yang mengurus status, notifikasi, dan pelepasan sesi — tapi ia bukan lagi
 * satu-satunya yang berdiri antara langganan habis dan pesan terkirim.
 *
 * `null` berarti tidak pernah kedaluwarsa. Itu keadaan paket coba gratis, yang
 * dibatasi jumlah pesan dan bukan oleh tanggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->timestamp('service_until')->nullable()->after('status');
        });

        // Workspace yang sedang berjalan tidak boleh ikut mati karena kolom
        // barunya kosong; nilainya diambil dari langganan yang sudah ada.
        DB::table('workspaces')->update([
            'service_until' => DB::raw(
                '(select current_period_end from subscriptions where subscriptions.workspace_id = workspaces.id limit 1)'
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('service_until');
        });
    }
};
