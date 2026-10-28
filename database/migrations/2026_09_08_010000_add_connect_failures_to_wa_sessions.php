<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung percobaan sambung otomatis yang belum berhasil.
 *
 * `SyncSessionStatusJob` berjalan tiap menit dan memanggil `connect()` untuk
 * setiap sesi `disconnected` — tanpa penghitung dan tanpa jeda. Selama
 * penyebabnya sementara itu justru yang diinginkan. Yang tidak diantisipasi
 * adalah penyebab yang TIDAK akan membaik sendiri, dan di situ loop ini
 * berubah jadi mesin pengali kebocoran: tiap percobaan menyalakan satu Chromium
 * ±400 MB, dan sebelum penutupannya diperbaiki, tiap Chromium itu tidak pernah
 * kembali.
 *
 * Jalur terburuknya tidak butuh galat sama sekali. Engine menjawab `start`
 * dengan 200 seketika (`initialize()` sengaja tidak di-await), lalu mengabarkan
 * kegagalannya lewat event `auth_failure` — dan `laravel.event()` MENELAN
 * kegagalan pengiriman event itu (laravel.js:92-98). Kalau Laravel kebetulan
 * sibuk, baris sesi tetap `connecting`, engine sudah membuang entry-nya,
 * `/status` menjawab 404 → `disconnected`, dan menit berikutnya loop-nya
 * berulang. Selamanya.
 *
 * Karena itu yang dihitung PERCOBAAN, bukan kegagalan yang dilaporkan: satu-
 * satunya bukti yang bisa dipercaya adalah sesi yang benar-benar sampai
 * `connected`. Penghitungnya nol lagi di situ, dan hanya di situ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('connect_failures')->default(0)->after('auto_reconnect');
        });
    }

    public function down(): void
    {
        Schema::table('wa_sessions', function (Blueprint $table): void {
            $table->dropColumn('connect_failures');
        });
    }
};
