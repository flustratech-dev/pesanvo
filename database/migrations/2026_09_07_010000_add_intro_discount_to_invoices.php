<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan harga perkenalan, dipisah dari potongan kode referal.
 *
 * **`discount_amount` tetap menjadi TOTAL seluruh potongan** — promo perkenalan
 * ditambah kode referal — dan kolom baru ini hanya rinciannya. Urutan itu
 * disengaja: `allocateUniqueCode()` menjamin nominal AKHIR unik di antara
 * tagihan terbuka lewat SQL mentah `amount + tax_amount - discount_amount`, dan
 * memecah totalnya menjadi dua kolom berarti kueri itu harus ikut diubah — di
 * tempat yang sudah sekali lolos SQLite lalu jatuh di MySQL.
 *
 * Jadi: `discount_amount` untuk hitungan, `intro_discount_amount` untuk
 * menjelaskan. Halaman tagihan menampilkan keduanya sebagai dua baris terpisah,
 * karena pelanggan yang memakai kode referal berhak tahu berapa yang datang
 * dari kodenya — dan reseller berhak tahu itu juga.
 *
 * Nilainya DIBEKUKAN di tagihan, tidak pernah dihitung ulang saat ditampilkan.
 * Harga promo di config boleh berubah kapan saja; tagihan yang sudah terbit
 * tidak boleh ikut berubah nilainya. Aturan yang sama dengan `invoices.total`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('intro_discount_amount')->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('intro_discount_amount');
        });
    }
};
