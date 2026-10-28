<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook menempel di API key, bukan lagi cuma di workspace.
 *
 * Pelanggan dengan tiga website menerima kejadian yang sama di ketiganya, dan
 * tidak ada cara tahu kejadian itu berasal dari integrasi yang mana. Untuk
 * pelanggan yang punya toko, aplikasi kasir, dan CRM di satu workspace, itu
 * tidak terpakai. Satu API key = satu website = satu webhook URL.
 *
 * **`api_key_id` nullable, dan itu syarat mutlak, bukan kemalasan.** `null`
 * berarti webhook tingkat workspace — persis perilaku yang berlaku sekarang.
 * Baris lama otomatis menjadi itu, jadi tidak ada satu pun pelanggan yang
 * webhook-nya berhenti saat migrasi berjalan. Memutus integrasi yang sedang
 * berjalan di sisi pelanggan adalah kegagalan yang tidak mereka sadari sampai
 * ada pesan yang hilang, dan saat itu jejaknya sudah dingin.
 *
 * `nullOnDelete` untuk keduanya: API key yang dicabut tidak boleh ikut
 * menghapus webhook-nya (pelanggan kehilangan konfigurasi yang susah payah
 * dipasang) maupun riwayat pesannya (angka pemakaian dan tagihan berdiri di
 * atasnya). Keduanya turun jadi tingkat workspace — berkurang ketepatannya,
 * tapi tidak ada yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->foreignId('api_key_id')->nullable()->after('workspace_id')
                ->constrained('api_keys')->nullOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            // Diisi saat pesan DIANTREKAN, bukan dibaca lagi saat dikirim:
            // pengiriman terjadi di dalam job, dan di dalam job tidak ada
            // request yang bisa ditanya API key mana yang sedang dipakai.
            $table->foreignId('api_key_id')->nullable()->after('wa_session_id')
                ->constrained('api_keys')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_id');
        });
    }
};
