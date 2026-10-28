<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

/**
 * Melepas jaring pengaman rollback perpindahan hash API key.
 *
 * SELAMA `key_hash` masih berisi bcrypt, rollback ke kode lama tidak menuntut
 * apa pun: kode lama membaca kolom itu seperti biasa. Perintah ini memindahkan
 * SHA-256 ke `key_hash` dan mengosongkan `key_hash_fast` — dan sejak saat itu
 * rollback berarti seluruh API key pelanggan mati serentak, karena
 * `BcryptHasher::check()` MELEMPAR pada hash yang bukan bcrypt.
 *
 * Karena itu ia perintah tersendiri, bukan migrasi: migrasi berjalan sendiri
 * saat container menyala, dan keputusan "kita tidak akan rollback lagi" tidak
 * boleh diambil oleh proses boot. Waktu amannya ada di docs/CUTOVER.md.
 *
 * Yang dibeli setelah dijalankan: satu kolom hilang, dan `issue()` berhenti
 * membayar bcrypt saat membuat kunci. Keduanya kecil. Jangan terburu-buru.
 */
class BersihkanHashApiCommand extends Command
{
    protected $signature = 'pesanvo:hash-api-bersihkan
        {--dry-run : Hanya melaporkan, tidak mengubah apa pun}';

    protected $description = 'Memindahkan hash API key ke bentuk cepat sepenuhnya. Melepas kemampuan rollback.';

    public function handle(): int
    {
        $kering = (bool) $this->option('dry-run');

        $siap = ApiKey::whereNotNull('key_hash_fast')->count();

        /*
         | Kunci yang BELUM punya hash cepat adalah kunci yang belum sekali pun
         | dipakai sejak deploy dan tidak punya `key_ciphertext` untuk dinaikkan
         | lebih awal. Memindahkan yang lain sementara ia masih bcrypt tidak
         | merusaknya — `verifySecret()` menangani ketiga keadaan — tapi ia
         | tetap harus disebut, karena rollback sesudah ini tidak lagi gratis
         | dan pemiliknya perlu tahu kunci mana yang paling berisiko.
        */
        $belum = ApiKey::whereNull('key_hash_fast')->count();

        $this->line("Kunci dengan hash cepat : {$siap}");
        $this->line("Kunci yang belum        : {$belum}");
        $this->newLine();

        if ($siap === 0) {
            $this->warn('Tidak ada yang bisa dibersihkan.');

            return self::SUCCESS;
        }

        if ($kering) {
            $this->line("<comment>Mode kering.</comment> {$siap} kunci AKAN dipindahkan; tidak ada yang berubah.");
            $this->newLine();
            $this->warn('Menjalankannya sungguhan melepas kemampuan rollback. Baca docs/CUTOVER.md dulu.');

            return self::SUCCESS;
        }

        $dipindah = 0;

        ApiKey::whereNotNull('key_hash_fast')->chunkById(200, function ($keys) use (&$dipindah): void {
            foreach ($keys as $key) {
                $key->forceFill([
                    'key_hash' => $key->key_hash_fast,
                    'key_hash_fast' => null,
                ])->saveQuietly();

                $dipindah++;
            }
        });

        $this->info("{$dipindah} kunci dipindahkan. Rollback ke kode lama sekarang akan mematikan seluruh API key.");

        if ($belum > 0) {
            $this->warn("{$belum} kunci masih memakai bcrypt dan akan naik sendiri saat pertama dipakai.");
        }

        return self::SUCCESS;
    }
}
