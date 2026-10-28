<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan pemakai yang sudah ada ke paket berbayar tanpa memutus mereka.
 *
 * Sampai migrasi ini jalan, gateway dipakai tanpa langganan sama sekali. Semua
 * workspace yang sudah ada karena itu mendapat paket terkecil dengan status
 * `trialing` sampai akhir bulan berjalan: layanannya berjalan penuh seperti
 * kemarin, dan yang berubah cuma munculnya tanggal berakhir di dashboard.
 *
 * Yang sengaja TIDAK dilakukan di sini: menurunkan batas mereka ke batas paket
 * Essentials. Workspace yang sudah terlanjur diberi kuota besar secara manual
 * tetap memegangnya sampai periode percobaannya habis — memotong kuota di
 * tengah bulan berarti pesan yang sedang mengalir tiba-tiba berhenti, dan
 * pelanggan mengalaminya sebagai kerusakan, bukan sebagai kebijakan harga.
 * Batas paket mulai berlaku saat langganan pertama dibayar.
 *
 * Workspace internal Pesanvo dilewati: ia tidak pernah menagih dirinya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        $akhirBulan = now()->endOfMonth();
        $sekarang = now();
        $paket = config('plans.default');

        $baris = DB::table('workspaces')
            ->whereNull('deleted_at')
            ->where('is_internal', false)
            ->whereNotIn('id', function ($q) {
                $q->select('workspace_id')->from('subscriptions');
            })
            ->pluck('id')
            ->map(fn ($id) => [
                'workspace_id' => $id,
                'plan_slug' => $paket,
                'period' => 'monthly',
                'status' => 'trialing',
                'current_period_start' => $sekarang,
                'current_period_end' => $akhirBulan,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])
            ->all();

        foreach (array_chunk($baris, 200) as $bagian) {
            DB::table('subscriptions')->insert($bagian);
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('status', 'trialing')->delete();
    }
};
