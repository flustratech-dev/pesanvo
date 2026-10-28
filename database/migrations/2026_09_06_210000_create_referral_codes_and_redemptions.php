<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode referal: pembeli dapat diskon, reseller dapat komisi.
 *
 * **`discount_amount` disimpan di tagihan, bukan dihitung ulang saat
 * ditampilkan.** Persen di `referral_codes` boleh berubah kapan saja — reseller
 * naik kelas, promo berakhir — dan tagihan yang sudah terbit tidak boleh ikut
 * berubah nilainya. Aturan yang sama sudah berlaku untuk `invoices.total`, dan
 * alasannya sama: nominal yang berubah setelah pelanggan mencatatnya adalah
 * nominal yang tidak bisa dicocokkan dengan uang yang masuk.
 *
 * `commission_amount` di `referral_redemptions` mengikuti prinsip itu juga.
 *
 * `redeemed_count` sengaja TIDAK dinaikkan saat kode ditukar melainkan saat
 * tagihannya **lunas**. Kalau dihitung saat ditukar, siapa pun bisa
 * menghabiskan jatah `max_redemptions` sebuah kode dengan membuat tagihan
 * berulang-ulang tanpa pernah membayarnya — dan reseller kehilangan jatahnya
 * tanpa menerima satu rupiah pun komisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();

            // 5 huruf kapital, tanpa I dan O — keduanya paling sering tertukar
            // dengan angka 1 dan 0 saat kode didiktekan lewat telepon.
            $table->string('code', 5)->unique();

            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedTinyInteger('commission_percent')->default(0);

            // null berarti tanpa batas.
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('referral_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();

            // Satu workspace hanya boleh menukar SATU kode, sekali seumur
            // hidupnya. Ditegakkan indeks unik, bukan cuma oleh pemeriksaan di
            // service: dua permintaan checkout yang datang bersamaan lolos
            // pemeriksaan yang sama sebelum salah satunya sempat menulis.
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete()->unique();

            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);

            // pending  → ditukar, tagihannya belum lunas
            // approved → tagihan lunas, komisi terutang ke reseller
            // paid     → komisi sudah ditransfer (manual)
            // void     → tagihan kedaluwarsa atau dibatalkan
            $table->string('status', 20)->default('pending')->index();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('referral_code_id')->nullable()->after('subscription_id')
                ->constrained('referral_codes')->nullOnDelete();

            // Rupiah penuh, bilangan bulat. Tidak pernah float — pembulatan
            // sen yang tidak ada di mata uang ini menghasilkan selisih yang
            // tidak bisa dijelaskan ke siapa pun.
            $table->unsignedBigInteger('discount_amount')->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_code_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('referral_redemptions');
        Schema::dropIfExists('referral_codes');
    }
};
