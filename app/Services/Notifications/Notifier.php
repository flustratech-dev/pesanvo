<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya tempat notifikasi dibuat.
 *
 * Aturannya sama dengan `WhatsAppNotifier` dan `EmailNotifier`, dan alasannya
 * sama: **tidak pernah melempar galat.** Yang memanggilnya sedang mengerjakan
 * sesuatu yang jauh lebih penting — menandai tagihan lunas, menyimpan tiket,
 * memutus sesi — dan itu tidak boleh gagal gara-gara lonceng.
 *
 * Bedanya dari keduanya: ini satu-satunya jalur yang tidak bergantung pada apa
 * pun di luar sistem. Nomor Pesanvo terputus dan seluruh pesan WhatsApp diam;
 * `MAIL_MAILER` salah dan tidak satu pun email terkirim. Notifikasi tetap
 * sampai — dan ia juga satu-satunya yang menyimpan riwayat.
 *
 * `$dedupe` adalah kunci penjaga kabar kembar, ditegakkan indeks unik di
 * database dan bukan cuma oleh pemeriksaan di sini: job harian bisa berjalan
 * dua kali dan penjadwal bisa mendeteksi engine mati tiap menit. Lonceng yang
 * terisi tiga puluh baris sama berhenti dibaca siapa pun — kegagalan yang lebih
 * buruk daripada tidak ada notifikasi sama sekali.
 */
class Notifier
{
    /** Mengirim ke satu orang. */
    public function keUser(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $level = 'info',
        ?Workspace $workspace = null,
        ?string $dedupe = null,
        string $audience = 'workspace',
    ): ?Notification {
        try {
            return Notification::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace?->id,
                'audience' => $audience,
                'type' => $type,
                'level' => $level,
                'title' => $title,
                'body' => $body,
                'url' => self::bersihkanUrl($url),
                'dedupe_key' => $dedupe,
            ]);
        } catch (QueryException $e) {
            // Indeks unik menolak: kabar yang sama sudah pernah dikirim ke orang
            // ini. Bukan kegagalan — justru penjagaannya yang bekerja.
            if ($this->pelanggaranUnik($e)) {
                return null;
            }

            Log::warning('Notifikasi gagal dibuat.', ['type' => $type, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Notifikasi gagal dibuat.', ['type' => $type, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Mengirim ke SELURUH anggota sebuah workspace.
     *
     * Ke semua anggota, bukan cuma pemiliknya: yang memegang integrasi sering
     * bukan yang membayar, dan nomor yang terputus perlu diketahui yang
     * menjaganya — bukan yang mengurus tagihannya.
     *
     * @return int jumlah notifikasi yang benar-benar dibuat
     */
    public function keWorkspace(
        Workspace $workspace,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $level = 'info',
        ?string $dedupe = null,
    ): int {
        $dibuat = 0;

        foreach ($workspace->members()->get() as $anggota) {
            $hasil = $this->keUser(
                user: $anggota,
                type: $type,
                title: $title,
                body: $body,
                url: $url,
                level: $level,
                workspace: $workspace,
                // Kunci dibuat per workspace, bukan per orang: dua anggota
                // memang harus sama-sama menerimanya, tapi masing-masing hanya
                // sekali. Indeksnya `(user_id, dedupe_key)`, jadi kunci yang
                // sama untuk orang berbeda tetap lolos.
                dedupe: $dedupe ? "ws{$workspace->id}:{$dedupe}" : null,
            );

            if ($hasil) {
                $dibuat++;
            }
        }

        return $dibuat;
    }

    /**
     * Mengirim ke seluruh super admin.
     *
     * Ini aliran yang terpisah sama sekali dari milik pelanggan — isinya "ada
     * yang perlu dikerjakan manusia" dan "ada yang rusak tanpa gejala", dua hal
     * yang tidak berarti apa-apa bagi pemilik workspace.
     *
     * @return int jumlah notifikasi yang benar-benar dibuat
     */
    public function keAdmin(
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $level = 'info',
        ?string $dedupe = null,
        ?Workspace $workspace = null,
    ): int {
        $dibuat = 0;

        foreach (User::where('is_super_admin', true)->get() as $admin) {
            $hasil = $this->keUser(
                user: $admin,
                type: $type,
                title: $title,
                body: $body,
                url: $url,
                level: $level,
                workspace: $workspace,
                dedupe: $dedupe,
                audience: 'admin',
            );

            if ($hasil) {
                $dibuat++;
            }
        }

        return $dibuat;
    }

    /** Jumlah yang belum dibaca, untuk angka di lonceng. */
    public function belumDibaca(User $user, string $audience = 'workspace'): int
    {
        return Notification::untuk($user, $audience)->belumDibaca()->count();
    }

    /**
     * Membuang notifikasi lama yang sudah dibaca.
     *
     * Yang BELUM dibaca tidak pernah dibuang berapa pun umurnya: kabar yang
     * hilang sebelum sempat dilihat adalah persis kegagalan yang lonceng ini
     * dibuat untuk mencegahnya.
     */
    public function pangkas(int $hari = 90): int
    {
        return Notification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($hari))
            ->delete();
    }

    /**
     * Memastikan URL notifikasi aman untuk browser pengguna.
     *
     * URL yang dibuat dari callback engine (host 127.0.0.1) atau job CLI tanpa
     * APP_URL yang benar (host localhost) tidak boleh dikirim mentah ke browser
     * pengguna — browser mereka akan membuka localhost/127.0.0.1 di mesin
     * mereka sendiri (mis. port 80 Apache/XAMPP lokal) dan meledak 404.
     */
    public static function bersihkanUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // Sudah path relatif aman (diawali / tapi bukan // protocol-relative)
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (! $host) {
            return $url;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()?->getHost();

        $isInternalOrLoopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || ($appHost && strcasecmp($host, $appHost) === 0)
            || ($requestHost && strcasecmp($host, $requestHost) === 0);

        if ($isInternalOrLoopback) {
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

            return $path.$query.$fragment;
        }

        return $url;
    }

    private function pelanggaranUnik(QueryException $e): bool
    {
        // 23000 di MySQL, 23505/'UNIQUE constraint failed' di SQLite.
        return $e->getCode() === '23000'
            || $e->getCode() === '23505'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
