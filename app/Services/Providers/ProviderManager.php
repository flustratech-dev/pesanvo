<?php

namespace App\Services\Providers;

use App\Models\WaSession;
use InvalidArgumentException;

/**
 * Memilih provider untuk sebuah sesi.
 *
 * Sekarang hanya ada satu: `wwebjs`. Kolom `wa_sessions.driver` dipertahankan
 * sebagai pencatat, bukan pilihan — pelanggan tidak pernah diminta memilihnya,
 * dan API menolak nilai selain `wwebjs`. Meminta pelanggan memilih mesin
 * pengirim adalah pertanyaan yang tidak bisa mereka jawab dan tidak perlu
 * mereka pikirkan.
 */
class ProviderManager
{
    /** @var array<string, WhatsAppProvider> */
    private array $resolved = [];

    public function for(WaSession $session): WhatsAppProvider
    {
        return $this->driver($session->driver);
    }

    public function driver(string $name): WhatsAppProvider
    {
        return $this->resolved[$name] ??= match ($name) {
            'wwebjs' => new WwebjsProvider,
            default => throw new InvalidArgumentException("Driver WhatsApp tidak dikenal: {$name}"),
        };
    }
}
