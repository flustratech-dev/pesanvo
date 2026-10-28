<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             | Rahasia TOTP dan kode pemulihan disimpan TERENKRIPSI (cast
             | `encrypted` di model), bukan sebagai hash.
             |
             | Berbeda dari kata sandi, dan perbedaannya wajib: rahasia TOTP
             | harus bisa dibaca kembali apa adanya untuk menghitung kode setiap
             | 30 detik. Hash tidak mungkin dipakai di sini. Yang bisa dilakukan
             | adalah membuatnya tidak berguna bagi siapa pun yang mendapat
             | salinan basis data tanpa APP_KEY — dan itu tepat mencakup
             | kebocoran dump SQL, cadangan yang bocor, dan akses baca ke replika.
             |
             | Konsekuensi yang harus diingat: APP_KEY yang berganti membuat
             | SELURUH 2FA tidak bisa dibaca lagi, dan setiap admin terkunci di
             | luar panelnya sendiri.
            */
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            /*
             | Rahasia yang sudah dibuat TAPI belum dikonfirmasi tidak boleh
             | menghalangi siapa pun masuk.
             |
             | Tanpa kolom ini, orang yang membuka halaman pemasangan lalu
             | menutup tabnya sebelum memindai QR akan diminta kode dari
             | aplikasi yang tidak pernah ia pasang — terkunci permanen di luar
             | akunnya sendiri oleh fitur yang belum sempat ia nyalakan.
            */
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
