@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card text-card-foreground p-5 shadow-sm']) }}>
    @if ($title)
        <header class="mb-4">
            <h2 class="font-semibold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-muted-foreground">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
