<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Permintaan ekspor data — hak akses menurut Pasal 5–15 UU PDP.
         |
         | Berupa tabel dan bukan unduhan seketika karena isinya bisa ratusan
         | ribu baris pesan. Menyusunnya di dalam permintaan HTTP berarti
         | halaman menggantung sampai habis waktu, lalu pelanggan menekan
         | tombolnya lagi — dan tiap tekanan membangun ulang berkas yang sama
         | dari nol sambil menahan satu pekerja PHP.
        */
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // menunggu | diproses | siap | gagal
            $table->string('status', 20)->default('menunggu');

            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();

            // Berkasnya memuat seluruh isi percakapan pelanggan. Membiarkannya
            // di disk selamanya berarti menumpuk salinan data paling sensitif
            // di tempat yang tidak pernah dilihat siapa pun lagi — dan setiap
            // salinan seperti itu adalah kebocoran yang menunggu terjadi.
            $table->timestamp('expires_at')->nullable();

            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            /*
             | Penghapusan akun ditunda, tidak seketika.
             |
             | Ini satu-satunya tindakan di produk ini yang benar-benar tidak
             | bisa dibatalkan, dan sebagian besar permintaan hapus yang
             | disesali terjadi dalam hitungan jam — akun yang dikira duplikat,
             | orang yang menekan tombol yang salah, kemarahan sesaat pada
             | tagihan. Masa tunggu mengubah kesalahan permanen menjadi
             | kesalahan yang bisa dibatalkan sendiri.
             |
             | `deletion_scheduled_for` disimpan terpisah dari
             | `deletion_requested_at` supaya lamanya masa tunggu MEMBEKU pada
             | saat permintaan dibuat. Menghitungnya dari config setiap kali
             | berarti mengubah config memindahkan tanggal eksekusi orang yang
             | sudah terlanjur diberi tahu tanggalnya.
            */
            $table->timestamp('deletion_requested_at')->nullable()->after('is_exempt');
            $table->timestamp('deletion_scheduled_for')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deletion_requested_at', 'deletion_scheduled_for']);
        });

        Schema::dropIfExists('data_exports');
    }
};
