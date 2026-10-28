# Contoh Integrasi

Kode siap pakai untuk menyambungkan aplikasi Anda ke gateway.

---

## PHP / Laravel

Simpan kredensial di `.env`:

```env
WA_GATEWAY_URL=https://wa.pesanvo.id
WA_GATEWAY_KEY=vwa_xxxxxxxx.xxxxxxxxxxxx
WA_GATEWAY_SESSION=
```

Blok itu bisa disalin langsung dari halaman **API Keys** setelah kunci dibuat. `WA_GATEWAY_SESSION` boleh tetap kosong — lihat [Mulai Cepat](MULAI_CEPAT.md#simpan-kuncinya-di-env-bukan-di-dalam-kode).

`config/whatsapp.php`:

```php
<?php

return [
    'url' => env('WA_GATEWAY_URL', 'https://wa.pesanvo.id'),
    'key' => env('WA_GATEWAY_KEY'),

    // Kosong = kirim dari nomor yang sedang terhubung. Isi dengan ID sesi
    // hanya kalau workspace Anda punya beberapa nomor.
    'session' => env('WA_GATEWAY_SESSION'),

    // Sengaja pendek. Notifikasi WhatsApp adalah pelengkap, bukan pengganti
    // email — gateway yang lambat tidak boleh menahan permintaan pengguna.
    'timeout' => 5,
];
```

`app/Services/WhatsAppGateway.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppGateway
{
    /**
     * Selalu mengembalikan bool, tidak pernah melempar exception.
     *
     * Kegagalan mengirim WhatsApp tidak boleh menggagalkan proses yang
     * memanggilnya — invoice tetap harus tersimpan meski notifikasinya gagal.
     */
    public static function kirim(?string $nomor, string $pesan): bool
    {
        if (! $nomor || ! config('whatsapp.key')) {
            return false;
        }

        try {
            $response = Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', config('whatsapp.key'))
                ->timeout(config('whatsapp.timeout'))
                ->acceptJson()
                ->post('/api/v1/messages/text', [
                    'to' => $nomor,
                    'message' => $pesan,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp tidak bisa dihubungi', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('Gateway WhatsApp menolak pesan', [
            'status' => $response->status(),
            'error' => $response->json('error.message'),
        ]);

        return false;
    }

    public static function template(?string $nomor, string $template, array $variabel = []): bool
    {
        if (! $nomor || ! config('whatsapp.key')) {
            return false;
        }

        try {
            return Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', config('whatsapp.key'))
                ->timeout(config('whatsapp.timeout'))
                ->post('/api/v1/messages/template', [
                    'to' => $nomor,
                    'template' => $template,
                    'variables' => $variabel,
                ])
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('Template WhatsApp gagal', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
```

Pemakaian:

```php
use App\Services\WhatsAppGateway;

WhatsAppGateway::kirim($pelanggan->telepon, "Pesanan #{$pesanan->id} sudah dikirim.");

WhatsAppGateway::template($pelanggan->telepon, 'pengingat-invoice', [
    'nama' => $pelanggan->nama,
    'nomor' => $invoice->nomor,
]);
```

### Lewat antrean Laravel

Untuk pengiriman yang tidak mendesak, jangan panggil langsung di controller — antrekan supaya pengguna tidak menunggu:

```php
class KirimNotifikasiWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $nomor,
        public readonly string $pesan,
    ) {}

    public function handle(): void
    {
        WhatsAppGateway::kirim($this->nomor, $this->pesan);
    }
}

KirimNotifikasiWhatsApp::dispatch($pelanggan->telepon, 'Pesanan diterima.');
```

---

## Node.js

```js
const WA_URL = process.env.WA_GATEWAY_URL ?? 'https://wa.pesanvo.id';
const WA_KEY = process.env.WA_GATEWAY_KEY;

export async function kirimWhatsApp(nomor, pesan) {
    if (!nomor || !WA_KEY) return false;

    try {
        const res = await fetch(`${WA_URL}/api/v1/messages/text`, {
            method: 'POST',
            headers: {
                'X-Api-Key': WA_KEY,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ to: nomor, message: pesan }),
            signal: AbortSignal.timeout(5000),
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            console.warn('Gateway menolak pesan:', err.error?.message ?? res.status);

            return false;
        }

        return true;
    } catch (e) {
        console.warn('Gateway tidak bisa dihubungi:', e.message);

        return false;
    }
}
```

---

## Python

```python
import os
import logging
import requests

WA_URL = os.getenv("WA_GATEWAY_URL", "https://wa.pesanvo.id")
WA_KEY = os.getenv("WA_GATEWAY_KEY")


def kirim_whatsapp(nomor: str, pesan: str) -> bool:
    if not nomor or not WA_KEY:
        return False

    try:
        r = requests.post(
            f"{WA_URL}/api/v1/messages/text",
            headers={"X-Api-Key": WA_KEY},
            json={"to": nomor, "message": pesan},
            timeout=5,
        )
    except requests.RequestException as e:
        logging.warning("Gateway tidak bisa dihubungi: %s", e)
        return False

    if not r.ok:
        logging.warning("Gateway menolak pesan: %s", r.text)
        return False

    return True
```

---

## Mengirim lampiran

### PHP

```php
Http::withHeader('X-Api-Key', config('whatsapp.key'))
    ->attach('file', file_get_contents($path), 'invoice.pdf')
    ->post(config('whatsapp.url').'/api/v1/messages/media', [
        'to' => $nomor,
        'caption' => 'Invoice Agustus 2026',
        'type' => 'document',
    ]);
```

### Node.js

```js
const form = new FormData();
form.append('to', nomor);
form.append('caption', 'Invoice Agustus 2026');
form.append('type', 'document');
form.append('file', new Blob([buffer]), 'invoice.pdf');

await fetch(`${WA_URL}/api/v1/messages/media`, {
    method: 'POST',
    headers: { 'X-Api-Key': WA_KEY },
    body: form,
});
```

---

## Menunggu sampai benar-benar terkirim

Untuk sebagian besar kebutuhan, tidak perlu menunggu — kirim lalu lanjutkan.

Kalau memang perlu memastikan (misalnya kode verifikasi), periksa statusnya:

```php
$id = $response->json('data.id');

for ($i = 0; $i < 30; $i++) {
    sleep(2);

    $status = Http::withHeader('X-Api-Key', $key)
        ->get("{$base}/api/v1/messages/{$id}")
        ->json('data.status');

    if (in_array($status, ['delivered', 'read'], true)) {
        return true;
    }

    if ($status === 'failed') {
        return false;
    }
}
```

Cara yang lebih hemat: daftarkan [webhook](WEBHOOK.md) `message.status` dan biarkan kami yang mengabari.

---

## Kesalahan yang sering terjadi

| Kesalahan | Akibatnya |
|---|---|
| Menganggap `202` berarti sudah terkirim | Status sebenarnya menyusul lewat ID pesan atau webhook |
| Membuat perulangan sendiri untuk kirim massal | Nomor berisiko diblokir. Pakai `/messages/bulk` |
| Timeout terlalu panjang | Pengguna menunggu lama hanya karena notifikasi |
| Melempar exception saat gateway gagal | Proses utama ikut gagal padahal notifikasi cuma pelengkap |
| API key ditaruh di kode JavaScript halaman web | Siapa pun bisa membacanya lewat Inspect Element |
| Satu API key untuk semua aplikasi | Kunci bocor berarti semua aplikasi harus diganti kuncinya |
| Tidak memverifikasi tanda tangan webhook | Endpoint bisa dikirimi data palsu |
