<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email menyusul WhatsApp di seluruh pengecualian.
 *
 * Tiga penambahan, satu alasan yang sama: WhatsApp adalah satu-satunya jalur
 * yang dipakai, dan satu-satunya jalur berarti satu titik yang kalau mati
 * membuat semuanya diam. Nomor Pesanvo terputus — hal yang memang terjadi saat
 * deploy, saat WhatsApp memutus perangkat tertaut, atau saat ponselnya lama
 * offline — dan seluruh kabar ke tim hilang tanpa satu pun gejala.
 *
 * `special_numbers.email` mencatat kontak pemilik nomor perusahaan, supaya
 * nomor yang bermasalah punya orang yang bisa dihubungi tanpa menebak.
 *
 * `pending_exemptions` menyimpan pembebasan untuk alamat email yang BELUM
 * pernah mendaftar. Tanpa ini, membebaskan calon pelanggan berarti menunggu
 * mereka mendaftar lebih dulu lalu mengingat untuk kembali menandainya — dan
 * yang lupa ditandai akan tertagih seperti pelanggan biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_numbers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });

        Schema::create('pending_exemptions', function (Blueprint $table) {
            $table->id();

            // Disimpan huruf kecil supaya pencocokannya tidak bergantung cara
            // orang mengetik alamatnya saat mendaftar.
            $table->string('email')->unique();

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Diisi saat pemiliknya benar-benar mendaftar dan pembebasannya
            // dipakai. Barisnya TIDAK dihapus — tanpa jejaknya, tidak ada cara
            // menjawab "siapa yang pernah membebaskan akun ini, dan kapan".
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_exemptions');

        Schema::table('special_numbers', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
