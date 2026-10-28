<?php

namespace App\Services\Billing;

use App\Models\PayoutRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    /**
     * Menerbitkan berkas dokumen invoice PDF resmi untuk pencairan dana komisi.
     *
     * @return string Path relatif berkas di dalam disk 'media'
     */
    public function generatePayoutInvoice(PayoutRequest $payout): string
    {
        $payout->loadMissing(['user', 'referralCode', 'payer']);

        $pdf = Pdf::loadView('pdf.payout-invoice', [
            'payout' => $payout,
            'shaHash' => substr(hash('sha256', "pesanvo-payout-{$payout->id}-{$payout->payout_number}-{$payout->amount}"), 0, 16),
        ])->setPaper('a4', 'portrait');

        $filename = "invoices/payout-{$payout->payout_number}.pdf";
        Storage::disk('media')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Mengambil instance PDF untuk download langsung di peramban.
     */
    public function streamPayoutInvoice(PayoutRequest $payout)
    {
        $payout->loadMissing(['user', 'referralCode', 'payer']);

        return Pdf::loadView('pdf.payout-invoice', [
            'payout' => $payout,
            'shaHash' => substr(hash('sha256', "pesanvo-payout-{$payout->id}-{$payout->payout_number}-{$payout->amount}"), 0, 16),
        ])->setPaper('a4', 'portrait');
    }
}
