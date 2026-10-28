@props(['audience' => 'workspace'])

{{--
    Lonceng notifikasi di bilah atas.

    Datanya diambil di sini, bukan dititipkan controller: lonceng ini muncul di
    SETIAP halaman, dan menuntut tiap controller mengirim datanya sendiri
    berarti satu controller yang lupa menghasilkan lonceng yang diam-diam
    kosong — persis kegagalan tanpa gejala yang seluruh fitur ini dibuat untuk
    menghilangkan.

    Dua kueri ringan dan hanya untuk pengguna yang sudah masuk; keduanya
    ditopang indeks `(user_id, read_at)`.
--}}
@php
    $penerima = auth()->user();

    $belumDibaca = $penerima
        ? \App\Models\Notification::untuk($penerima, $audience)->belumDibaca()->count()
        : 0;

    $terbaru = $penerima
        ? \App\Models\Notification::untuk($penerima, $audience)->latest('id')->limit(8)->get()
        : collect();
@endphp

@if ($penerima)
    <div class="relative" x-data="{ buka: false }" @keydown.escape.window="buka = false">
        <button type="button" @click="buka = ! buka"
                class="relative grid h-9 w-9 place-items-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground"
                :aria-expanded="buka"
                aria-label="Notifikasi">
            <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>
            </svg>

            @if ($belumDibaca > 0)
                {{-- Angkanya, bukan cuma titik. "Ada sesuatu" tidak cukup untuk
                     memutuskan apakah perlu dibuka sekarang. --}}
                <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-destructive-foreground">
                    {{ $belumDibaca > 99 ? '99+' : $belumDibaca }}
                </span>
            @endif
        </button>

        <div x-show="buka" x-cloak @click.outside="buka = false"
             x-transition.opacity.duration.150ms
             class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-border bg-card shadow-lg sm:w-96">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
                <p class="text-sm font-semibold">Notifikasi</p>

                @if ($belumDibaca > 0)
                    <form method="POST" action="{{ route('notifications.read-all', ['audience' => $audience]) }}">
                        @csrf
                        <button class="text-xs text-muted-foreground hover:text-foreground hover:underline">
                            Tandai semua dibaca
                        </button>
                    </form>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($terbaru as $n)
                    {{-- Seluruh barisnya tautan, bukan cuma judulnya: yang
                         menekan notifikasi menekan di mana saja di barisnya. --}}
                    <a href="{{ route('notifications.open', $n->id) }}"
                       class="flex gap-3 border-b border-border px-4 py-3 transition last:border-0 hover:bg-muted/50 {{ $n->sudahDibaca() ? '' : 'bg-primary/5' }}">
                        <span @class([
                            'mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full',
                            'bg-primary/10 text-primary' => $n->level === 'success',
                            'bg-amber-500/10 text-amber-600 dark:text-amber-400' => $n->level === 'warning',
                            'bg-destructive/10 text-destructive' => $n->level === 'danger',
                            'bg-muted text-muted-foreground' => $n->level === 'info',
                        ])>
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $n->ikon() }}"/></svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium leading-snug {{ $n->sudahDibaca() ? 'text-muted-foreground' : '' }}">
                                {{ $n->title }}
                            </span>

                            @if ($n->body)
                                <span class="mt-0.5 line-clamp-2 block text-xs leading-relaxed text-muted-foreground">{{ $n->body }}</span>
                            @endif

                            <span class="mt-1 block text-[11px] text-muted-foreground">
                                {{ $n->created_at->diffForHumans() }}
                                @if ($audience === 'admin' && $n->workspace)
                                    · {{ $n->workspace->name }}
                                @endif
                            </span>
                        </span>

                        @unless ($n->sudahDibaca())
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                        @endunless
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada notifikasi.
                    </p>
                @endforelse
            </div>

            <a href="{{ route('notifications.index', ['audience' => $audience]) }}"
               class="block border-t border-border px-4 py-2.5 text-center text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground">
                Lihat semua notifikasi
            </a>
        </div>
    </div>
@endif
