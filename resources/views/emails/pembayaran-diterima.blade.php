<x-mail::message>
# Pembayaran diterima

Halo{{ $workspace?->billing_name ? ' '.$workspace->billing_name : '' }},

Tagihan **{{ $invoice->number }}** sebesar
**Rp {{ number_format($invoice->total, 0, ',', '.') }}** sudah lunas. Layanan Anda aktif sekarang.

<x-mail::table>
| | |
|:---|---:|
| Workspace | {{ $workspace?->name }} |
| Paket | {{ $subscription->plan()->name() }} |
| Berlaku sampai | {{ $subscription->current_period_end?->translatedFormat('j F Y') ?? '-' }} |
</x-mail::table>

{{--
    Kalimat tentang scan QR wajib ikut. Pelanggan yang nomornya sempat dilepas
    saat menunggak berasumsi ia harus mengulang seluruh pemasangan — padahal
    kredensialnya tidak pernah dibuang dan nomor tersambung sendiri.
--}}
Kalau nomor WhatsApp Anda sempat terlepas saat langganan berhenti, ia akan tersambung sendiri
dalam beberapa menit. Kredensialnya masih tersimpan — **tidak perlu scan QR ulang**.

<x-mail::button :url="route('billing.index')">
Buka dashboard
</x-mail::button>

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
