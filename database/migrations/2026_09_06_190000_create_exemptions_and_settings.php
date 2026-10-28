<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian yang dikelola dari panel admin, bukan dari env atau CLI.
 *
 * Sampai sekarang satu-satunya cara membebaskan sesuatu dari penagihan adalah
 * kolom `workspaces.is_internal` yang tidak punya antarmuka sama sekali — harus
 * lewat tinker. Itu melanggar aturan yang sudah ditulis sendiri di CLAUDE.md:
 * kebutuhan yang "cuma bisa lewat CLI" adalah tanda antarmukanya yang kurang,
 * bukan alasan menambah command. Dan yang lebih buruk, pengecualian tanpa
 * antarmuka berarti tidak ada satu pun tempat yang menjawab "siapa saja yang
 * sekarang memakai produk ini gratis" — pertanyaan yang cepat atau lambat
 * ditanyakan, dan jawabannya harus bisa dilihat, bukan diingat.
 *
 * Tiga tabel/kolom, tiga kebutuhan berbeda:
 *
 *  - `special_numbers` — nomor milik perusahaan sendiri. Tidak menghitung
 *    kuota sesi workspace mana pun dan tidak ikut dilepas saat langganan mati.
 *  - `users.is_exempt` — akun yang seluruh workspace miliknya bebas berlangganan.
 *  - `app_settings` — penyetelan yang harus bisa diubah tanpa redeploy, mulai
 *    dari workspace pengirim notifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_numbers', function (Blueprint $table): void {
            $table->id();

            // Disimpan sudah ternormalisasi (62xxx) lewat PhoneNumber::normalize.
            // Unik supaya tidak ada dua baris yang mengatur nomor yang sama
            // dengan keterangan berbeda.
            $table->string('phone', 20)->unique();
            $table->string('label', 80);
            $table->string('note', 255)->nullable();

            // Siapa yang menambahkan — pengecualian tanpa jejak siapa yang
            // memberi adalah lubang audit, bukan kemudahan.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_exempt')->default(false)->after('is_super_admin');
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_exempt');
        });

        Schema::dropIfExists('special_numbers');
    }
};
