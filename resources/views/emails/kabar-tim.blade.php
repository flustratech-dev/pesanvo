<x-mail::message>
# {{ $judul }}

{{-- Isi ditampilkan apa adanya, dengan baris tetap terjaga. Teks ini disusun
     `BillingMessages`/`HelpdeskMessages` sebagai sumber tunggal nada seluruh
     produk; menyusunnya ulang di sini berarti dua versi yang akan menyimpang. --}}
<div style="white-space: pre-line;">{{ $isi }}</div>

@if ($tautan)
<x-mail::button :url="$tautan">
{{ $labelTautan }}
</x-mail::button>
@endif

{{ config('app.name') }}
</x-mail::message>
