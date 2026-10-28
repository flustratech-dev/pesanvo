<x-mail::message>
# Tagihan {{ $invoice->number }}

Halo{{ $workspace?->billing_name ? ' '.$workspace->billing_name : '' }},

Tagihan untuk workspace **{{ $workspace?->name }}** sudah terbit.

<x-mail::table>
| | |
|:---|---:|
| Paket | {{ $invoice->plan()->name() }} · {{ $invoice->periodLabel() }} |
| Jatuh tempo | {{ $invoice->due_at?->translatedFormat('j F Y') }} |
| **Total** | **Rp {{ number_format($invoice->total, 0, ',', '.') }}** |
</x-mail::table>

{{--
    Tiga digit terakhir adalah kode unik, dan itu satu-satunya cara pembayaran
    Anda bisa dicocokkan dengan tagihan ini selama pencocokannya masih
    dikerjakan manusia. Kalimatnya wajib ada: nominal yang dibulatkan pelanggan
    berakhir sebagai uang masuk yang tidak bisa dikenali milik siapa.
--}}
@if ($invoice->unique_code > 0)
Mohon transfer **sampai digit terakhir**, termasuk angka
{{ str_pad((string) $invoice->unique_code, 3, '0', STR_PAD_LEFT) }} di ujungnya. Angka itu yang
membuat pembayaran Anda bisa kami kenali; nominal yang dibulatkan akan tertahan tanpa bisa
dicocokkan dengan tagihan mana pun.
@endif

<x-mail::button :url="route('billing.invoice', $invoice->id)">
Bayar sekarang
</x-mail::button>

Setelah membayar, unggah bukti transfernya di halaman yang sama. Kami periksa manual dan
mengabari Anda begitu langganannya aktif.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
