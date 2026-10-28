<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langganan melekat pada workspace, satu banding satu.
 *
 * Alternatifnya — langganan melekat pada akun, lalu paket menentukan berapa
 * workspace yang boleh dibuat — terdengar lebih murah bagi pelanggan tapi
 * memutus hubungan antara yang ditagih dan yang membebani server. Biaya nyata
 * kami tumbuh per nomor WhatsApp, dan nomor melekat pada workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Unik: satu workspace tidak boleh punya dua langganan sekaligus.
            // Riwayat perpindahan paket tercatat di tabel invoices, bukan
            // dengan menumpuk baris langganan yang sudah tidak berlaku.
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('plan_slug');
            $table->enum('period', ['monthly', 'yearly'])->default('monthly');

            /*
             | trialing  — masa pakai gratis; berlaku penuh, berakhir di period_end
             | active    — sudah dibayar
             | past_due  — lewat jatuh tempo; pengiriman berhenti, nomor tetap tertaut
             | suspended — lewat masa tenggang; sesi ikut diputus
             | canceled  — dihentikan pelanggan atau admin
             */
            $table->enum('status', ['trialing', 'active', 'past_due', 'suspended', 'canceled'])
                ->default('trialing');

            $table->timestamp('current_period_start')->nullable();

            // Diindeks karena job harian menyapu tabel ini dengan perbandingan
            // tanggal — satu-satunya kueri yang berjalan setiap hari di sini.
            $table->timestamp('current_period_end')->nullable()->index();

            // Kapan pengiriman dihentikan. Awal hitungan masa tenggang sebelum
            // sesi ikut diputus, jadi bukan sekadar catatan.
            $table->timestamp('past_due_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
