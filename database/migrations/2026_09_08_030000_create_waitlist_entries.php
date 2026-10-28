<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar tunggu kapasitas.
 *
 * Platform ini hanya sanggup menjalankan `WA_MAX_SESSIONS` nomor sekaligus, dan
 * sejak 8 September 2026 batas itu ditegakkan SEBELUM pelanggan membayar
 * (`App\Support\KapasitasPlatform`). Menolak lebih baik daripada mengambil uang
 * untuk layanan yang tidak bisa dijalankan — tapi menolak saja membuang dua hal
 * sekaligus.
 *
 * Yang pertama pelanggannya: orang yang ditolak tanpa jalan lain tidak kembali
 * besok, ia mencari gateway lain hari itu juga.
 *
 * Yang kedua justru lebih berharga, dan tidak ada di tempat lain mana pun:
 * **berapa banyak permintaan yang tidak bisa kami layani.** Tanpa tabel ini,
 * kapasitas penuh terlihat sebagai grafik pendaftaran yang datar — dan grafik
 * datar terbaca sebagai "tidak ada peminat", bukan sebagai "peminatnya ditolak
 * di pintu". Keduanya menuntut keputusan yang berlawanan.
 *
 * Barisnya TIDAK dihapus setelah pelanggan dikabari. Ia catatan permintaan, dan
 * catatan permintaan yang dihapus setelah dilayani menghapus juga satu-satunya
 * bukti bahwa kapasitas pernah jadi penghambat penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('plan_slug', 40);
            $table->string('period', 10);

            // Berapa slot yang dibutuhkan paket yang diminta. Elite butuh dua,
            // dan mengabari peminat Elite saat baru satu slot bebas berarti
            // menjanjikan sesuatu yang masih akan gagal.
            $table->unsignedTinyInteger('slots')->default(1);

            $table->timestamp('notified_at')->nullable();

            // Terisi saat workspace ini akhirnya benar-benar membayar paketnya.
            // Selisihnya dengan created_at adalah lama tunggu sebenarnya —
            // angka yang menentukan apakah kapasitas perlu ditambah sekarang
            // atau bulan depan.
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();

            // Satu baris per workspace per paket: menekan tombolnya lima kali
            // adalah satu permintaan, bukan lima.
            $table->unique(['workspace_id', 'plan_slug', 'period']);

            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
