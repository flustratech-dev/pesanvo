<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus istilah "tenant" dari database.
 *
 * Dashboard sejak awal menyebutnya *workspace*, kode dan database menyebutnya
 * *tenant*. Dua nama untuk satu benda itu bukan sekadar tidak rapi: saat
 * menelusuri kenapa sesi yang sudah hijau tetap ditolak, setengah waktunya
 * habis untuk memastikan dua istilah itu memang merujuk hal yang sama.
 *
 * Kolom `kind` pada wa_sessions ikut dibuang di sini. Nilai `platform` hanya
 * bisa lahir dari perintah CLI yang sudah dihapus, dan keberadaannya membuat
 * sesi yang tampak terhubung di dashboard tidak pernah terpilih otomatis saat
 * pemanggil API mengosongkan session_id — tanpa petunjuk apa pun di antarmuka.
 */
return new class extends Migration
{
    /** Tabel yang memuat kolom tenant_id, beserta indeks yang menyebutnya. */
    private const TABEL = [
        'tenant_members',
        'api_keys',
        'wa_sessions',
        'messages',
        'message_templates',
        'webhooks',
        'usage_counters',
        'otp_codes',
        'audit_logs',
    ];

    public function up(): void
    {
        Schema::rename('tenants', 'workspaces');
        Schema::rename('tenant_members', 'workspace_members');

        foreach ($this->tabelSetelahRename() as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->renameColumn('tenant_id', 'workspace_id');
            });
        }

        Schema::table('wa_sessions', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        Schema::table('wa_sessions', function (Blueprint $table) {
            $table->enum('kind', ['platform', 'tenant'])->default('tenant');
        });

        foreach ($this->tabelSetelahRename() as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->renameColumn('workspace_id', 'tenant_id');
            });
        }

        Schema::rename('workspace_members', 'tenant_members');
        Schema::rename('workspaces', 'tenants');
    }

    /** @return array<int, string> */
    private function tabelSetelahRename(): array
    {
        return array_map(
            fn (string $t) => $t === 'tenant_members' ? 'workspace_members' : $t,
            self::TABEL
        );
    }
};
