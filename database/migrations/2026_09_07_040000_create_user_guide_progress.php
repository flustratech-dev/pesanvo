<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tur pengenalan yang sudah pernah dilihat seseorang.
 *
 * Disimpan di tabel, bukan di cookie atau localStorage: tur yang muncul lagi
 * karena orang berganti peramban atau membersihkan cache terbaca sebagai
 * kerusakan, dan pengguna lama tidak punya cara menutupnya selamanya.
 *
 * **Tabelnya sengaja dibiarkan kosong.** Akun yang sudah ada ikut melihat tur
 * ini sekali — keputusan Owner, 7 Sep 2026. Alasannya masuk akal: fitur yang
 * dituntun tur ini sebagian besar memang baru (saldo, Enterprise, bantuan,
 * harga perkenalan), jadi pelanggan lama pun belum pernah melihatnya. Yang
 * dijaga tetap sama — **sekali saja**, dan itu dijamin baris di tabel ini,
 * bukan oleh siapa yang mendaftar kapan.
 *
 * `guide_version` ada supaya tur yang isinya berubah besar bisa ditampilkan
 * ulang kepada yang sudah pernah melihatnya, tanpa menghapus barisnya —
 * menghapus berarti kehilangan jawaban atas "siapa yang sudah pernah dituntun".
 *
 * `status` membedakan `completed` dari `skipped`. Keduanya sama-sama berarti
 * "jangan tampilkan lagi", tapi banyaknya orang yang melewati tur adalah
 * satu-satunya tanda bahwa turnya sendiri yang bermasalah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guide_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guide_key', 60);
            $table->unsignedSmallInteger('guide_version')->default(1);
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->unique(['user_id', 'guide_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guide_progress');
    }
};
