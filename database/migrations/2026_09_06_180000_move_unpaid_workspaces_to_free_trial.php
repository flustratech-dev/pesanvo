<?php

use App\Support\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan workspace yang masih `unpaid` ke paket coba gratis.
 *
 * Antara dinyalakannya status `unpaid` dan dibuatnya paket coba gratis, setiap
 * pendaftar baru lahir terkunci penuh: tidak bisa menautkan nomor, tidak bisa
 * membuat API key, tidak bisa mengirim apa pun. `ensureFor()` berhenti di baris
 * pertama kalau langganannya sudah ada, jadi mereka akan tetap begitu selamanya
 * meski kodenya sudah berubah — dan yang mengalaminya justru orang yang paling
 * awal mendaftar.
 *
 * Yang dipindahkan hanya yang benar-benar belum pernah membayar. `unpaid` juga
 * bisa dicapai lewat pembatalan di kemudian hari, dan workspace yang pernah
 * membayar lalu berhenti tidak boleh diberi jatah coba baru — jatah itu sekali
 * seumur workspace, bukan sekali tiap kali berhenti berlangganan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gratis = Plan::free();

        $sasaran = DB::table('subscriptions')
            ->where('status', 'unpaid')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.workspace_id', 'subscriptions.workspace_id')
                    ->where('invoices.status', 'paid');
            })
            ->pluck('workspace_id');

        if ($sasaran->isEmpty()) {
            return;
        }

        DB::table('subscriptions')
            ->whereIn('workspace_id', $sasaran)
            ->update([
                'plan_slug' => $gratis->slug,
                'status' => 'trialing',
                'current_period_start' => now(),
                'current_period_end' => null,
                'updated_at' => now(),
            ]);

        DB::table('workspaces')
            ->whereIn('id', $sasaran)
            ->update($gratis->limits() + [
                'plan_slug' => $gratis->slug,
                'status' => 'active',
                'service_until' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Dikembalikan ke `unpaid` — keadaan sebelum migrasi ini. Pemakaian yang
     * telanjur terjadi selama masa coba tetap tercatat di `usage_counters`,
     * jadi menjalankan ulang migrasinya tidak mengembalikan jatah yang sudah
     * terpakai.
     */
    public function down(): void
    {
        $gratis = config('plans.free');

        $sasaran = DB::table('subscriptions')
            ->where('status', 'trialing')
            ->where('plan_slug', $gratis)
            ->pluck('workspace_id');

        if ($sasaran->isEmpty()) {
            return;
        }

        DB::table('subscriptions')
            ->whereIn('workspace_id', $sasaran)
            ->update([
                'plan_slug' => config('plans.default'),
                'status' => 'unpaid',
                'current_period_start' => null,
                'updated_at' => now(),
            ]);

        DB::table('workspaces')
            ->whereIn('id', $sasaran)
            ->update(['status' => 'suspended', 'updated_at' => now()]);
    }
};
