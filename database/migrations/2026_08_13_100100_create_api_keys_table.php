<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Kunci penuh berformat `vwa_<prefix><random>` dan hanya ditampilkan
            // sekali saat dibuat. Yang disimpan cuma hash-nya; `prefix` dipakai
            // untuk mempersempit pencarian sebelum verifikasi hash, supaya tidak
            // perlu membandingkan hash ke seluruh baris tabel.
            $table->string('prefix', 12)->unique();
            $table->string('key_hash');

            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
