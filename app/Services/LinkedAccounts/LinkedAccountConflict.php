<?php

namespace App\Services\LinkedAccounts;

use RuntimeException;

/**
 * Perubahan dari seberang tidak bisa diterapkan tanpa menimpa akun lain —
 * email barunya sudah dipakai orang yang berbeda di aplikasi ini.
 */
class LinkedAccountConflict extends RuntimeException {}
