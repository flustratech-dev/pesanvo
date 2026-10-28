<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_sessions', function (Blueprint $table) {
            // ULID: id sesi ikut dipakai sebagai clientId RemoteAuth di engine
            // dan muncul di nama folder/zip, jadi harus aman untuk nama file.
            $table->ulid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // platform = nomor resmi Pesanvo (OTP, undangan, billing, tiket).
            // tenant   = nomor milik pelanggan sendiri (invoice, PO, blast).
            $table->enum('kind', ['platform', 'tenant'])->default('tenant');
            $table->enum('driver', ['wwebjs', 'cloud_api', 'fonnte'])->default('wwebjs');

            $table->enum('status', [
                'pending',      // dibuat, belum pernah dijalankan
                'connecting',   // engine sedang membuka browser
                'qr',           // menunggu di-scan
                'connected',
                'disconnected',
                'failed',
            ])->default('pending');

            $table->string('phone_number', 20)->nullable();
            $table->string('push_name')->nullable();

            // QR mentah dari WhatsApp; dirender jadi gambar saat diminta, tidak
            // disimpan sebagai PNG supaya baris tidak membengkak.
            $table->text('qr_payload')->nullable();
            $table->timestamp('qr_expires_at')->nullable();

            $table->boolean('auto_reconnect')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('session_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('wa_session_id')->constrained('wa_sessions')->cascadeOnDelete();
            $table->string('disk')->default('session-backups');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('backed_up_at');
            $table->timestamps();

            $table->index(['wa_session_id', 'backed_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_backups');
        Schema::dropIfExists('wa_sessions');
    }
};
