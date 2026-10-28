<?php

namespace App\Support;

/**
 * Nomor WhatsApp publik tim, untuk setiap tautan "Hubungi admin" yang dilihat
 * pelanggan — tagihan menunggu, halaman verifikasi, footer, halaman Enterprise.
 *
 * Satu sumber supaya nomor bisnis yang berganti cukup diubah di env, bukan
 * dicari satu per satu di Blade. Sebelum ada ini nomornya tertulis mati di
 * lima tempat, dan pergantian nomor pertama kali meninggalkan separuhnya
 * menunjuk nomor lama — pelanggan yang menunggu verifikasi pembayaran
 * mengirim bukti ke nomor yang tidak lagi dibaca siapa pun.
 */
class KontakWhatsApp
{
    /**
     * Dalam format 62xxx, siap dipakai di wa.me. Nomor publik diutamakan;
     * nomor kotak masuk tim hanya dipakai kalau yang publik kosong, supaya
     * tautan yang selalu dirender tidak pernah menuju wa.me tanpa nomor.
     */
    public static function nomor(): ?string
    {
        return PhoneNumber::normalize((string) config('billing.enterprise.whatsapp'))
            ?? PhoneNumber::normalize((string) config('billing.admin_phone'));
    }

    public static function tautan(string $pesan = ''): string
    {
        $url = 'https://wa.me/'.self::nomor();

        return $pesan === '' ? $url : $url.'?text='.rawurlencode($pesan);
    }
}
