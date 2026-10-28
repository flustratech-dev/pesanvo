<?php

namespace App\Services\Providers;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Kegagalan yang tidak akan membaik kalau diulang — nomor tidak terdaftar
     * di WhatsApp, sesi sudah logout, format salah. Job pengirim memakai ini
     * untuk langsung menandai pesan gagal alih-alih memakai jatah retry.
     */
    public static function permanent(string $message, array $context = []): self
    {
        return new self($message, retryable: false, context: $context);
    }
}
