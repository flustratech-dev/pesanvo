<?php

namespace Tests\Unit;

use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Menjaga satu perjanjian yang kalau dilanggar tidak menghasilkan galat apa pun:
 * `retry_after` antrean harus lebih besar dari batas waktu job TERPANJANG.
 *
 * `retry_after` bukan "berapa lama sebelum mengulang job yang gagal" — ia
 * "berapa lama sebuah job boleh berjalan sebelum antrean menyimpulkan worker-nya
 * mati". Job yang melampauinya dilepas kembali dan dikerjakan lagi SEMENTARA
 * yang pertama masih berjalan.
 *
 * Untuk SendMessageJob akibatnya bukan galat melainkan penerima yang menerima
 * pesan yang sama dua kali. Engine punya penangkalnya (map `#kiriman` di
 * session-manager.js), tapi map itu cuma menyimpan 15 menit — percobaan ulang
 * di luar jendela itu benar-benar mengirim ulang.
 *
 * Bawaan Laravel 90 detik, dan itu lebih pendek daripada hampir seluruh job di
 * sini. Uji ini ada supaya job baru dengan timeout panjang tidak lolos diam-diam.
 */
class AntreanTest extends TestCase
{
    #[Test]
    public function retry_after_melampaui_seluruh_timeout_job(): void
    {
        $jobs = $this->jobs();

        // SELURUH connection, bukan cuma yang sedang dipakai. Angka yang
        // tertinggal di connection lain tidak menggigit hari ini dan menggigit
        // diam-diam pada hari QUEUE_CONNECTION diganti.
        foreach ($this->retryAfterTiapConnection() as $connection => $retryAfter) {
            foreach ($jobs as $kelas => $timeout) {
                $this->assertLessThan(
                    $retryAfter,
                    $timeout,
                    "{$kelas} bertimeout {$timeout} detik, sama atau melebihi retry_after ({$retryAfter}) "
                        ."pada connection '{$connection}'. Job ini akan dikerjakan dua kali sementara "
                        .'yang pertama masih berjalan. Naikkan retry_after di config/queue.php lebih dulu.'
                );
            }
        }
    }

    /**
     * `queue:work --timeout` di start.sh tunduk pada perjanjian yang sama:
     * worker yang dibunuh pengawasnya sendiri sesudah `retry_after` lewat
     * meninggalkan job yang sudah dilepas ke worker berikutnya.
     */
    #[Test]
    public function timeout_worker_di_start_sh_lebih_pendek_dari_retry_after(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $script = file_get_contents(base_path('start.sh'));

        $this->assertMatchesRegularExpression('/queue:work.*--timeout=\d+/', $script);

        preg_match('/queue:work[^\n]*--timeout=(\d+)/', $script, $cocok);

        $this->assertLessThan(
            $retryAfter,
            (int) $cocok[1],
            "queue:work --timeout={$cocok[1]} di start.sh melebihi retry_after ({$retryAfter})."
        );
    }

    /**
     * @return array<string, int>
     */
    private function retryAfterTiapConnection(): array
    {
        $hasil = [];

        foreach (config('queue.connections') as $nama => $pengaturan) {
            if (isset($pengaturan['retry_after'])) {
                $hasil[$nama] = (int) $pengaturan['retry_after'];
            }
        }

        $this->assertNotEmpty($hasil, 'Tidak ada satu pun connection ber-retry_after terbaca.');

        return $hasil;
    }

    /**
     * Dibaca dari berkasnya, bukan dari daftar yang ditulis tangan: daftar
     * manual adalah daftar yang suatu saat lupa ditambahi, dan yang lupa
     * ditambahi persis job baru yang paling mungkin melanggar.
     *
     * @return array<class-string, int>
     */
    private function jobs(): array
    {
        $hasil = [];

        foreach (glob(app_path('Jobs/*.php')) as $berkas) {
            $kelas = 'App'.chr(92).'Jobs'.chr(92).basename($berkas, '.php');

            if (! class_exists($kelas) || ! is_subclass_of($kelas, ShouldQueue::class)) {
                continue;
            }

            $timeout = (new ReflectionClass($kelas))->getDefaultProperties()['timeout'] ?? null;

            if (is_int($timeout)) {
                $hasil[$kelas] = $timeout;
            }
        }

        $this->assertNotEmpty($hasil, 'Tidak ada satu pun job terbaca — jalur glob-nya salah.');

        return $hasil;
    }
}
