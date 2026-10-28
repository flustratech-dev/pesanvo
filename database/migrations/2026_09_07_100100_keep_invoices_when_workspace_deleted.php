<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan tidak boleh ikut terhapus bersama workspace-nya.
 *
 * `invoices.workspace_id` dibuat `cascadeOnDelete` sejak awal, dan selama ini
 * tidak pernah menggigit karena `Workspace::delete()` adalah hapus lunak —
 * baris workspace-nya tetap ada, jadi cascade tidak pernah berjalan.
 *
 * Yang membuatnya menjadi masalah nyata adalah penghapusan akun mandiri, yang
 * memakai `forceDelete()`. Pada saat itu cascade benar-benar berjalan dan
 * membuang seluruh riwayat tagihan workspace tersebut — dokumen pembukuan yang
 * menurut ketentuan perpajakan wajib disimpan sepuluh tahun, dan yang
 * Kebijakan Privasi kami janjikan tetap disimpan meski pelanggan meminta
 * penghapusan. Kehilangannya tidak menghasilkan galat apa pun; ia baru
 * ketahuan saat ada yang mencarinya, dan pada saat itu sudah tidak ada.
 *
 * Sesudah ini, workspace yang dihapus permanen meninggalkan tagihannya sebagai
 * baris yatim yang sengaja: nomor, nominal, tanggal, dan status utuh untuk
 * pembukuan, tanpa kaitan ke orang atau workspace mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Tagihan yatim harus dibuang lebih dulu: kolomnya akan kembali NOT NULL
        // dan barisnya tidak punya workspace untuk dirujuk lagi.
        DB::table('invoices')->whereNull('workspace_id')->delete();

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }
};
