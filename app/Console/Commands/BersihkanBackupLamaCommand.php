<?php

namespace App\Console\Commands;

use App\Models\SessionBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Membersihkan sisa backup sesi era Chromium (format .zip).
 *
 * Sejak cutover ke Baileys, kredensial sesi disimpan dalam format payload JSON
 * terstruktur (session.json). Berkas .zip biner lama tidak kompatibel dengan
 * Baileys dan memicu json_encode error jika terbaca saat startSession.
 *
 * Perintah ini menghapus baris database session_backups berformat .zip dan
 * membuang berkas fisiknya dari storage disk agar tidak menjadi sampah.
 */
class BersihkanBackupLamaCommand extends Command
{
    protected $signature = 'pesanvo:bersihkan-backup-lama
        {--dry-run : Hanya melaporkan berapa backup yang akan dihapus, tidak menghapus apa pun}';

    protected $description = 'Membersihkan baris session_backups berformat lama (.zip) dan berkas fisiknya dari storage disk.';

    public function handle(): int
    {
        $kering = (bool) $this->option('dry-run');

        $backups = SessionBackup::where('path', 'like', '%.zip')->get();
        $total = $backups->count();

        if ($total === 0) {
            $this->info('Tidak ada backup berformat lama yang ditemukan.');

            return self::SUCCESS;
        }

        $barisTabel = $backups->map(fn (SessionBackup $b) => [
            'id' => $b->id,
            'session_id' => $b->wa_session_id,
            'disk' => $b->disk,
            'path' => $b->path,
            'ada_di_disk' => Storage::disk($b->disk)->exists($b->path) ? 'Ya' : 'Tidak',
        ])->all();

        $this->table(
            ['ID', 'Session ID', 'Disk', 'Path', 'Ada di Disk?'],
            $barisTabel
        );

        if ($kering) {
            $this->newLine();
            $this->line("<comment>Mode kering.</comment> {$total} baris backup lama dan berkasnya AKAN dihapus; tidak ada yang diubah.");
            $this->line('Jalankan tanpa <comment>--dry-run</comment> untuk benar-benar menghapus.');

            return self::SUCCESS;
        }

        $berkasDihapus = 0;
        foreach ($backups as $backup) {
            if (Storage::disk($backup->disk)->exists($backup->path)) {
                Storage::disk($backup->disk)->delete($backup->path);
                $berkasDihapus++;
            }
            $backup->delete();
        }

        $this->newLine();
        $this->info("{$total} baris backup lama berformat .zip berhasil dihapus ({$berkasDihapus} berkas fisik di disk dibersihkan).");

        return self::SUCCESS;
    }
}
