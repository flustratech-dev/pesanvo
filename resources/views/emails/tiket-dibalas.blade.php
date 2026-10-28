<x-mail::message>
# Tiket Anda sudah dibalas

Halo{{ $workspace?->billing_name ? ' '.$workspace->billing_name : '' }},

Pertanyaan Anda sudah kami jawab.

<x-mail::table>
| | |
|:---|---:|
| Tiket | #{{ $ticket->id }} |
| Judul | {{ $ticket->subject }} |
| Workspace | {{ $workspace?->name }} |
| Kategori | {{ $ticket->labelKategori() }} |
</x-mail::table>

{{--
    Isi balasannya sengaja tidak ikut. Tiket bisa memuat apa saja yang
    ditempelkan pelanggan saat melaporkan masalah, termasuk kunci API mereka
    sendiri — dan email diarsipkan di tempat yang tidak kami kendalikan.
--}}
Buka dashboard untuk membaca jawabannya dan membalas kalau masih ada yang kurang.

<x-mail::button :url="route('tickets.show', $ticket->id)">
Baca jawabannya
</x-mail::button>

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
