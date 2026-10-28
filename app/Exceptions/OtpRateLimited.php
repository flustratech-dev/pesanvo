<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Permintaan kode ditolak karena batas laju, bukan karena permintaannya salah.
 *
 * Dibedakan dari RuntimeException biasa supaya jawabannya bisa 429 ("coba lagi
 * nanti") alih-alih 422 ("permintaan Anda keliru"). Dulu keduanya sama-sama
 * dijawab 429, sehingga workspace yang belum menghubungkan nomor sama sekali
 * diberi tahu bahwa ia terlalu sering meminta — lalu menunggu, mencoba lagi,
 * dan menemui pesan yang sama tanpa pernah tahu apa yang sebenarnya kurang.
 */
class OtpRateLimited extends RuntimeException {}
