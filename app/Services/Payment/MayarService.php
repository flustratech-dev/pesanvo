<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\Workspace;
use App\Support\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MayarService
{
    private bool $isActive;

    private ?string $apiKey;

    private string $apiUrl;

    private ?string $webhookToken;

    public function __construct()
    {
        $setting = PaymentGatewaySetting::mayar();
        $this->isActive = (bool) ($setting['is_active'] ?? true);

        $rawApiKey = filled($setting['api_key'] ?? null)
            ? (string) $setting['api_key']
            : (string) config('services.mayar.api_key', '');

        // Bersihkan whitespace, tanda kutip ("..."), atau awalan "Bearer "
        $cleanedKey = trim($rawApiKey);
        $cleanedKey = trim($cleanedKey, '"\'');
        $cleanedKey = trim(preg_replace('/^Bearer\s+/i', '', $cleanedKey));
        $this->apiKey = filled($cleanedKey) ? $cleanedKey : null;

        $rawApiUrl = filled($setting['api_url'] ?? null)
            ? (string) $setting['api_url']
            : (string) config('services.mayar.api_url', 'https://api.mayar.id/hl/v2');
        $this->apiUrl = rtrim(trim(trim($rawApiUrl), '"\''), '/');

        $rawWebhook = filled($setting['webhook_token'] ?? null)
            ? (string) $setting['webhook_token']
            : (string) config('services.mayar.webhook_token', '');
        $this->webhookToken = filled($rawWebhook) ? trim(trim($rawWebhook), '"\'') : null;
    }

    /**
     * Apakah kredensial API Mayar sudah diisi dan gateway berstatus aktif.
     */
    public function isConfigured(): bool
    {
        return $this->isActive && filled($this->apiKey);
    }

    public function getWebhookToken(): ?string
    {
        return $this->webhookToken;
    }

    /**
     * Menerbitkan invoice pembayaran di Mayar.id untuk sebuah tagihan.
     *
     * Jika invoice sudah pernah dibuat dan link masih ada, langsung kembalikan
     * link yang sudah ada agar tidak membuat duplikat invoice di Mayar.
     *
     * @return array{link: string, id: string, transactionId: ?string}
     */
    public function createInvoice(Invoice $invoice, Workspace $workspace): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payment gateway Mayar belum dikonfigurasi (API Key kosong).');
        }

        // Pakai link yang sudah ada jika belum kedaluwarsa
        if (filled($invoice->payment_url) && $invoice->payment_gateway === 'mayar' && filled($invoice->payment_reference)) {
            return [
                'link' => $invoice->payment_url,
                'id' => $invoice->payment_reference,
                'transactionId' => $invoice->payment_payload['transactionId'] ?? null,
            ];
        }

        $customerName = $workspace->billing_name ?: $workspace->name;
        $customerEmail = $workspace->billing_email ?: ($workspace->owner_email ?: $workspace->owner?->email);
        $rawPhone = $workspace->billing_phone ?: ($workspace->owner?->phone ?: '');
        $customerMobile = preg_replace('/[^0-9]/', '', (string) $rawPhone);
        if (strlen($customerMobile) < 10) {
            $customerMobile = '081200000000';
        }

        // Susun item tagihan
        $itemDesc = $invoice->isTopup()
            ? 'Pengisian Saldo PAYG Pesanvo'
            : "Paket {$invoice->plan()->name()} ({$invoice->periodLabel()}) — Pesanvo";

        if ($invoice->discount_amount > 0) {
            $itemDesc .= ' (Diskon Rp '.number_format($invoice->discount_amount, 0, ',', '.').')';
        }

        $items = [
            [
                'quantity' => 1,
                'rate' => (int) $invoice->total,
                'description' => $itemDesc,
            ],
        ];

        $expiredAt = $invoice->due_at
            ? $invoice->due_at->toIso8601String()
            : now()->addDays(config('billing.invoice_due_days', 3))->toIso8601String();

        $payload = [
            'name' => $customerName,
            'email' => $customerEmail,
            'mobile' => $customerMobile,
            'description' => "Tagihan {$invoice->number} — {$workspace->name}",
            'redirectUrl' => request()->hasHeader('host')
                ? url("/billing/invoices/{$invoice->id}")
                : route('billing.invoice', $invoice),
            'expiredAt' => $expiredAt,
            'items' => $items,
            'extraData' => [
                'invoice_id' => (string) $invoice->id,
                'invoice_number' => (string) $invoice->number,
                'workspace_id' => (string) $workspace->id,
            ],
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->post("{$this->apiUrl}/invoices/create", $payload);

            if (! $response->successful()) {
                Log::error('Mayar API error create invoice', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'invoice' => $invoice->number,
                ]);

                if ($response->status() === 401) {
                    throw new RuntimeException('Mayar: Kredensial API tidak valid atau tidak memiliki otorisasi (401 Unauthorized). Pastikan API Key diisi dengan benar dari Dashboard Mayar (Menu Integrasi / Pengembang) dan cocok dengan URL API.');
                }

                $body = $response->json() ?? [];
                $detail = '';
                if (isset($body['data']) && is_array($body['data'])) {
                    $first = reset($body['data']);
                    if (is_array($first) && ! empty($first['message'])) {
                        $detail = ': '.$first['message'];
                    } elseif (is_array($first) && is_array(reset($first))) {
                        $nestedFirst = reset($first);
                        $detail = ': '.(is_array($nestedFirst) ? ($nestedFirst['message'] ?? reset($nestedFirst)) : $nestedFirst);
                    } elseif (is_string($first)) {
                        $detail = ': '.$first;
                    }
                }

                $pesan = ($response->json('messages') ?? 'Gagal menghubungi server pembayaran Mayar.').$detail;
                throw new RuntimeException("Mayar: {$pesan}");
            }

            $data = $response->json('data') ?? [];
            $link = $data['link'] ?? null;
            $mayarId = $data['id'] ?? null;
            $transactionId = $data['transactionId'] ?? null;

            if (blank($link) || blank($mayarId)) {
                throw new RuntimeException('Respons Mayar tidak memuat tautan pembayaran yang valid.');
            }

            $invoice->forceFill([
                'payment_gateway' => 'mayar',
                'payment_reference' => $mayarId,
                'payment_url' => $link,
                'payment_payload' => $data,
            ])->save();

            return [
                'link' => $link,
                'id' => $mayarId,
                'transactionId' => $transactionId,
            ];
        } catch (\Throwable $e) {
            Log::error('Exception creating Mayar invoice: '.$e->getMessage(), [
                'invoice' => $invoice->number,
            ]);

            throw new RuntimeException('Gagal membuat sesi pembayaran: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Mengambil detail tagihan dari Mayar untuk verifikasi silang.
     */
    public function getInvoiceDetail(string $mayarId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get("{$this->apiUrl}/invoices/{$mayarId}");

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal verifikasi status invoice ke Mayar API: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Membuat Dynamic QRIS langsung dari Mayar API.
     *
     * @return array{url: string, qrString: string, amount: int}|null
     */
    public function createDynamicQris(Invoice $invoice): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post("{$this->apiUrl}/qr-codes/create", [
                    'amount' => (int) $invoice->total,
                ]);

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat Dynamic QRIS Mayar: '.$e->getMessage());
        }

        return null;
    }
}
