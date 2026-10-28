@props(['label', 'nilai', 'sub' => null, 'ikon' => null, 'nada' => 'netral', 'tautan' => null, 'tautanLabel' => 'Lihat'])

{{-- Ubin angka. Nadanya hanya diberi warna saat memang butuh tindakan; kalau
     semua ubin berwarna, tidak ada satu pun yang menonjol. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border bg-card p-4 shadow-xs transition '.($nada === 'perhatian' ? 'border-primary/40' : ($nada === 'bahaya' ? 'border-destructive/40' : 'border-border'))]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</p>
        @if ($ikon)
            <span @class([
                'grid h-7 w-7 shrink-0 place-items-center rounded-lg',
                'bg-muted text-muted-foreground' => $nada === 'netral',
                'bg-primary/10 text-primary' => $nada === 'perhatian',
                'bg-destructive/10 text-destructive' => $nada === 'bahaya',
            ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $ikon }}"/></svg>
            </span>
        @endif
    </div>

    <p @class([
        'mt-2 text-2xl font-semibold tracking-tight tabular-nums',
        'text-destructive' => $nada === 'bahaya',
        'text-primary' => $nada === 'perhatian',
    ])>{{ $nilai }}</p>

    @if ($sub)
        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{{ $sub }}</p>
    @endif

    @if ($tautan)
        <a href="{{ $tautan }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
            {{ $tautanLabel }}
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
    @endif
</div>
