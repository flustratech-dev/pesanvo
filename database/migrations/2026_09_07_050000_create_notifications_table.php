<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pusat notifikasi — dua aliran, satu tabel.
 *
 * Kenapa ini ada padahal sudah ada WhatsApp dan email: keduanya keluar dari
 * sistem dan bisa gagal tanpa gejala. Nomor Pesanvo terputus dan seluruh kabar
 * diam; `MAIL_MAILER` salah dan tidak satu pun email terkirim. Lonceng di
 * dalam aplikasi adalah satu-satunya jalur yang tidak bergantung pada apa pun
 * di luar — dan ia juga satu-satunya yang menyimpan riwayat, karena WhatsApp
 * yang sudah digulir hilang.
 *
 * **Disebar per penerima, bukan satu baris untuk banyak orang.** Alasannya
 * keadaan "sudah dibaca": ia melekat pada orangnya, bukan pada kabarnya. Satu
 * baris bersama menuntut tabel pivot yang dibaca di setiap pemuatan halaman,
 * sementara jumlah anggota workspace di produk ini kecil — menyebarnya jauh
 * lebih murah daripada menjaganya tetap tunggal.
 *
 * `audience` membedakan dua aliran yang isinya tidak pernah bercampur:
 * pelanggan perlu tahu nomornya terputus, tim perlu tahu ada bukti bayar
 * menunggu. Menyatukannya berarti tim menerima puluhan jenis kabar milik
 * ratusan workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Konteks, bukan kepemilikan. `null` untuk kabar yang tidak
            // menyangkut satu workspace tertentu — hampir semua kabar tim.
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();

            // 'workspace' | 'admin'
            $table->string('audience', 20)->default('workspace');

            // Slug peristiwa, mis. `session.disconnected`. Dipakai menyaring dan
            // — nanti — mematikan jenis kabar tertentu per pengguna.
            $table->string('type', 60)->index();

            // info | success | warning | danger
            $table->string('level', 20)->default('info');

            $table->string('title');
            $table->text('body')->nullable();

            // Tautan tindakan. Notifikasi yang tidak bisa ditindaklanjuti cuma
            // memberi tahu ada masalah tanpa memberi jalan menyelesaikannya.
            $table->string('url')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Lonceng selalu dibuka untuk melihat yang BELUM dibaca; indeksnya
            // menopang persis kueri itu.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'audience', 'created_at']);

            /*
             | Penjaga kabar kembar, ditegakkan database.
             |
             | Job harian bisa berjalan dua kali, `markPaid()` bisa dipanggil
             | ulang dari panel, dan penjadwal bisa mendeteksi engine mati tiap
             | menit. Tanpa kunci ini, lonceng terisi tiga puluh baris yang
             | sama dan berhenti dibaca siapa pun — kegagalan yang lebih buruk
             | daripada tidak ada notifikasi sama sekali.
             |
             | Nullable: kabar yang memang boleh berulang (mis. tiap tiket baru)
             | dibiarkan tanpa kunci, dan MySQL maupun SQLite mengizinkan banyak
             | NULL di kolom unik.
            */
            $table->string('dedupe_key', 120)->nullable();
            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
