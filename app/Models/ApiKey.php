<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'prefix',
        'key_hash',
        'key_hash_fast',
        'key_ciphertext',
        'scopes',
        'rate_limit_per_minute',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = ['key_hash', 'key_hash_fast', 'key_ciphertext'];

    protected function casts(): array
    {
        return [
            // Dienkripsi dengan APP_KEY, bukan di-hash: kolom ini memang harus
            // bisa dibaca balik untuk ditampilkan di dashboard. Verifikasi
            // permintaan API tetap lewat key_hash yang tidak bisa dibalik.
            'key_ciphertext' => 'encrypted',
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Membuat kunci baru dan mengembalikan nilai polosnya.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Workspace $workspace, string $name, array $scopes = ['*'], ?int $createdBy = null): array
    {
        $prefix = 'vwa_'.Str::lower(Str::random(8));
        $secret = Str::random(40);
        $plain = $prefix.'.'.$secret;

        $key = self::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'prefix' => $prefix,
            // KEDUANYA diisi selama masa transisi. bcrypt di `key_hash` bukan
            // untuk dipakai — verifikasi selalu lewat `key_hash_fast` — melainkan
            // supaya kunci yang dibuat SESUDAH deploy tetap bisa diverifikasi
            // kode lama kalau harus rollback. Biayanya satu bcrypt saat kunci
            // dibuat, bukan saat dipakai; pembuatan terjadi beberapa kali seumur
            // hidup workspace, verifikasi ribuan kali sehari.
            'key_hash' => Hash::make($secret),
            'key_hash_fast' => self::hashSecret($secret),
            'key_ciphertext' => $plain,
            'scopes' => $scopes,
            'created_by' => $createdBy,
        ]);

        return [$key, $plain];
    }

    /**
     * Nilai penuh kunci, untuk ditampilkan ulang di dashboard.
     *
     * null untuk kunci yang dibuat sebelum kolom terenkripsi ada — nilainya
     * memang tidak tersimpan di mana pun dan tidak bisa dipulihkan dari hash.
     */
    public function plainKey(): ?string
    {
        return $this->key_ciphertext;
    }

    /**
     * Hash untuk rahasia API key.
     *
     * SHA-256, bukan bcrypt, dan itu keputusan sadar. bcrypt lambat DENGAN
     * SENGAJA — biayanya membeli perlindungan terhadap serangan kamus atas kata
     * sandi pilihan manusia, yang entropinya rendah. Rahasia di sini
     * `Str::random(40)` dari alfabet 62 karakter: sekitar 238 bit acak. Tidak
     * ada kamus untuk diserang, jadi biaya itu tidak membeli apa pun.
     *
     * Yang dibayar untuk kesia-siaan itu diukur, bukan diperkirakan: bcrypt
     * cost 12 memakan 276 ms CPU per pemeriksaan, dan pemeriksaan itu terjadi
     * pada SETIAP permintaan API, termasuk seluruh yang berhasil. Satu
     * pelanggan Elite yang memakai batas yang kita jual sendiri (300 permintaan
     * per menit) menghabiskan 83 detik CPU per menit — lebih dari satu core
     * penuh, di VPS 2 vCPU yang dipakai bersama MySQL dan dua puluh container
     * lain. Rate limit tidak menolong sama sekali: yang membakarnya justru lalu
     * lintas yang sah.
     *
     * Ini juga yang dilakukan Laravel Sanctum untuk token API, dengan alasan
     * yang sama persis.
     *
     * JANGAN memakai ini untuk kata sandi pengguna. Di sana bcrypt-lah yang
     * benar, dan `User` memang tetap memakainya.
     */
    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Memeriksa rahasia yang dikirim pemanggil.
     *
     * Kunci yang sudah ada di produksi masih ber-hash bcrypt, dan tidak satu
     * pun boleh berhenti bekerja karena perubahan ini — pelanggan yang API
     * key-nya tiba-tiba ditolak adalah integrasi yang mati tanpa peringatan.
     * Jadi bcrypt tetap diterima, dan kunci yang terbukti benar ditulis ulang
     * ke bentuk baru saat itu juga. Sesudah satu kali pakai, kunci mana pun
     * sudah berada di jalur cepat.
     *
     * `hash_equals` dan bukan `===`: perbandingan string biasa berhenti pada
     * byte pertama yang berbeda, dan selisih waktunya bisa dipakai menebak
     * hash-nya byte demi byte.
     */
    public function verifySecret(string $secret): bool
    {
        // 1. Jalur cepat. Semua kunci berakhir di sini setelah sekali dipakai.
        if ($this->key_hash_fast !== null) {
            return hash_equals((string) $this->key_hash_fast, self::hashSecret($secret));
        }

        // 2. Setelah `pesanvo:hash-api-bersihkan` dijalankan, `key_hash_fast`
        //    dikosongkan dan `key_hash` sendiri sudah berisi SHA-256. Hash
        //    bcrypt selalu diawali $2y$ / $2a$ / $2b$; punya kami 64 digit heksa.
        if (! str_starts_with((string) $this->key_hash, '$2')) {
            return hash_equals((string) $this->key_hash, self::hashSecret($secret));
        }

        // 3. Kunci lama yang belum pernah dipakai sejak deploy. Satu-satunya
        //    jalur yang masih membayar 276 ms, dan ia hanya dilewati sekali:
        //    kunci yang terbukti benar langsung dinaikkan.
        if (! Hash::check($secret, $this->key_hash)) {
            return false;
        }

        // `key_hash` sengaja TIDAK ditimpa. Selama ia masih bcrypt, rollback
        // cukup mengembalikan kode — kode lama membaca kolom yang sama seperti
        // biasa dan tidak pernah tahu `key_hash_fast` ada.
        $this->forceFill(['key_hash_fast' => self::hashSecret($secret)])->saveQuietly();

        return true;
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function allows(string $scope): bool
    {
        $scopes = $this->scopes ?? ['*'];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
