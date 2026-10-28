<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda workspace rekanan: paket berbayar yang diberikan admin tanpa tagihan.
 *
 * Berbeda dari `users.is_exempt`. Akun bebas tidak dibatasi apa pun dan tidak
 * pernah berakhir; rekanan tunduk pada batas paketnya dan punya tanggal
 * berakhir seperti pelanggan biasa — yang dihilangkan cuma pembayarannya.
 *
 * Penandanya di workspace, bukan di pengguna, karena paket juga hidup di
 * workspace: satu pemilik bisa punya workspace rekanan dan workspace berbayar
 * sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('partner_since')->nullable()->after('is_internal');
            $table->string('partner_note', 255)->nullable()->after('partner_since');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['partner_since', 'partner_note']);
        });
    }
};
