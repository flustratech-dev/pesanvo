<?php

namespace App\Services\Notifications;

use App\Models\AppSetting;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\MessageDispatcher;
use App\Support\EngineError;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pemberitahuan WhatsApp, dikirim lewat gateway kami sendiri.
 *
 * Produk ini menjual pengiriman notifikasi WhatsApp; tidak memakainya untuk
 * memberi tahu pelanggannya sendiri adalah tukang kayu yang rumahnya bocor.
 * Lebih dari itu, di sini WhatsApp adalah **satu-satunya** jalur yang benar
 * sampai: `users` tidak menyimpan nomor telepon, email belum dikonfigurasi, dan
 * spanduk di dashboard hanya terlihat oleh orang yang kebetulan sedang membuka
 * dashboard — justru yang paling jarang dilakukan pelanggan yang gateway-nya
 * berjalan lancar.
 *
 * Tiga aturan yang berlaku di seluruh kelas ini:
 *
 * 1. **Tidak pernah melempar galat.** Notifikasi itu pelengkap; tagihan tetap
 *    harus lunas meski pesannya gagal terkirim. Semua kegagalan dicatat, tidak
 *    ada yang merambat ke pemanggil.
 *
 * 2. **Dikirim dari nomor Pesanvo, bukan nomor pelanggan.** Memakai sesi
 *    pelanggan berarti kuota mereka yang terpotong dan laporan spam-nya jatuh
 *    ke nomor mereka.
 *
 * 3. **Satu peristiwa, satu pesan.** Penanda di cache mencegah job harian yang
 *    berjalan dua kali mengirim pemberitahuan yang sama dua kali — dan pesan
 *    kembar soal uang membuat orang mengira ia ditagih dua kali.
 */
class WhatsAppNotifier
{
    public function __construct(private readonly MessageDispatcher $dispatcher) {}

    /**
     * Mengirim ke nomor tagihan sebuah workspace.
     *
     * `$sekali` adalah kunci penanda: kalau diisi, pesan dengan kunci yang sama
     * tidak dikirim lagi selama masa yang ditentukan.
     */
    public function toWorkspace(Workspace $workspace, string $pesan, ?string $sekali = null, int $ingatJam = 24): bool
    {
        if ($workspace->isExempt() || blank($workspace->billing_phone)) {
            return false;
        }

        return $this->send($workspace->billing_phone, $pesan, $sekali, $ingatJam);
    }

    /**
     * Mengirim ke nomor tim kami sendiri.
     *
     * Dipakai untuk hal yang menuntut tindakan manusia — terutama bukti
     * pembayaran baru, karena selama pencocokan masih manual, tagihan hanya
     * menjadi lunas kalau ada orang yang membukanya di panel.
     */
    public function toAdmin(string $pesan, ?string $sekali = null, int $ingatJam = 24, array $media = []): bool
    {
        $nomor = config('billing.admin_phone');

        if (blank($nomor)) {
            return false;
        }

        return $this->send($nomor, $pesan, $sekali, $ingatJam, $media);
    }

    /**
     * Mengirim pemberitahuan ke nomor WhatsApp perorangan (misal: mitra reseller).
     */
    public function toPhone(string $nomor, string $pesan, ?string $sekali = null, int $ingatJam = 24, array $media = []): bool
    {
        if (blank($nomor)) {
            return false;
        }

        return $this->send($nomor, $pesan, $sekali, $ingatJam, $media);
    }

    /**
     * Apakah pemberitahuan bisa dikirim sama sekali.
     *
     * Dipakai panel admin untuk menampilkan keadaannya secara jujur: konfigurasi
     * yang belum lengkap membuat seluruh pemberitahuan diam tanpa satu pun
     * gejala, dan diam adalah gejala yang paling sulit disadari.
     */
    public function ready(): bool
    {
        return $this->senderSession() !== null;
    }

    /**
     * Mengirim pesan percobaan, dan menjelaskan kenapa kalau gagal.
     *
     * `ready()` cuma menjawab ya/tidak, dan itu tidak cukup untuk memasang:
     * "tidak siap" bisa berarti workspace pengirim belum dipilih, sudah dipilih
     * tapi nomornya belum discan, atau sudah discan tapi engine sedang mati.
     * Ketiganya butuh tindakan yang berbeda, dan menebaknya sendiri adalah
     * pekerjaan yang tidak perlu ada.
     *
     * Sengaja TIDAK memakai penanda sekali-kirim: percobaan yang menolak
     * berjalan dua kali tidak ada gunanya.
     *
     * @return array{berhasil: bool, pesan: string}
     */
    public function kirimTes(string $tujuan): array
    {
        $nomor = PhoneNumber::normalize($tujuan);

        if ($nomor === null) {
            return ['berhasil' => false, 'pesan' => 'Nomor tujuan tidak dikenali. Pakai format 08xx atau 62xx.'];
        }

        $workspaceId = AppSetting::ambil('notify_workspace_id', config('billing.notify_workspace_id'));

        if (blank($workspaceId)) {
            return ['berhasil' => false, 'pesan' => 'Workspace pengirim belum dipilih di halaman ini.'];
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return ['berhasil' => false, 'pesan' => 'Workspace pengirim yang tersimpan sudah tidak ada. Pilih ulang.'];
        }

        if (! $this->senderSession()) {
            $jumlah = $workspace->sessions()->count();

            return ['berhasil' => false, 'pesan' => $jumlah === 0
                ? "Workspace {$workspace->name} belum punya sesi sama sekali. Buat sesi di dashboard, lalu scan QR-nya."
                : "Sesi di workspace {$workspace->name} ada tapi belum tersambung. Buka menu Sesi WhatsApp, klik Hubungkan, lalu scan QR-nya."];
        }

        try {
            $this->dispatcher->queue($this->senderSession(), $nomor, [
                'body' => '*Tes pemberitahuan Pesanvo*

'
                    .'Kalau pesan ini sampai, jalur pemberitahuan sudah benar: tagihan, pengingat masa '
                    .'berlaku, dan kabar bukti pembayaran akan terkirim lewat nomor yang sama.

'
                    .'Dikirim '.now()->translatedFormat('j F Y, H:i').'.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tes pemberitahuan gagal', ['error' => $e->getMessage()]);

            return ['berhasil' => false, 'pesan' => EngineError::pesan($e)];
        }

        return ['berhasil' => true, 'pesan' => 'Pesan masuk antrean pengiriman ke '.PhoneNumber::mask($nomor)
            .'. Kalau tidak sampai dalam satu menit, periksa halaman Lalu Lintas Pesan.'];
    }

    private function send(string $tujuan, string $pesan, ?string $sekali, int $ingatJam, array $media = []): bool
    {
        $penanda = $sekali ? "wa-notif:{$sekali}" : null;

        if ($penanda && Cache::has($penanda)) {
            return false;
        }

        $pengirim = $this->senderSession();

        if (! $pengirim) {
            Log::info('Pemberitahuan WhatsApp dilewati: tidak ada sesi pengirim yang siap.', [
                'tujuan' => PhoneNumber::mask($tujuan),
            ]);

            return false;
        }

        try {
            $attributes = ['body' => $pesan];
            if (! empty($media)) {
                $attributes = array_merge($attributes, [
                    'type' => $media['type'] ?? 'document',
                    'media_path' => $media['media_path'] ?? null,
                    'media_mime' => $media['media_mime'] ?? 'application/pdf',
                    'media_filename' => $media['media_filename'] ?? 'document.pdf',
                ]);
            }

            $this->dispatcher->queue($pengirim, $tujuan, $attributes);

            if ($penanda) {
                Cache::put($penanda, true, now()->addHours($ingatJam));
            }

            return true;
        } catch (\Throwable $e) {
            // Notifikasi itu pelengkap. Yang memanggilnya sedang mengerjakan
            // sesuatu yang jauh lebih penting — menandai tagihan lunas,
            // menangguhkan langganan — dan itu tidak boleh gagal gara-gara ini.
            Log::warning('Pemberitahuan WhatsApp gagal dikirim.', [
                'tujuan' => PhoneNumber::mask($tujuan),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sesi milik Pesanvo yang dipakai mengirim.
     *
     * Dikonfigurasi lewat env dan bukan dipilih otomatis dari sesi mana pun
     * yang kebetulan tersambung: memilih sendiri berarti suatu hari
     * pemberitahuan tagihan keluar dari nomor pelanggan lain.
     */
    /**
     * Sesi yang dipakai mengirim SELURUH pemberitahuan.
     *
     * Urutannya disengaja: setelan dari panel admin lebih dulu, baru env.
     *
     * Dulu hanya env, dan itu menghasilkan urutan pemasangan yang mustahil —
     * id workspace baru ada setelah workspace-nya dibuat lewat dashboard, jadi
     * mengisinya berarti deploy, buat workspace, salin id, deploy lagi. Selama
     * dua deploy itu seluruh pemberitahuan diam tanpa satu pun gejala. Sekarang
     * cukup dipilih dari halaman Pemberitahuan di panel admin.
     *
     * Nomor istimewa didahulukan di dalam workspace itu: kalau nomor perusahaan
     * ditautkan di sana, dialah yang mengirim — dan ia tidak pernah ikut
     * dilepas saat ada langganan yang mati.
     */
    private function senderSession(): ?WaSession
    {
        $workspaceId = AppSetting::ambil('notify_workspace_id', config('billing.notify_workspace_id'));

        if (blank($workspaceId)) {
            return null;
        }

        $sesi = Workspace::find($workspaceId)
            ?->sessions()
            ->where('status', 'connected')
            ->get();

        if (! $sesi || $sesi->isEmpty()) {
            return null;
        }

        return $sesi->first(fn (WaSession $s) => $s->isSpecial()) ?? $sesi->first();
    }
}
