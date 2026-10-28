@props(['judul' => null, 'sub' => null, 'rapat' => false])

{{-- Bagian halaman yang mengalir langsung di atas latar, tanpa kotak.

     Menggantikan <x-card> di hampir seluruh halaman. Alasannya bukan selera:
     halaman yang seluruh isinya dibungkus kotak membuat setiap bagian tampak
     sama penting, dan mata kehilangan tempat berpijak — persis keluhan yang
     muncul setelah panel admin selesai. Sekarang kotak hanya dipakai untuk hal
     yang memang berdiri sendiri: ubin angka di baris atas, dan formulir yang
     menunggu keputusan.

     Pemisahnya garis tipis dan jarak, bukan bingkai. --}}
<section {{ $attributes->merge(['class' => 'mt-8 first:mt-0']) }}>
    @if ($judul || isset($aksi))
        <header class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-border pb-2.5">
            <div class="min-w-0">
                @if ($judul)
                    <h2 class="text-base font-semibold tracking-tight">{{ $judul }}</h2>
                @endif
                @if ($sub)
                    <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">{{ $sub }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $aksi }}</div>
            @endisset
        </header>
    @endif

    <div @class(['-mx-4 overflow-x-auto sm:mx-0' => ! $rapat])>
        {{ $slot }}
    </div>
</section>
