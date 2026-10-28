<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom hash cepat untuk API key, berdampingan dengan bcrypt lama.
 *
 * Alasan perpindahan hash-nya ada di `ApiKey::hashSecret()`. Yang ini soal
 * BENTUK penyimpanannya, dan bentuk itu dipilih demi satu hal: rollback yang
 * cukup mengembalikan kode.
 *
 * Kolom `key_hash` sengaja TIDAK disentuh dan tetap berisi bcrypt. Kalau ia
 * ditimpa SHA-256, kode lama yang memanggil `Hash::check()` tidak sekadar
 * menolak kunci — `BcryptHasher::check()` MELEMPAR RuntimeException ("This
 * password does not use the Bcrypt algorithm") begitu menemui hash yang bukan
 * bcrypt. Rollback sesudah itu berarti SELURUH API key pelanggan mati serentak,
 * dan pemulihannya menuntut langkah SQL yang harus diingat seseorang di tengah
 * gangguan.
 *
 * Dengan bcrypt tetap di tempatnya, rollback tidak menuntut apa pun: kode lama
 * membaca kolom yang sama seperti biasa dan tidak pernah tahu kolom ini ada.
 *
 * Biayanya satu kolom 64 karakter per kunci, dan satu bcrypt saat kunci DIBUAT
 * (bukan saat dipakai). Pembuatan kunci terjadi beberapa kali seumur hidup
 * sebuah workspace; verifikasinya ribuan kali sehari.
 *
 * Kolom ini dilepas nanti oleh `pesanvo:hash-api-bersihkan`, dan sejak saat itu
 * rollback tidak lagi gratis. Waktu amannya ada di docs/CUTOVER.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->string('key_hash_fast', 64)->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropColumn('key_hash_fast');
        });
    }
};
