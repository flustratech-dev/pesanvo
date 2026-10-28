@props(['warna' => 'netral', 'titik' => false])

{{-- Lencana status. Satu tempat untuk seluruh panel supaya "lunas" di halaman
     tagihan dan "aktif" di halaman workspace memakai bahasa warna yang sama —
     dua rona hijau berbeda untuk dua hal yang sama-sama baik membuat orang
     mengira keduanya berbeda arti. --}}
@php
    $peta = [
        'netral' => 'bg-muted text-muted-foreground ring-border',
        'hijau' => 'bg-primary/10 text-primary ring-primary/20',
        'kuning' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-amber-500/25',
        'merah' => 'bg-destructive/10 text-destructive ring-destructive/25',
        'biru' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400 ring-sky-500/25',
        'solid' => 'bg-primary text-primary-foreground ring-primary',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($peta[$warna] ?? $peta['netral'])]) }}>
    @if ($titik)
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    @endif
    {{ $slot }}
</span>
