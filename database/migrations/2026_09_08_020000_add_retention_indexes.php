<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks yang dibutuhkan pemangkasan retensi.
 *
 * Pemangkasan selalu berbentuk `WHERE created_at < ?`, dan tidak satu pun tabel
 * di bawah punya indeks yang bisa melayaninya:
 *
 *   webhook_deliveries — indeksnya `(webhook_id, created_at)`. Predikat yang
 *   berangkat dari `created_at` tidak bisa memakai indeks komposit yang kolom
 *   pertamanya `webhook_id`; MySQL memindai seluruh tabel.
 *
 *   audit_logs — indeksnya `(tenant_id, created_at)`, dengan masalah yang sama.
 *
 *   notifications — `Notifier::pangkas()` menyaring `read_at`, yang tidak
 *   terindeks sama sekali.
 *
 * Tanpa indeks ini, pemangkasan bertahap justru LEBIH buruk daripada satu
 * DELETE besar: tiap potongan memindai ulang seluruh tabel dari awal, jadi
 * biayanya naik kuadratik terhadap jumlah potongan.
 *
 * Dijalankan sekarang, saat seluruh tabel ini masih di bawah 1 MB di produksi
 * (webhook_deliveries bahkan kosong). DDL yang sama setelah ada pelanggan
 * adalah operasi mahal pada MySQL yang dipakai bersama aplikasi pesanvo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->index('created_at', 'webhook_deliveries_created_at_index');

            // Retensi kiriman SUKSES jauh lebih pendek daripada yang gagal
            // (48 jam vs 7 hari), dan sebagian besar baris memang sukses.
            // Menyaringnya butuh kedua kolom sekaligus.
            $table->index(['delivered_at', 'created_at'], 'webhook_deliveries_delivered_created_index');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('created_at', 'audit_logs_created_at_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('read_at', 'notifications_read_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex('webhook_deliveries_created_at_index');
            $table->dropIndex('webhook_deliveries_delivered_created_index');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_created_at_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_read_at_index');
        });
    }
};
