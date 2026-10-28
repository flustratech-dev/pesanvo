<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan kunci penuh dalam bentuk terenkripsi, di samping hash-nya.
 *
 * Sebelumnya kunci hanya bisa dilihat sekali seumur hidup. Akibatnya dalam
 * pemakaian nyata: orang menempelkannya ke catatan pribadi, grup chat, atau
 * screenshot — tempat yang jauh lebih mudah bocor daripada database ini. Yang
 * kehilangan kuncinya harus mencabut dan membuat baru, lalu memperbarui .env
 * di setiap aplikasi yang memakainya, hanya karena ingin memeriksa satu digit.
 *
 * Yang TIDAK berubah: verifikasi permintaan API tetap memakai `key_hash`.
 * Kolom ini murni untuk menampilkan ulang di dashboard, dan dienkripsi dengan
 * APP_KEY sehingga salinan database saja tidak cukup untuk memakainya.
 *
 * Kunci yang dibuat sebelum migrasi ini tetap tidak bisa ditampilkan — tidak
 * ada yang bisa memulihkannya dari hash, dan itu memang tujuan hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->text('key_ciphertext')->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('key_ciphertext');
        });
    }
};
