@extends('layouts.docs')

@section('title', $page['title'])
@section('description', $page['summary'])

@section('content')
<div class="flex flex-col xl:flex-row gap-10">
    <article class="min-w-0 flex-1 max-w-4xl">
        {{-- Breadcrumb Navigation --}}
        <nav class="mb-4 flex items-center gap-2 text-xs font-medium text-muted-foreground flex-wrap">
            <a href="{{ route('docs.index') }}" class="inline-flex items-center gap-1 hover:text-primary transition">
                <i class="bi bi-book text-xs"></i>
                <span>Dokumentasi</span>
            </a>
            <i class="bi bi-chevron-right text-[10px] text-muted-foreground/60"></i>
            <span class="rounded-md bg-muted px-2 py-0.5 text-muted-foreground font-semibold">{{ $page['group'] }}</span>
            <i class="bi bi-chevron-right text-[10px] text-muted-foreground/60"></i>
            <span class="text-foreground font-semibold truncate">{{ $page['title'] }}</span>
        </nav>

        {{-- Article Header --}}
        <div class="pb-6 border-b border-border/80">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-primary">
                        <i class="bi bi-check2-circle"></i>
                        <span>{{ $page['group'] }}</span>
                    </span>
                    <span class="text-xs text-muted-foreground">&bull;</span>
                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                        <i class="bi bi-clock"></i>
                        <span>~3 menit baca</span>
                    </span>
                </div>

                {{-- Tombol Unduh / Akses .md --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('docs.raw', $page['slug']) }}"
                       download
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition shadow-2xs"
                       title="Unduh berkas dalam format Markdown (.md)">
                        <i class="bi bi-filetype-md text-primary"></i>
                        <span>Unduh .md</span>
                    </a>
                </div>
            </div>

            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
                {{ $page['title'] }}
            </h1>
            <p class="mt-3 text-base sm:text-lg text-muted-foreground leading-relaxed">
                {{ $page['summary'] }}
            </p>
        </div>

        {{-- Isinya berasal dari berkas markdown di repo, bukan masukan pengguna --}}
        <div class="doc-body mt-8">
            {!! $page['html'] !!}
        </div>

        {{-- Navigasi Sebelumnya / Berikutnya --}}
        <nav class="mt-14 grid gap-4 sm:grid-cols-2 border-t border-border pt-8">
            @if ($page['prev'])
                <a href="{{ route('docs.show', $page['prev']['slug']) }}"
                   class="group flex flex-col justify-between rounded-2xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:bg-card/90 shadow-2xs">
                    <div class="flex items-center gap-1 text-xs font-semibold text-muted-foreground group-hover:text-primary transition">
                        <i class="bi bi-arrow-left"></i>
                        <span>Sebelumnya</span>
                    </div>
                    <p class="mt-2 text-sm font-bold text-foreground group-hover:text-primary transition">
                        {{ $page['prev']['title'] }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground line-clamp-1">
                        {{ $page['prev']['summary'] }}
                    </p>
                </a>
            @else
                <div></div>
            @endif

            @if ($page['next'])
                <a href="{{ route('docs.show', $page['next']['slug']) }}"
                   class="group flex flex-col justify-between rounded-2xl border border-border bg-card p-4 text-right transition-all hover:border-primary/50 hover:bg-card/90 shadow-2xs">
                    <div class="flex items-center justify-end gap-1 text-xs font-semibold text-muted-foreground group-hover:text-primary transition">
                        <span>Berikutnya</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                    <p class="mt-2 text-sm font-bold text-foreground group-hover:text-primary transition">
                        {{ $page['next']['title'] }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground line-clamp-1">
                        {{ $page['next']['summary'] }}
                    </p>
                </a>
            @endif
        </nav>

        {{-- Bantuan / Support Bar --}}
        <div class="mt-10 rounded-2xl border border-border/80 bg-muted/40 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary">
                    <i class="bi bi-question-circle-fill text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-foreground">Pertanyaan seputar artikel ini?</h4>
                    <p class="text-xs text-muted-foreground">Tim dukungan teknis kami siap menjawab pertanyaan implementasi Anda.</p>
                </div>
            </div>
            <a href="{{ route('docs.show', 'bantuan') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground hover:bg-muted transition shrink-0">
                <i class="bi bi-headset"></i>
                <span>Hubungi Kami</span>
            </a>
        </div>
    </article>

    {{-- Daftar isi halaman ini (Table of Contents) --}}
    @if (count($page['toc']) > 1)
        <aside class="hidden w-64 shrink-0 xl:block">
            <div class="sticky top-20 space-y-4 max-h-[calc(100vh-6rem)] overflow-y-auto pr-2 scrollbar-thin">
                <div class="flex items-center gap-2 pb-2 border-b border-border/60">
                    <i class="bi bi-list-nested text-primary text-sm"></i>
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Di Halaman Ini</p>
                </div>

                <ul class="space-y-1 border-l border-border/80 text-xs">
                    @foreach ($page['toc'] as $item)
                        <li>
                            <a href="#{{ $item['id'] }}"
                               class="-ml-px block border-l-2 border-transparent py-1 pl-3 text-muted-foreground transition hover:border-primary hover:text-foreground">
                                {{ $item['text'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="pt-3 border-t border-border/60 flex flex-col gap-2">
                    <button type="button" 
                            onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
                            class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary transition cursor-pointer">
                        <i class="bi bi-arrow-up"></i>
                        <span>Kembali ke atas</span>
                    </button>
                    <a href="{{ route('docs.index') }}" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary transition">
                        <i class="bi bi-grid"></i>
                        <span>Katalog Dokumentasi</span>
                    </a>
                </div>
            </div>
        </aside>
    @endif
</div>
@endsection
