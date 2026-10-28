<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket Enterprise: permintaan penawaran, dan paket custom yang menjawabnya.
 *
 * Dua tabel karena keduanya menjawab pertanyaan yang berbeda dan berumur
 * berbeda. `enterprise_leads` adalah SURAT MASUK — siapa yang pernah bertanya,
 * apa kebutuhannya, sudah dijawab belum — dan ia tetap berharga walau tidak
 * pernah menjadi pelanggan. `enterprise_plans` adalah KESEPAKATAN: batas dan
 * harga yang benar-benar berlaku untuk satu workspace.
 *
 * Menyatukan keduanya berarti lead yang batal ikut membawa baris paket yang
 * tidak pernah berlaku, dan pelanggan lama yang memperpanjang butuh lead palsu
 * hanya supaya paketnya punya tempat.
 *
 * **Batasnya disalin ke `workspaces` saat tagihannya lunas, bukan dibaca dari
 * sini tiap kali.** Itu aturan yang sudah berlaku untuk paket biasa
 * (`markPaid()` menyalin `max_sessions`, kuota, dan rate limit ke kolom
 * workspace), dan Enterprise tidak boleh jadi pengecualian: dua sumber
 * kebenaran untuk batas yang sama akan berbeda diam-diam, dan yang membacanya
 * di jalur pengiriman adalah kolom workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_leads', function (Blueprint $table) {
            $table->id();

            // Nullable: form ini terbuka untuk pengunjung yang belum punya akun.
            // Justru merekalah yang paling sering mengisinya — orang yang sudah
            // jadi pelanggan biasanya menghubungi lewat tiket.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone', 30);

            // Angka perkiraan dari pelanggan, bukan batas yang berlaku. Nullable
            // karena tidak semua orang tahu jawabannya saat bertanya, dan form
            // yang menolak orang karena itu kehilangan lead yang sebenarnya.
            $table->unsignedInteger('estimated_sessions')->nullable();
            $table->unsignedInteger('estimated_messages')->nullable();

            $table->text('needs')->nullable();

            // baru | diproses | selesai | ditolak
            $table->string('status', 20)->default('baru')->index();

            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enterprise_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('enterprise_leads')->nullOnDelete();

            $table->string('name');

            // Rupiah penuh, bilangan bulat. Harga tahunan ditulis TERPISAH,
            // tidak diturunkan dari pengali bulanan seperti paket katalog:
            // kesepakatan enterprise memang dinegosiasikan per kasus, dan
            // memaksakan rumus ×10 di sini berarti angka yang disepakati dengan
            // pelanggan tidak bisa dimasukkan apa adanya.
            $table->unsignedBigInteger('price_monthly');
            $table->unsignedBigInteger('price_yearly');

            $table->unsignedInteger('max_sessions')->default(1);
            $table->unsignedInteger('monthly_message_quota')->default(0);
            $table->unsignedInteger('max_api_keys')->default(0);
            $table->unsignedInteger('max_members')->default(0);
            $table->unsignedInteger('message_retention_days')->default(365);
            $table->unsignedInteger('api_rate_limit_per_minute')->default(300);

            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu workspace, satu kesepakatan yang berlaku. Kesepakatan lama
            // dimatikan (`is_active` false), bukan dihapus — riwayat harga yang
            // pernah disepakati adalah hal yang akan dicari orang saat ada
            // perselisihan tagihan.
            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_plans');
        Schema::dropIfExists('enterprise_leads');
    }
};
