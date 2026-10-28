@props(['judul' => null, 'sub' => null, 'rapat' => false])

{{-- Wadah untuk tabel dan daftar.

     Beda dari <x-card>: panel ini TIDAK memberi padding pada isinya, karena
     tabel yang menempel ke tepi panel terbaca jauh lebih rapi daripada tabel
     yang mengambang di tengah bantalan. Judul dan tombol aksinya tetap
     berbantalan lewat header sendiri. --}}
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xs']) }}>
    @if ($judul || isset($aksi))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3.5">
            <div class="min-w-0">
                @if ($judul)
                    <h2 class="font-semibold">{{ $judul }}</h2>
                @endif
                @if ($sub)
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $sub }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $aksi }}</div>
            @endisset
        </header>
    @endif

    <div @class(['overflow-x-auto' => ! $rapat, 'p-5' => $rapat])>
        {{ $slot }}
    </div>
</section>
