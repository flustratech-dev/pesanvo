<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp yang bisa dihubungi soal tagihan.
 *
 * Sebelum ini tidak ada satu pun cara menghubungi pelanggan di luar dashboard:
 * `users` tidak menyimpan nomor telepon, dan aplikasi tidak punya konfigurasi
 * email sama sekali. Artinya satu-satunya pemberitahuan "langganan Anda habis
 * tiga hari lagi" adalah spanduk yang hanya terlihat oleh orang yang kebetulan
 * sedang membuka dashboard — dan pelanggan yang gateway-nya berjalan lancar
 * justru yang paling jarang membukanya.
 *
 * Terpisah dari nomor sesi WhatsApp dengan sengaja: sesi bisa diputus, diganti,
 * atau ditangguhkan, sementara nomor untuk urusan tagihan justru paling
 * dibutuhkan tepat saat sesinya sedang mati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('billing_phone', 20)->nullable()->after('owner_email');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('billing_phone');
        });
    }
};
