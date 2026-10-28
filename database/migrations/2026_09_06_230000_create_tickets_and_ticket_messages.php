<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpdesk, dibangun di dalam produk ini sendiri.
 *
 * Keputusan Owner, dan sudah diambil: `app-helpdesk` memang ada dan sudah
 * memakai gateway ini, tapi pelanggan pesanvo tidak seharusnya dilempar ke
 * produk lain untuk mengeluh.
 *
 * `user_id` di `ticket_messages` nullable karena `null` berarti balasan admin.
 * Dibedakan begitu — bukan dengan menyimpan id admin — supaya nama karyawan
 * kami tidak pernah bocor ke layar pelanggan; yang menjawab adalah Pesanvo,
 * bukan orang tertentu. `is_from_admin` tetap ada sebagai penanda eksplisit,
 * karena `user_id` yang kosong juga bisa berarti akun penanyanya sudah dihapus.
 *
 * `last_reply_at` disimpan alih-alih dihitung dari pesan terakhir: daftar tiket
 * diurutkan dengannya, dan mengurutkan lewat subquery ke tabel pesan membuat
 * halaman yang paling sering dibuka admin melambat seiring bertambahnya tiket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Penanya. Nullable supaya menghapus akun tidak ikut menghapus
            // riwayat percakapannya — tiket adalah catatan kami juga.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->string('category', 40)->default('lainnya');
            $table->string('priority', 20)->default('normal');

            // open | answered | closed
            $table->string('status', 20)->default('open')->index();

            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Saringan bawaan admin adalah "perlu dijawab" — tiket yang status
            // terakhirnya `open`, terlama di atas. Indeksnya menopang itu.
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_from_admin')->default(false);

            // Disimpan di disk `media` (privat), sama seperti bukti bayar.
            // Tidak pernah disajikan lewat URL yang bisa ditebak.
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
