<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keadaan awal sebuah langganan: `unpaid` — belum pernah berlangganan.
 *
 * Sampai sekarang setiap workspace yang belum punya langganan otomatis dibuatkan
 * satu berstatus `trialing` sampai akhir bulan. Niatnya adalah memindahkan
 * pemakai lama tanpa memutus mereka, tapi akibatnya jauh lebih luas: **setiap
 * pendaftar baru juga ikut mendapat masa gratis**, dan seluruhnya berakhir di
 * tanggal yang sama. Orang yang mendaftar hari ini memakai produk berbayar
 * secara cuma-cuma sampai akhir bulan tanpa pernah diputuskan siapa pun.
 *
 * Pemberian gratis itu hanya berlaku untuk akun yang sudah ada **sebelum**
 * produk ini dijual — dan itu sudah dikerjakan sekali oleh migrasi
 * `give_existing_workspaces_a_trial`, bukan oleh keadaan bawaan.
 *
 * Karena itu bawaannya sekarang `unpaid`: workspace baru harus memilih paket
 * dan membayar lebih dulu. `unpaid` sengaja dibedakan dari `past_due` — yang
 * satu belum pernah membayar, yang lain pernah lalu berhenti, dan keduanya
 * butuh kalimat yang berbeda di depan pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'trialing', 'active', 'past_due', 'suspended', 'canceled'])
                ->default('unpaid')
                ->change();
        });
    }

    public function down(): void
    {
        // Baris `unpaid` harus punya tempat sebelum nilainya dibuang dari enum,
        // kalau tidak turunnya gagal di tengah jalan pada database yang benar
        // menegakkan enum.
        DB::table('subscriptions')
            ->where('status', 'unpaid')
            ->update(['status' => 'canceled']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['trialing', 'active', 'past_due', 'suspended', 'canceled'])
                ->default('trialing')
                ->change();
        });
    }
};
