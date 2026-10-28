<x-mail::message>
# Masa berlaku langganan

Halo{{ $workspace?->billing_name ? ' '.$workspace->billing_name : '' }},

@if ($sisaHari <= 0)
Langganan {{ $subscription->plan()->name() }} untuk workspace **{{ $workspace?->name }}**
berakhir **hari ini** ({{ $subscription->current_period_end?->translatedFormat('j F Y') }}).
@elseif ($sisaHari === 1)
Langganan {{ $subscription->plan()->name() }} untuk workspace **{{ $workspace?->name }}**
berakhir **besok** ({{ $subscription->current_period_end?->translatedFormat('j F Y') }}).
@else
Langganan {{ $subscription->plan()->name() }} untuk workspace **{{ $workspace?->name }}**
berakhir **{{ $sisaHari }} hari lagi**, pada
{{ $subscription->current_period_end?->translatedFormat('j F Y') }}.
@endif

{{--
    Tanpa kalimat kedua ini, "pengiriman berhenti" terbaca seperti nomornya
    diblokir atau tautannya diputus — ketakutan yang keliru dan yang paling
    sering berujung pelanggan menekan Hubungkan berulang kali untuk masalah
    yang sebenarnya cuma tagihan.
--}}
Setelah tanggal itu pengiriman pesan berhenti, tapi **nomor WhatsApp Anda tetap tertaut** dan
tidak perlu discan ulang selama {{ config('billing.grace_days') }} hari.

<x-mail::button :url="route('billing.plans')">
Perpanjang sekarang
</x-mail::button>

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
