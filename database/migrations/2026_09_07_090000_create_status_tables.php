<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Hitungan per komponen per hari, bukan satu baris per pemeriksaan.
         |
         | Halaman status menggambar 90 batang — satu per hari per komponen.
         | Menyimpan mentahnya berarti 648.000 baris untuk menggambar 450 batang,
         | dengan beban tulis tiap menit selamanya. Yang hilang dari agregasi ini
         | cuma kemampuan menunjuk menit persisnya sebuah gangguan mulai; itu
         | pertanyaan yang dijawab catatan insiden dan log, bukan halaman status.
        */
        Schema::create('status_daily', function (Blueprint $table): void {
            $table->id();
            $table->string('component', 40);
            $table->date('day');
            $table->unsignedInteger('ok_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);
            $table->timestamps();

            // Satu baris per komponen per hari. Tanpa indeks unik ini,
            // `firstOrCreate` yang berjalan berbarengan dari dua proses
            // menghasilkan dua baris dan uptime hari itu terhitung separuh.
            $table->unique(['component', 'day']);
        });

        /*
         | Insiden ditulis manusia, bukan disimpulkan mesin.
         |
         | Pemeriksaan otomatis tahu SEBUAH komponen tidak sehat; ia tidak tahu
         | apa yang terjadi, siapa yang terdampak, dan kapan kira-kira selesai —
         | dan justru tiga hal itu yang dicari orang saat membuka halaman status
         | di tengah gangguan. Halaman status tanpa kalimat dari manusia sama
         | tidak bergunanya dengan tidak ada halaman status.
        */
        Schema::create('status_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);

            // Kosong berarti gangguan menyeluruh, bukan pada satu komponen.
            $table->string('component', 40)->nullable();

            // menyelidiki → teridentifikasi → memantau → selesai
            $table->string('status', 20)->default('menyelidiki');

            // gangguan | pemeliharaan — dibedakan karena pemeliharaan terjadwal
            // dikecualikan dari perhitungan SLA, dan pelanggan berhak melihat
            // mana yang mana tanpa harus bertanya.
            $table->string('kind', 20)->default('gangguan');

            $table->string('impact', 20)->default('sebagian');
            $table->text('summary');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['started_at']);
        });

        /*
         | Kabar susulan dipisah dari insidennya, bukan menimpa ringkasannya.
         |
         | Yang menenangkan orang saat gangguan bukan kalimat terakhir melainkan
         | terlihatnya ada yang sedang mengerjakan — dan itu hanya terbaca kalau
         | riwayat kabarnya tersusun berurutan dengan jamnya.
        */
        Schema::create('status_incident_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('status_incident_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incident_updates');
        Schema::dropIfExists('status_incidents');
        Schema::dropIfExists('status_daily');
    }
};
