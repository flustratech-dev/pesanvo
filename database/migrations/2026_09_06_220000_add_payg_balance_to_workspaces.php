<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pay as you go: saldo prepaid, dipotong per pesan yang benar-benar terkirim.
 *
 * `billing_mode` bawaannya `subscription`, jadi seluruh workspace yang sudah ada
 * tidak berubah perilakunya sama sekali — ini penambahan cabang, bukan
 * penggantian cara.
 *
 * **`balance_transactions` adalah buku besar, bukan log.** `workspaces.balance`
 * harus selalu sama dengan jumlah seluruh mutasinya, dan panel admin wajib punya
 * cara memeriksa kecocokan itu. Saldo yang tidak bisa direkonsiliasi adalah uang
 * pelanggan yang tidak bisa dipertanggungjawabkan — dan yang menemukan
 * selisihnya akan jadi pelanggan yang merasa saldonya berkurang sendiri.
 *
 * `balance_after` disimpan di tiap baris justru untuk itu: tanpanya, satu baris
 * yang hilang atau tertulis dua kali baru ketahuan dari total yang tidak cocok,
 * tanpa cara tahu di mana. Dengan kolom ini, barisnya bisa ditelusuri satu per
 * satu sampai ketemu tempat rantainya putus.
 *
 * Rupiah penuh, bilangan bulat, tidak pernah float. Pembulatan sen yang tidak
 * ada di mata uang ini menghasilkan selisih yang tidak bisa dijelaskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('billing_mode', 20)->default('subscription')->after('plan_slug');
            $table->bigInteger('balance')->default(0)->after('billing_mode');
        });

        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // topup | charge | refund | adjustment
            $table->string('type', 20)->index();

            // Positif menambah, negatif mengurangi. Ditandatangani supaya
            // jumlah seluruh kolom ini SAMA DENGAN saldo — rekonsiliasinya
            // jadi satu SUM, bukan penjumlahan bersyarat per jenis yang bisa
            // salah tafsir saat jenis baru ditambahkan.
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');

            // Pesan yang memotong saldo ini. Ber-ULID mengikuti `messages.id`.
            $table->foreignUlid('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu pesan hanya boleh memotong saldo SEKALI. Ditegakkan indeks,
            // bukan cuma oleh pemeriksaan di kode: `SendMessageJob` bisa
            // dijalankan ulang setelah gagal di tengah, dan pemotongan ganda
            // untuk satu pesan adalah uang pelanggan yang hilang tanpa jejak.
            $table->unique(['workspace_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'balance']);
        });
    }
};
