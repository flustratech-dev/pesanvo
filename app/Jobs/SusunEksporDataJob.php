<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Menyusun satu berkas ZIP berisi seluruh data sebuah workspace.
 *
 * Ada untuk memenuhi hak akses menurut UU PDP, dan sekaligus untuk satu hal
 * yang lebih sering dipakai: pelanggan yang ingin menyimpan riwayat
 * percakapannya sendiri sebelum masa retensi paketnya memangkasnya. Retensi
 * berjalan otomatis dan tidak bisa dibatalkan — halaman ini satu-satunya cara
 * menyelamatkan riwayat sebelum itu terjadi.
 *
 * Pesan ditulis sebagai CSV yang di-stream baris demi baris, bukan dikumpulkan
 * ke array lalu di-encode. Workspace dengan retensi 365 hari bisa punya ratusan
 * ribu baris, dan memuat seluruhnya ke memori adalah cara paling pasti membuat
 * pekerja antrean dibunuh OOM killer di server yang sumber dayanya terbatas.
 */
class SusunEksporDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * Sekali saja. Ekspor yang gagal lalu diulang tiga kali menyusun berkas
     * ratusan megabyte tiga kali untuk kegagalan yang penyebabnya hampir selalu
     * sama — kehabisan ruang disk atau memori, yang tidak berubah dalam tiga
     * kali percobaan.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $exportId) {}

    public function handle(Notifier $notifikasi): void
    {
        $ekspor = DataExport::with('workspace')->find($this->exportId);

        if (! $ekspor || $ekspor->status !== 'menunggu') {
            return;
        }

        $ekspor->update(['status' => 'diproses']);

        try {
            [$path, $ukuran] = $this->susun($ekspor);

            $ekspor->update([
                'status' => 'siap',
                'path' => $path,
                'size' => $ukuran,
                'finished_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);

            $notifikasi->keUser(
                $ekspor->pemohon,
                type: 'ekspor.siap',
                title: 'Ekspor data siap diunduh',
                body: 'Berkas untuk workspace '.$ekspor->workspace->name.' sudah selesai disusun. Tautannya berlaku 7 hari.',
                url: route('settings'),
                dedupe: "ekspor:{$ekspor->id}",
            );
        } catch (\Throwable $e) {
            Log::error('Ekspor data gagal disusun: '.$e->getMessage(), ['ekspor' => $ekspor->id]);

            $ekspor->update([
                'status' => 'gagal',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            // Kegagalan yang diam adalah kegagalan yang membuat pelanggan
            // menunggu berkas yang tidak akan pernah datang, lalu menyimpulkan
            // haknya tidak dilayani.
            $notifikasi->keUser(
                $ekspor->pemohon,
                type: 'ekspor.gagal',
                title: 'Ekspor data gagal disusun',
                body: 'Kami sudah dikabari dan sedang memeriksanya. Silakan coba lagi, atau hubungi kami lewat Bantuan.',
                url: route('settings'),
                level: 'danger',
                dedupe: "ekspor-gagal:{$ekspor->id}",
            );
        }
    }

    /** @return array{0: string, 1: int} path relatif dan ukurannya */
    private function susun(DataExport $ekspor): array
    {
        $workspace = $ekspor->workspace;
        $disk = Storage::disk('local');
        $relatif = "ekspor/{$workspace->id}/pesanvo-ekspor-{$workspace->slug}-".now()->format('Ymd-His').'.zip';

        $disk->makeDirectory(dirname($relatif));
        $penuh = $disk->path($relatif);

        $zip = new ZipArchive;

        if ($zip->open($penuh, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Berkas ZIP tidak dapat dibuat di '.$penuh);
        }

        $zip->addFromString('BACA_SAYA.txt', $this->bacaSaya($workspace));
        $zip->addFromString('workspace.json', $this->json([
            'nama' => $workspace->name,
            'slug' => $workspace->slug,
            'status' => $workspace->status,
            'paket' => $workspace->plan_slug,
            'dibuat_pada' => $workspace->created_at?->toIso8601String(),
            'batas' => [
                'maks_nomor' => $workspace->max_sessions,
                'kuota_pesan_bulanan' => $workspace->monthly_message_quota,
                'batas_api_per_menit' => $workspace->api_rate_limit_per_minute,
            ],
            'penagihan' => [
                'nama' => $workspace->billing_name,
                'email' => $workspace->billing_email,
                'telepon' => $workspace->billing_phone,
            ],
            'saldo' => $workspace->balance,
        ]));

        $zip->addFromString('anggota.json', $this->json(
            $workspace->members()->get(['users.id', 'name', 'email', 'phone', 'company'])->toArray()
        ));

        $zip->addFromString('nomor-whatsapp.json', $this->json(
            $workspace->sessions()->get(['id', 'name', 'status', 'phone_number', 'push_name', 'connected_at', 'created_at'])->toArray()
        ));

        /*
         | API key: hash DAN ciphertext dibuang dengan sengaja.
         |
         | Berkas ini diunduh lalu berpindah lewat email, chat, dan cloud drive
         | orang. Kunci yang ikut di dalamnya adalah kunci yang bocor di tempat
         | yang tidak pernah kami lihat — dan pemiliknya tidak akan pernah tahu
         | dari mana bocornya. Yang berhak melihat kuncinya tetap bisa
         | membukanya di dashboard.
        */
        $zip->addFromString('api-key.json', $this->json(
            $workspace->apiKeys()->get(['id', 'name', 'prefix', 'scopes', 'last_used_at', 'revoked_at', 'created_at'])->toArray()
        ));

        $zip->addFromString('webhook.json', $this->json(
            $workspace->webhooks()->get(['id', 'url', 'events', 'is_active', 'api_key_id', 'created_at'])->toArray()
        ));

        $zip->addFromString('tagihan.json', $this->json(
            $workspace->invoices()->get(['id', 'number', 'status', 'plan_slug', 'period', 'amount', 'tax_amount', 'discount_amount', 'total', 'due_at', 'paid_at', 'created_at'])->toArray()
        ));

        $zip->addFromString('template.json', $this->json(
            $workspace->templates()->get(['id', 'name', 'slug', 'body', 'variables', 'is_active', 'created_at'])->toArray()
        ));

        $zip->addFromString('pesan.csv', $this->pesanCsv($workspace));

        $zip->close();

        clearstatcache(true, $penuh);

        return [$relatif, (int) filesize($penuh)];
    }

    /**
     * Seluruh pesan sebagai CSV, disusun per potongan.
     *
     * `chunkById` dan bukan `chunk`: `chunk` memakai OFFSET, dan baris yang
     * terhapus retensi di tengah proses menggeser seluruh halaman berikutnya —
     * ekspor yang kehilangan pesan tanpa satu pun galat.
     */
    private function pesanCsv($workspace): string
    {
        $keluar = fopen('php://temp/maxmemory:8388608', 'r+');

        fputcsv($keluar, [
            'id', 'tanggal', 'arah', 'nomor_tujuan', 'nomor_pengirim',
            'tipe', 'isi', 'status', 'galat', 'terkirim_pada', 'dibaca_pada',
        ]);

        $workspace->messages()->orderBy('id')->chunkById(1000, function ($pesan) use ($keluar): void {
            foreach ($pesan as $p) {
                fputcsv($keluar, [
                    $p->id,
                    $p->created_at?->toIso8601String(),
                    $p->direction,
                    $p->to_number,
                    $p->from_number,
                    $p->type,
                    $p->body,
                    $p->status,
                    $p->error,
                    $p->sent_at?->toIso8601String(),
                    $p->read_at?->toIso8601String(),
                ]);
            }
        });

        rewind($keluar);
        $isi = stream_get_contents($keluar);
        fclose($keluar);

        // BOM UTF-8 supaya Excel di Windows tidak merusak huruf beraksen dan
        // emoji — dan isi pesan WhatsApp hampir selalu memuat keduanya.
        return "\xEF\xBB\xBF".$isi;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function bacaSaya($workspace): string
    {
        return implode("\n", [
            'EKSPOR DATA WORKSPACE — '.$workspace->name,
            'Disusun '.now()->translatedFormat('j F Y, H:i').' WIB oleh '.config('app.name'),
            '',
            'ISI BERKAS INI',
            '  workspace.json      Keterangan workspace, batas, dan data penagihan',
            '  anggota.json        Anggota workspace',
            '  nomor-whatsapp.json Nomor yang pernah ditautkan beserta keadaannya',
            '  api-key.json        Daftar API key TANPA nilai kuncinya',
            '  webhook.json        Alamat webhook dan kejadian yang didengarkan',
            '  tagihan.json        Riwayat tagihan',
            '  pesan.csv           Seluruh pesan masuk dan keluar yang masih tersimpan',
            '',
            'YANG SENGAJA TIDAK DISERTAKAN',
            '  - Nilai API key. Berkas ini berpindah lewat email dan chat; kunci di',
            '    dalamnya adalah kunci yang bocor tanpa pemiliknya pernah tahu.',
            '    Kuncinya tetap bisa dibuka di dashboard.',
            '  - Kredensial sesi WhatsApp. Setara dengan WhatsApp Web yang selalu',
            '    terbuka, dan tidak berguna di luar sistem kami.',
            '  - Lampiran media. Ukurannya bisa berkali lipat dari seluruh isi ini;',
            '    hubungi kami lewat Bantuan kalau Anda memerlukannya.',
            '',
            'PERLU DIKETAHUI',
            '  pesan.csv hanya memuat pesan yang MASIH tersimpan. Riwayat dipangkas',
            '  otomatis sesuai masa retensi paket Anda, dan yang sudah terpangkas',
            '  tidak dapat dipulihkan oleh siapa pun.',
            '',
            '  Berkas ini memuat isi percakapan Anda dan pelanggan Anda. Simpan',
            '  seperti Anda menyimpan data pelanggan yang lain.',
            '',
            'Tautan unduhnya berlaku 7 hari, setelah itu berkas ini kami hapus.',
        ]);
    }
}
