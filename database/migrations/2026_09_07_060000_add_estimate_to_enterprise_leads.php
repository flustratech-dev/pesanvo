<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perkiraan yang dilihat pemohon, disimpan apa adanya.
 *
 * Bukan demi kerapian: tanpa ini, tim menyusun penawaran tanpa tahu angka apa
 * yang sudah dilihat — dan menyebut angka yang lebih tinggi dari yang tertera
 * di layar saat pemohon memutuskan menghubungi kami adalah cara tercepat
 * kehilangan mereka. Harga komponennya boleh berubah kapan saja; yang dilihat
 * pemohon saat itu tidak boleh ikut berubah.
 *
 * Aturan yang sama sudah berlaku untuk `invoices.total` dan
 * `referral_redemptions.commission_amount`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_leads', function (Blueprint $table) {
            $table->unsignedInteger('want_retention_months')->nullable()->after('estimated_messages');
            $table->unsignedInteger('want_api_rate')->nullable()->after('want_retention_months');
            $table->boolean('want_onboarding')->default(false)->after('want_api_rate');

            // Rupiah penuh, bilangan bulat.
            $table->unsignedBigInteger('estimate_monthly')->nullable()->after('want_onboarding');
            $table->unsignedBigInteger('estimate_yearly')->nullable()->after('estimate_monthly');
            $table->unsignedBigInteger('estimate_once')->nullable()->after('estimate_yearly');
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_leads', function (Blueprint $table) {
            $table->dropColumn([
                'want_retention_months',
                'want_api_rate',
                'want_onboarding',
                'estimate_monthly',
                'estimate_yearly',
                'estimate_once',
            ]);
        });
    }
};
