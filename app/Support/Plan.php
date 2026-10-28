<?php

namespace App\Support;

use App\Models\AppSetting;
use InvalidArgumentException;

/**
 * Satu paket langganan, dibaca dari `config/plans.php`.
 *
 * Ini bukan model Eloquent dan tidak akan pernah jadi model: paket adalah
 * keputusan produk yang hidup di kode, sedangkan yang disimpan di database
 * hanyalah slug paket mana yang sedang dipakai sebuah workspace.
 *
 * Konsekuensi yang perlu disadari saat membaca kode ini: paket yang slug-nya
 * tidak ada lagi di katalog TIDAK melempar galat saat dimuat lewat `cari()`.
 * Workspace yang berlangganan paket yang dihapus dari katalog harus tetap bisa
 * membuka dashboard-nya; yang terjadi ia jatuh ke paket bawaan, bukan ke
 * halaman galat.
 */
class Plan
{
    private function __construct(
        public readonly string $slug,
        public readonly array $attributes,
    ) {}

    /**
     * Memuat katalog paket dari config dan menimpanya dengan harga dinamis dari database bila ada.
     */
    public static function catalog(): array
    {
        $catalog = config('plans.catalog') ?? [];
        $customPrices = AppSetting::ambil('plan_prices');

        if ($customPrices && is_string($customPrices)) {
            $decoded = json_decode($customPrices, true);
            if (is_array($decoded)) {
                foreach ($decoded as $planSlug => $prices) {
                    if (isset($catalog[$planSlug]) && is_array($prices)) {
                        foreach ($prices as $key => $val) {
                            if ($val !== null && $val !== '') {
                                $catalog[$planSlug][$key] = (int) $val;
                            }
                        }
                    }
                }
            }
        }

        return $catalog;
    }

    /**
     * Paket berdasarkan slug. Melempar galat kalau slug-nya tidak dikenal —
     * dipakai di jalur tempat slug datang dari kode kita sendiri.
     */
    public static function get(string $slug): self
    {
        $catalog = self::catalog();

        if (! isset($catalog[$slug])) {
            throw new InvalidArgumentException("Paket '{$slug}' tidak ada di katalog.");
        }

        return new self($slug, $catalog[$slug]);
    }

    /**
     * Paket berdasarkan slug, dengan paket bawaan sebagai jaring pengaman.
     * Dipakai di jalur tempat slug datang dari database — termasuk baris lama
     * yang menyebut paket yang sudah tidak ada.
     */
    public static function find(?string $slug): self
    {
        $catalog = self::catalog();

        if ($slug === null || ! isset($catalog[$slug])) {
            $slug = config('plans.default');
        }

        return new self($slug, $catalog[$slug]);
    }

    public static function exists(?string $slug): bool
    {
        return $slug !== null && isset(self::catalog()[$slug]);
    }

    /**
     * Paket yang bisa dibeli, urut seperti di config.
     *
     * Paket coba gratis sengaja tidak ikut: ia muncul di katalog supaya batas
     * dan namanya dibaca dari satu tempat yang sama dengan paket lain, tapi
     * tidak ada yang bisa membelinya. Menampilkannya di halaman harga berarti
     * menawarkan tombol beli yang tidak akan pernah ada di baliknya, dan di
     * dropdown admin berarti seseorang suatu saat memindahkan pelanggan
     * berbayar ke jatah lima pesan.
     *
     * @return array<int, self>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $slug) => self::get($slug),
                array_keys(self::catalog())
            ),
            // PAYG ikut dikecualikan meski `sellable`. Ia memang bisa dibeli,
            // tapi bentuk harganya berbeda — per pesan, bukan per bulan — dan
            // seluruh pemanggil `all()` (halaman harga, dropdown admin, tabel
            // paket di /admin/sistem) merender harga bulanan. Memaksanya masuk
            // cetakan itu menghasilkan kartu bertuliskan "Rp 0/bulan".
            fn (self $plan) => $plan->isSellable() && ! $plan->isPayg()
        ));
    }

    /** Paket pay as you go, yang bentuk harganya berbeda dari yang lain. */
    public static function payg(): self
    {
        return self::get('payg');
    }

    /** Paket coba gratis untuk workspace baru. */
    public static function free(): self
    {
        return self::get(config('plans.free'));
    }

    public function isSellable(): bool
    {
        return (bool) ($this->attributes['sellable'] ?? true);
    }

    public function isFree(): bool
    {
        return $this->slug === config('plans.free');
    }

    /**
     * Harga paket ini per pesan, bukan per bulan.
     *
     * Yang membatasi pengiriman workspace PAYG adalah saldonya, bukan kuota
     * bulanan maupun tanggal berakhir — jadi seluruh kode yang membaca
     * `current_period_end` harus tahan `null` untuk workspace seperti ini.
     */
    public function isPayg(): bool
    {
        return (bool) ($this->attributes['payg'] ?? false);
    }

    public function name(): string
    {
        return $this->attributes['name'];
    }

    public function tagline(): string
    {
        return $this->attributes['tagline'];
    }

    /** @return array<int, string> */
    public function features(): array
    {
        return $this->attributes['features'];
    }

    public function isHighlighted(): bool
    {
        return (bool) $this->attributes['highlight'];
    }

    /**
     * Harga untuk satu periode penagihan, dalam rupiah penuh.
     *
     * Harga tahunan diturunkan dari harga bulanan, tidak ditulis terpisah:
     * dua angka yang harus dijaga tetap selaras adalah dua angka yang cepat
     * atau lambat akan berbeda tanpa ada yang menyadarinya.
     */
    public function price(string $period): int
    {
        $multiplier = (int) AppSetting::ambil('plans_yearly_multiplier', config('plans.yearly_multiplier', 10));

        return $period === 'yearly'
            ? $this->attributes['price_monthly'] * $multiplier
            : $this->attributes['price_monthly'];
    }

    public function monthlyPrice(): int
    {
        return $this->attributes['price_monthly'];
    }

    /**
     * Harga perkenalan untuk pembelian PERTAMA, atau `null` kalau tidak ada.
     *
     * Sengaja mengembalikan `null` alih-alih harga normal: pemanggilnya perlu
     * bisa membedakan "tidak ada promo" dari "promo kebetulan sebesar harga
     * normal", karena yang pertama tidak boleh menampilkan harga tercoret.
     */
    public function introPrice(string $period): ?int
    {
        $kunci = $period === 'yearly' ? 'intro_price_yearly' : 'intro_price_monthly';
        $harga = $this->attributes[$kunci] ?? null;

        if ($harga === null) {
            return null;
        }

        // Promo yang lebih mahal dari harga normal hampir pasti salah ketik,
        // dan diam-diam menagih lebih banyak jauh lebih buruk daripada
        // kehilangan promonya.
        return $harga < $this->price($period) ? (int) $harga : null;
    }

    public function hasIntro(string $period): bool
    {
        return $this->introPrice($period) !== null;
    }

    /** Berapa rupiah yang dihemat pembeli pertama. */
    public function introSaving(string $period): int
    {
        return $this->price($period) - ($this->introPrice($period) ?? $this->price($period));
    }

    /** Persen potongan perkenalan, dibulatkan — untuk lencana "hemat 47%". */
    public function introPercent(string $period): int
    {
        $normal = $this->price($period);

        return $normal > 0 ? (int) round($this->introSaving($period) / $normal * 100) : 0;
    }

    /**
     * Batas yang ditegakkan aplikasi. Bentuk array-nya sengaja sama persis
     * dengan nama kolom di tabel `workspaces`, supaya menerapkan paket ke
     * workspace tidak perlu penerjemahan nama satu per satu.
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        return [
            'max_sessions' => $this->attributes['max_sessions'],
            'monthly_message_quota' => $this->attributes['monthly_message_quota'],
            'api_rate_limit_per_minute' => $this->attributes['api_rate_limit_per_minute'],
        ];
    }

    public function maxApiKeys(): int
    {
        return $this->attributes['max_api_keys'];
    }

    public function maxMembers(): int
    {
        return $this->attributes['max_members'];
    }

    public function messageRetentionDays(): int
    {
        return $this->attributes['message_retention_days'];
    }
}
