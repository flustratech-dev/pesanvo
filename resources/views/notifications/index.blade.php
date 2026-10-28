@extends($audience === 'admin' ? 'layouts.admin' : 'layouts.app')
@section('title', 'Notifikasi')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">
                {{ $audience === 'admin' ? 'Notifikasi tim' : 'Notifikasi' }}
            </h2>
            <p class="mt-0.5 text-sm text-muted-foreground">
                @if ($belumDibaca > 0)
                    {{ $belumDibaca }} belum dibaca.
                @else
                    Semuanya sudah dibaca.
                @endif
                {{-- Disebutkan karena orang akan mencarinya: kabar yang hilang
                     sebelum sempat dilihat adalah persis kegagalan yang lonceng
                     ini dibuat untuk mencegahnya, jadi yang belum dibaca tidak
                     pernah dipangkas berapa pun umurnya. --}}
                Yang sudah dibaca disimpan 90 hari; yang belum dibaca tidak pernah dihapus.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->is_super_admin)
                {{-- Dua aliran yang isinya tidak pernah bercampur. Pindahnya
                     lewat tautan, bukan sakelar: keduanya alamat sendiri, jadi
                     bisa ditautkan langsung dari mana pun. --}}
                @foreach (['workspace' => 'Workspace saya', 'admin' => 'Tim'] as $nilai => $label)
                    <a href="{{ route('notifications.index', ['audience' => $nilai]) }}"
                       class="rounded-lg border px-3.5 py-1.5 text-sm transition {{ $audience === $nilai ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border hover:bg-muted' }}">
                        {{ $label }}
                    </a>
                @endforeach
            @endif

            @if ($belumDibaca > 0)
                <form method="POST" action="{{ route('notifications.read-all', ['audience' => $audience]) }}">
                    @csrf
                    <button class="rounded-lg border border-border px-3.5 py-1.5 text-sm transition hover:bg-muted">
                        Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>
    </div>

    <x-section judul="Riwayat">
        @if ($notifikasi->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-muted-foreground">
                Belum ada notifikasi.
            </p>
        @else
            <div class="divide-y divide-border">
                @foreach ($notifikasi as $n)
                    <a href="{{ route('notifications.open', $n->id) }}"
                       class="flex gap-3.5 px-5 py-4 transition hover:bg-muted/40 {{ $n->sudahDibaca() ? '' : 'bg-primary/5' }}">
                        <span @class([
                            'mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full',
                            'bg-primary/10 text-primary' => $n->level === 'success',
                            'bg-amber-500/10 text-amber-600 dark:text-amber-400' => $n->level === 'warning',
                            'bg-destructive/10 text-destructive' => $n->level === 'danger',
                            'bg-muted text-muted-foreground' => $n->level === 'info',
                        ])>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $n->ikon() }}"/></svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium {{ $n->sudahDibaca() ? 'text-muted-foreground' : '' }}">
                                    {{ $n->title }}
                                </span>
                                @unless ($n->sudahDibaca())
                                    <x-badge warna="hijau">baru</x-badge>
                                @endunless
                            </span>

                            @if ($n->body)
                                <span class="mt-1 block text-sm leading-relaxed text-muted-foreground">{{ $n->body }}</span>
                            @endif

                            <span class="mt-1.5 block text-xs text-muted-foreground">
                                {{ $n->created_at->translatedFormat('j M Y, H:i') }}
                                @if ($n->workspace)
                                    · {{ $n->workspace->name }}
                                @endif
                                · <code class="text-[11px]">{{ $n->type }}</code>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-section>

    @if ($notifikasi->hasPages())
        <div class="mt-5">{{ $notifikasi->appends(['audience' => $audience])->links() }}</div>
    @endif
@endsection
