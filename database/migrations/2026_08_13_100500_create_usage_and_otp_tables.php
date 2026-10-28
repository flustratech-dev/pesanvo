<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per tenant per bulan. Sengaja tidak menghitung ulang dari
        // tabel messages saat pengecekan kuota — tabel itu akan jutaan baris
        // dan ikut dipangkas oleh retensi.
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('messages_sent')->default(0);
            $table->unsignedInteger('messages_received')->default(0);
            $table->unsignedInteger('messages_failed')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            // Membedakan OTP verifikasi nomor dari OTP keperluan lain, supaya
            // kode untuk satu tujuan tidak bisa dipakai di tujuan lain.
            $table->string('purpose', 40)->default('phone_verification');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('requested_by_ip', 45)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'expires_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('usage_counters');
    }
};
