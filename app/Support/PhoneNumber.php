<?php

namespace App\Support;

/**
 * Normalisasi nomor telepon Indonesia ke format E.164 tanpa tanda plus
 * (contoh: 6281234567890), format yang dipakai WhatsApp sebagai chat id.
 *
 * Aturannya tidak boleh diperlonggar diam-diam: nomor yang sudah tersimpan di
 * aplikasi pelanggan dinormalisasi dengan aturan yang sama, jadi perubahan di
 * sini membuat nomor lama berubah arti dan pesan mendarat di orang lain.
 */
class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        // 62 + 9..13 digit. Lebih pendek dari ini pasti bukan nomor seluler,
        // dan mengirim ke nomor sampah memancing laporan spam.
        return preg_match('/^62[1-9][0-9]{7,12}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * Chat id perorangan di WhatsApp. Grup memakai akhiran @g.us dan tidak
     * dibentuk dari nomor, jadi id grup dilewatkan apa adanya.
     */
    public static function toChatId(string $phoneOrChatId): string
    {
        if (str_ends_with($phoneOrChatId, '@g.us') || str_ends_with($phoneOrChatId, '@c.us')) {
            return $phoneOrChatId;
        }

        $normalized = self::normalize($phoneOrChatId);

        if ($normalized === null) {
            throw new \InvalidArgumentException("Nomor tujuan tidak valid: {$phoneOrChatId}");
        }

        return $normalized.'@c.us';
    }

    /** Menyamarkan nomor untuk log & tampilan: 6281****7890 */
    public static function mask(?string $phone): ?string
    {
        $normalized = self::normalize($phone) ?? $phone;

        if ($normalized === null || strlen($normalized) < 8) {
            return $normalized;
        }

        return substr($normalized, 0, 4).str_repeat('*', 4).substr($normalized, -4);
    }
}
