<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda perubahan akun yang belum sampai ke aplikasi pesanvo.
 *
 * Pengiriman pertama dicoba seketika, tapi aplikasi seberang bisa sedang
 * di-deploy ulang. Tanpa penanda ini, kata sandi yang diganti tepat saat itu
 * tidak pernah sampai: dua aplikasi yang katanya satu akun diam-diam menerima
 * kata sandi berbeda, dan pengguna baru tahu saat gagal masuk di salah satunya.
 *
 * `linked_sync_previous_email` menyimpan email LAMA saat email berganti dan
 * pengirimannya belum berhasil — tanpa itu, percobaan ulang tidak tahu baris
 * mana di seberang yang harus diganti emailnya, dan malah membuat akun kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('linked_sync_pending_at')->nullable()->index();
            $table->string('linked_sync_previous_email', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['linked_sync_pending_at']);
            $table->dropColumn(['linked_sync_pending_at', 'linked_sync_previous_email']);
        });
    }
};
