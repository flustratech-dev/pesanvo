<?php

namespace App\Support;

class DaftarBank
{
    /**
     * Daftar seluruh bank di Indonesia untuk dropdown data penagihan.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'Bank Central Asia (BCA)',
            'Bank Mandiri',
            'Bank Rakyat Indonesia (BRI)',
            'Bank Negara Indonesia (BNI)',
            'Bank Syariah Indonesia (BSI)',
            'Bank Permata',
            'Bank CIMB Niaga',
            'Bank Danamon',
            'Bank Tabungan Negara (BTN)',
            'Bank Central Asia Syariah (BCA Syariah)',
            'Bank Jago',
            'SeaBank Indonesia',
            'BCA Digital (Blu)',
            'Bank BTPN / Jenius',
            'Bank Neo Commerce (BNC)',
            'Allo Bank Indonesia',
            'Bank Mega',
            'Bank Sinarmas',
            'Bank OCBC NISP',
            'Bank Panin',
            'Bank Maybank Indonesia',
            'Bank Muamalat Indonesia',
            'Bank Bukopin / KB Bank',
            'Bank UOB Indonesia',
            'Bank Commonwealth',
            'Bank HSBC Indonesia',
            'Standard Chartered Bank',
            'Bank DBS Indonesia',
            'Bank Sahabat Sampoerna',
            'Bank Maspion',
            'Bank Ganesha',
            'Bank Ina Perdana',
            'Bank Victoria International',
            'Bank MNC Internasional',
            'Bank Aladin Syariah',
            'Bank Raya Indonesia',
            'Bank Jago Syariah',
            'Bank DKI',
            'Bank BJB',
            'Bank BJB Syariah',
            'Bank Jateng',
            'Bank Jateng Syariah',
            'Bank Jatim',
            'Bank Jatim Syariah',
            'Bank DIY (BPD DIY)',
            'Bank Bali (BPD Bali)',
            'Bank NTB Syariah',
            'Bank NTT',
            'Bank Sumut',
            'Bank Sumut Syariah',
            'Bank Nagari (BPD Sumbar)',
            'Bank Riau Kepri Syariah',
            'Bank Jambi',
            'Bank Sumsel Babel',
            'Bank Lampung',
            'Bank Bengkulu',
            'Bank Kalbar',
            'Bank Kalsel',
            'Bank Kalteng',
            'Bank Kaltimtara',
            'Bank Sulselbar',
            'Bank SulutGo',
            'Bank Sulteng',
            'Bank Sultra',
            'Bank Maluku Malut',
            'Bank Papua',
            'Lainnya',
        ];
    }
}
