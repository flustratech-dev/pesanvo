<?php

namespace App\Models;

use App\Support\Plan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Langganan sebuah workspace.
 *
 * Model ini menyimpan keadaan, bukan aturan. Semua perpindahan status —
 * membayar, lewat jatuh tempo, ditangguhkan, dipulihkan — hanya boleh terjadi
 * lewat `SubscriptionService`, karena tiap perpindahan juga harus menyentuh
 * `workspaces.status`, dan dua tempat yang sama-sama boleh mengubahnya adalah
 * dua tempat yang cepat atau lambat akan tidak sepakat.
 */
class Subscription extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_slug',
        'period',
        'status',
        'current_period_start',
        'current_period_end',
        'past_due_at',
        'suspended_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'past_due_at' => 'datetime',
            'suspended_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function plan(): Plan
    {
        return Plan::find($this->plan_slug);
    }

    /**
     * Layanannya berjalan. `trialing` ikut di sini dengan sengaja: pemakai lama
     * yang sedang dalam masa pindah harus merasakan layanan yang persis sama
     * dengan kemarin, bukan versi yang dilumpuhkan sambil menunggu bayar.
     */
    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true);
    }

    /**
     * Belum pernah berlangganan sama sekali.
     *
     * Dibedakan dari `past_due` — yang satu belum pernah mulai, yang lain
     * pernah lalu berhenti — karena keduanya butuh kalimat yang berbeda di
     * depan pengguna. "Pengiriman pesan dihentikan" tidak masuk akal bagi orang
     * yang belum pernah mengirim apa pun.
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    /** Sisa hari sampai periode berakhir. Negatif berarti sudah lewat. */
    public function daysRemaining(): int
    {
        if (! $this->current_period_end) {
            return 0;
        }

        // Dibandingkan per hari kalender, bukan per 24 jam: pelanggan membaca
        // "berakhir besok" dari tanggal di kalendernya, bukan dari selisih jam.
        return now()->startOfDay()->diffInDays($this->current_period_end->startOfDay(), false);
    }

    /**
     * Langganan tanpa tanggal berakhir tidak pernah "akan berakhir".
     *
     * Paket coba gratis berada persis di keadaan itu: `trialing` dengan
     * `current_period_end` kosong. Tanpa penjagaan ini `daysRemaining()`
     * menjawab 0 — dibaca sebagai "berakhir hari ini" — dan spanduk kuning di
     * seluruh dashboard mengumumkan tanggal berakhir yang tidak ada, lalu
     * jatuh dengan galat saat mencoba mencetaknya.
     */
    public function isExpiringSoon(int $withinDays = 7): bool
    {
        if ($this->current_period_end === null) {
            return false;
        }

        $sisa = $this->daysRemaining();

        return $this->isUsable() && $sisa >= 0 && $sisa <= $withinDays;
    }

    /**
     * Kenapa nomor workspace ini sedang tidak berjalan — kalau memang karena
     * langganan, bukan karena gangguan.
     *
     * Sebelum ini, sesi yang dilepas `suspend()` cuma berubah menjadi
     * "Terputus" tanpa satu kata pun penjelasan, dan pelanggan menyimpulkan
     * satu-satunya hal yang masuk akal: ada yang rusak. Lalu mereka menekan
     * Hubungkan berulang kali, gagal terus, dan menghubungi kami untuk masalah
     * yang sebenarnya cuma tagihan belum dibayar.
     *
     * Dikembalikan `null` kalau langganannya sehat, supaya pemanggilnya tidak
     * perlu tahu aturan mana yang berlaku.
     *
     * @return array{judul: string, pesan: string}|null
     */
    public function alasanNomorBerhenti(): ?array
    {
        if ($this->isUsable()) {
            return null;
        }

        if ($this->status === 'suspended') {
            return [
                'judul' => 'Nomor dilepas karena langganan belum diperpanjang',
                'pesan' => 'Kredensialnya masih kami simpan — setelah pembayaran, nomor yang sama '
                    .'tersambung sendiri tanpa perlu scan QR lagi. WhatsApp di ponsel Anda tidak '
                    .'terpengaruh sama sekali.',
            ];
        }

        if ($this->status === 'past_due') {
            $lepas = $this->sessionsCutOffAt();

            return [
                'judul' => 'Layanan berhenti sementara',
                'pesan' => 'Pengiriman keluar, pesan masuk, dan webhook semuanya berhenti. '
                    .'WhatsApp di ponsel Anda tidak terpengaruh — pesan yang masuk tetap ada di sana, '
                    .'yang berhenti hanya pencatatan dan penerusannya ke sistem Anda'
                    .($lepas ? ', dan nomor dilepas dari gateway kalau belum dibayar sampai '.$lepas->translatedFormat('j F Y').'.' : '.'),
            ];
        }

        return null;
    }

    /** Sedang memakai jatah coba gratis. */
    public function isFreeTier(): bool
    {
        return $this->plan_slug === config('plans.free');
    }

    /**
     * Kapan sesi WhatsApp akan ikut diputus kalau tagihan tidak dibayar.
     * Null selama langganan belum lewat jatuh tempo.
     */
    public function sessionsCutOffAt(): ?CarbonInterface
    {
        return $this->past_due_at?->copy()->addDays(config('billing.grace_days'));
    }

    /** Label status dalam bahasa yang dipakai di antarmuka. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'unpaid' => 'Belum berlangganan',
            'trialing' => 'Masa percobaan',
            'active' => 'Aktif',
            'past_due' => 'Lewat jatuh tempo',
            'suspended' => 'Ditangguhkan',
            'canceled' => 'Dihentikan',
            default => $this->status,
        };
    }
}
