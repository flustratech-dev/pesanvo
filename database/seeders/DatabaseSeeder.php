<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Berlaku di semua tahap — lokal, staging, dan produksi.
     *
     * Isinya hanya akun super admin. Tidak ada data contoh: workspace, sesi,
     * dan API key semuanya dibuat lewat dashboard, termasuk milik Pesanvo
     * sendiri dan aplikasi Pesanvo yang memakainya, jadi seeder yang
     * membuatkannya akan menghasilkan baris yang tidak pernah bisa dibuka
     * siapa pun lewat antarmuka.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}
