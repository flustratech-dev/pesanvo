<?php

namespace App\Support;

use App\Models\AppSetting;

class PaymentGatewaySetting
{
    public static function mayar(): array
    {
        $raw = AppSetting::ambil('gateway_mayar');
        $default = [
            'is_active' => true,
            'api_key' => (string) config('services.mayar.api_key', ''),
            'api_url' => (string) config('services.mayar.api_url', 'https://api.mayar.id/hl/v2'),
            'webhook_token' => (string) config('services.mayar.webhook_token', ''),
        ];

        if (! $raw) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }

    public static function midtrans(): array
    {
        $raw = AppSetting::ambil('gateway_midtrans');
        $default = [
            'is_active' => false,
            'environment' => 'sandbox',
            'merchant_id' => '',
            'client_key' => '',
            'server_key' => '',
            'snap_url' => 'https://app.sandbox.midtrans.com/snap/v1',
        ];

        if (! $raw) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }

    public static function xendit(): array
    {
        $raw = AppSetting::ambil('gateway_xendit');
        $default = [
            'is_active' => false,
            'environment' => 'sandbox',
            'secret_key' => '',
            'public_key' => '',
            'webhook_token' => '',
            'base_url' => 'https://api.xendit.co',
        ];

        if (! $raw) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }

    public static function ipaymu(): array
    {
        $raw = AppSetting::ambil('gateway_ipaymu');
        $default = [
            'is_active' => false,
            'environment' => 'sandbox',
            'va_number' => '',
            'api_key' => '',
            'base_url' => 'https://sandbox.ipaymu.com',
        ];

        if (! $raw) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }

    public static function doku(): array
    {
        $raw = AppSetting::ambil('gateway_doku');
        $default = [
            'is_active' => false,
            'environment' => 'sandbox',
            'client_id' => '',
            'secret_key' => '',
            'base_url' => 'https://api-sandbox.doku.com',
        ];

        if (! $raw) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }
}
