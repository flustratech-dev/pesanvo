<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Setiap skrip shell harus bermode 100755 DI GIT.
 *
 * Di Windows, `ls -la` menipu: berkas bisa terbaca `-rwxr-xr-x` di disk
 * sementara git menyimpannya 100644. Yang berlaku di container adalah yang
 * di git.
 *
 * Karena itu yang dibaca di sini `git ls-files -s`, bukan `is_executable()`.
 */
class BerkasSkripTest extends TestCase
{
    #[Test]
    public function seluruh_skrip_shell_executable_di_git(): void
    {
        $berkas = $this->modeSkripDiGit();

        $this->assertNotEmpty(
            $berkas,
            'Tidak satu pun berkas .sh terbaca dari indeks git — jalur atau pola kuerinya salah, '
                .'dan uji ini berhenti menjaga apa pun.'
        );

        foreach ($berkas as $jalur => $mode) {
            $this->assertSame(
                '100755',
                $mode,
                "{$jalur} bermode {$mode} di git, seharusnya 100755. Perbaiki dengan:\n"
                    ."    git update-index --chmod=+x {$jalur}\n"
                    .'Mode di disk TIDAK menentukan — di Windows ia bisa terbaca executable '
                    .'sementara git menyimpannya 644, dan yang berlaku di container adalah git.'
            );
        }
    }

    /**
     * Dibaca dari INDEKS GIT, bukan dari sistem berkas.
     *
     * @return array<string, string> jalur => mode
     */
    private function modeSkripDiGit(): array
    {
        $keluaran = [];
        $status = 0;

        exec('git -C '.escapeshellarg(base_path()).' ls-files -s -- "*.sh" 2>&1', $keluaran, $status);

        if ($status !== 0) {
            $this->markTestSkipped('git tidak tersedia atau ini bukan repositori git.');
        }

        $hasil = [];

        foreach ($keluaran as $baris) {
            // Format: <mode> <sha> <stage>\t<jalur>
            if (preg_match('/^(\d{6}) \S+ \d+\t(.+)$/', $baris, $cocok)) {
                $hasil[$cocok[2]] = $cocok[1];
            }
        }

        return $hasil;
    }
}
