<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            // ULID dikembalikan ke pemanggil API sebagai id pelacakan, jadi
            // tidak boleh auto-increment yang bisa ditebak antar tenant.
            $table->ulid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('wa_session_id')->nullable()->constrained('wa_sessions')->nullOnDelete();

            $table->enum('direction', ['outbound', 'inbound']);

            // id pesan dari WhatsApp (mis. "true_628...@c.us_3EB0..."), dipakai
            // mencocokkan event ack yang datang belakangan.
            $table->string('wa_message_id')->nullable()->index();
            $table->string('chat_id')->nullable();
            $table->string('to_number', 32)->nullable();
            $table->string('from_number', 32)->nullable();

            $table->enum('type', ['text', 'image', 'document', 'video', 'audio', 'location', 'other'])->default('text');
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_filename')->nullable();

            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('provider_response')->nullable();

            // Menandai pesan yang lahir dari job broadcast, supaya bisa
            // dihitung & dibatalkan sebagai satu kesatuan.
            $table->ulid('batch_id')->nullable()->index();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['wa_session_id', 'direction']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('body');
            // Daftar placeholder {{nama}} yang dipakai body, disimpan supaya UI
            // bisa memvalidasi variabel yang dikirim tanpa mem-parsing ulang.
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('messages');
    }
};
