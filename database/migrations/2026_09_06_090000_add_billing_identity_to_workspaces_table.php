<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepada siapa tagihan ini ditujukan.
 *
 * Sampai sekarang tagihan hanya menyebut nama workspace. Itu cukup selama
 * pembelinya juga pemakainya, tapi begitu ada yang meminta faktur untuk
 * pembukuan — dan itu permintaan pertama yang datang dari pelanggan berbentuk
 * badan usaha — tidak ada satu pun kolom yang bisa menjawab "atas nama siapa".
 *
 * Sengaja menempel di workspace, bukan di tiap tagihan: yang membayar tidak
 * berganti tiap bulan, dan menyalinnya ke tiap baris tagihan berarti data yang
 * sama tersimpan berulang lalu menyimpang diam-diam saat salah satunya diubah.
 * Konsekuensinya yang harus disadari: mengganti nama penagihan mengubah
 * tampilan seluruh tagihan lama juga. Kalau suatu saat faktur harus membeku
 * pada keadaan saat terbit, salin nilainya ke `invoices` pada saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('billing_phone');
            $table->string('billing_email')->nullable()->after('billing_name');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_email']);
        });
    }
};
