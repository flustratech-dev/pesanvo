<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Nomor tagihan yang dilihat pelanggan dan dipakai pembukuan.
            // Diturunkan dari id supaya urutannya dijamin database, bukan oleh
            // penghitung di aplikasi yang bisa menghasilkan nomor kembar saat
            // dua tagihan terbit pada detik yang sama.
            $table->string('number')->unique();

            // Penanda untuk penyedia pembayaran. Sudah ada sejak sekarang meski
            // saluran satu-satunya masih manual: begitu iPaymu masuk, nilai ini
            // yang dikirim sebagai referensi, dan tagihan lama tetap punya satu.
            $table->ulid('external_id')->unique();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('plan_slug');
            $table->enum('period', ['monthly', 'yearly'])->default('monthly');

            // Rupiah penuh, disimpan sebagai bilangan bulat. Rupiah tidak punya
            // pecahan yang dipakai, dan desimal mengambang pada nilai uang
            // adalah cara paling umum membuat jumlah tagihan meleset satu rupiah.
            $table->unsignedInteger('amount');
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedSmallInteger('unique_code')->default(0);
            $table->unsignedInteger('total');

            $table->enum('status', ['pending', 'paid', 'expired', 'canceled'])
                ->default('pending')
                ->index();

            // Mencatat lewat mana tagihan ini dibayar, bukan menawarkan pilihan.
            $table->string('channel')->default('qris_manual');

            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();

            // Bukti transfer yang diunggah pelanggan, dan admin yang menandai
            // lunas. Keduanya wajib ada selama pembayaran belum otomatis:
            // tanpa jejak siapa yang menyetujui, tidak ada cara menelusuri
            // tagihan yang ditandai lunas padahal uangnya tidak pernah masuk.
            $table->string('proof_path')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();

            $table->timestamps();

            // Halaman tagihan pelanggan selalu bertanya dengan dua kolom ini.
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
