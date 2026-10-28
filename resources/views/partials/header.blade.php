@php
    $beranda = request()->routeIs('welcome') ? '' : route('welcome');

    $menuHeader = [
        'produk' => [
            'label' => 'Produk',
            'grup' => [
                'Gateway ini' => [
                    ['Fitur', $beranda . '#fitur'],
                    ['Cara kerja', $beranda . '#cara-kerja'],
                    ['Harga', $beranda . '#harga'],
                    ['Mitra', route('mitra.landing')],
                    ['Dokumentasi', route('docs.index')],
                ],
                'Produk Pesanvo lain' => collect(config('pesanvo.produk'))
                    ->map(fn ($alamat, $nama) => [$nama, $alamat])
                    ->values()
                    ->all(),
            ],
        ],
        'developer' => [
            'label' => 'Developer',
            'grup' => [
                'Mulai' => [
                    ['Mulai cepat', route('docs.show', 'mulai-cepat')],
                    ['Integrasi AI Agent', route('ai.index')],
                    ['Menautkan nomor', route('docs.show', 'menautkan-nomor')],
                    ['Mengirim pesan', route('docs.show', 'mengirim-pesan')],
                    ['Contoh integrasi', route('docs.show', 'contoh-integrasi')],
                ],
                'Rujukan' => [
                    ['Referensi API', route('docs.show', 'referensi-api')],
                    ['Webhook', route('docs.show', 'webhook')],
                    ['API key', route('docs.show', 'api-key')],
                    ['Batas & kuota', route('docs.show', 'batas-dan-kuota')],
                ],
            ],
        ],
        'sumber' => [
            'label' => 'Sumber daya',
            'grup' => [
                'Panduan' => [
                    ['Praktik baik', route('docs.show', 'praktik-baik')],
                    ['Template pesan', route('docs.show', 'template-pesan')],
                    ['FAQ', route('docs.show', 'faq')],
                    ['Glosarium', route('docs.show', 'glosarium')],
                ],
            ],
        ],
    ];
@endphp

{{-- ===================== Header ===================== --}}
<header x-data="{ scrolled: false }"
        x-init="scrolled = (window.scrollY > 10)"
        @scroll.window.passive="scrolled = (window.scrollY > 10)"
        class="sticky top-0 z-50 w-full border-b transition-all duration-300"
        :class="scrolled ? 'border-border/70 bg-background/80 backdrop-blur-md shadow-2xs' : 'border-transparent bg-transparent'">
    <div class="mx-auto flex w-full max-w-[1720px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8 xl:px-10 py-3 sm:py-3.5">
        <a href="{{ route('welcome') }}" class="flex shrink-0 items-center gap-2.5 font-semibold text-foreground transition-opacity hover:opacity-90">
            <img src="{{ asset('images/pesanvo.png') }}" alt="Logo" class="h-7 w-auto object-contain">
            <span class="text-sm sm:text-base font-semibold tracking-tight whitespace-nowrap">Pesanvo Gateway</span>
        </a>

        <nav class="ml-auto flex shrink-0 items-center gap-1 text-sm">
            <div class="hidden items-center gap-1 lg:gap-1.5 xl:gap-2 lg:flex shrink-0"
                 x-data="{
                     menu: null,
                     jeda: null,
                     buka(nama) { clearTimeout(this.jeda); this.menu = nama },
                     tutup() { clearTimeout(this.jeda); this.menu = null },
                     tundaTutup() { clearTimeout(this.jeda); this.jeda = setTimeout(() => this.menu = null, 140) },
                 }"
                 @keydown.escape.window="tutup()">
                @foreach ($menuHeader as $kunci => $menu)
                    <div class="relative shrink-0" @mouseenter="buka('{{ $kunci }}')" @mouseleave="tundaTutup()">
                        <button type="button"
                                @click="menu === '{{ $kunci }}' ? tutup() : buka('{{ $kunci }}')"
                                :aria-expanded="menu === '{{ $kunci }}' ? 'true' : 'false'"
                                aria-haspopup="true"
                                class="group relative flex shrink-0 items-center gap-1.5 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap transition-colors hover:text-foreground"
                                :class="menu === '{{ $kunci }}' ? 'text-foreground' : 'text-muted-foreground'">
                            <span class="whitespace-nowrap">{{ $menu['label'] }}</span>
                            <svg class="h-3 w-3 shrink-0 transition-transform duration-200" :class="menu === '{{ $kunci }}' ? 'rotate-180 text-primary' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary transition-all duration-200"
                                  :class="menu === '{{ $kunci }}' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"></span>
                        </button>

                        <div x-show="menu === '{{ $kunci }}'" x-cloak
                             x-transition:enter="tirai-masuk"
                             x-transition:leave="tirai-keluar"
                             class="absolute left-0 top-full z-50 mt-1.5 flex gap-1 rounded-xl border border-border bg-popover/95 p-1.5 shadow-lg backdrop-blur-xl">
                            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                                <div class="min-w-52">
                                    <p class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $judulGrup }}</p>
                                    @foreach ($isiGrup as [$nama, $alamat])
                                        <a href="{{ $alamat }}" @click="tutup()"
                                           class="block whitespace-nowrap rounded-lg px-3 py-1.5 text-xs sm:text-sm text-foreground/80 transition-colors hover:bg-muted hover:text-foreground">
                                            {{ $nama }}
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <a href="{{ $beranda }}#cara-kerja" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">Cara Kerja</span>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="{{ $beranda }}#fitur" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">Fitur</span>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="{{ $beranda }}#harga" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">Harga</span>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="{{ route('mitra.landing') }}" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">Mitra</span>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="{{ route('ai.index') }}" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">AI Agent</span>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="https://pesanvo.id/" target="_blank" rel="noopener noreferrer" class="group relative flex shrink-0 items-center gap-1 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">VPS</span>
                    <svg class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/></svg>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="https://build.pesanvo.id" target="_blank" rel="noopener noreferrer" class="group relative flex shrink-0 items-center gap-1 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                    <span class="whitespace-nowrap">Jasa Web</span>
                    <svg class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/></svg>
                    <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
            </div>

            <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                    class="ml-1 shrink-0 rounded-lg p-2 text-muted-foreground hover:text-foreground transition-colors"
                    aria-label="Toggle Dark Mode">
                <svg class="hidden h-4 w-4 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <svg class="block h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>

            <a href="{{ route('docs.index') }}" class="group relative hidden shrink-0 lg:block px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-foreground transition-colors">
                <span class="whitespace-nowrap">Docs</span>
                <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
            </a>

            <div class="hidden shrink-0 items-center gap-2 xl:gap-3 pl-1.5 lg:flex">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary px-3.5 py-1.5 text-xs sm:text-sm font-medium whitespace-nowrap text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="group relative shrink-0 px-2 xl:px-2.5 py-2 text-xs sm:text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground">
                        <span class="whitespace-nowrap">Masuk</span>
                        <span class="absolute bottom-0 left-2 xl:left-2.5 right-2 xl:right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary px-3.5 py-1.5 text-xs sm:text-sm font-medium whitespace-nowrap text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">
                        Buat akun
                    </a>
                @endauth
            </div>

            <button @click="mobileMenu = !mobileMenu" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted lg:hidden shrink-0" aria-label="Menu Mobile">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </nav>
    </div>
</header>

{{-- ===================== Mobile Drawer ===================== --}}
<div x-show="mobileMenu" x-cloak
     x-transition:enter="transition-transform duration-300 ease-out"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition-transform duration-250 ease-in"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed inset-0 z-50 flex flex-col bg-background lg:hidden">

    <div class="flex items-center justify-between border-b border-border px-5 py-4">
        <a href="{{ route('welcome') }}" class="flex items-center gap-2 font-semibold text-foreground">
            <img src="{{ asset('images/pesanvo.png') }}" alt="Logo" class="h-6 w-auto object-contain">
            <span class="text-sm">Pesanvo Gateway</span>
        </a>
        <button @click="mobileMenu = false" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted" aria-label="Tutup Menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
        @foreach ($menuHeader as $menu)
            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                <div class="space-y-1.5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $judulGrup }}</p>
                    <div class="space-y-1 pl-1">
                        @foreach ($isiGrup as [$nama, $alamat])
                            <a href="{{ $alamat }}" @click="mobileMenu = false" class="block py-1 text-sm font-medium text-foreground hover:text-primary transition-colors">{{ $nama }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach

        <div class="border-t border-border pt-3 space-y-1">
            <a href="https://pesanvo.id/" target="_blank" rel="noopener noreferrer" class="flex w-full items-center justify-between py-1.5 text-sm font-medium text-foreground hover:text-primary transition-colors">
                <span>Layanan Cloud VPS</span>
                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
            </a>
            <a href="https://build.pesanvo.id" target="_blank" rel="noopener noreferrer" class="flex w-full items-center justify-between py-1.5 text-sm font-medium text-foreground hover:text-primary transition-colors">
                <span>Jasa Web</span>
                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
            </a>
            <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" class="flex w-full items-center justify-between py-1.5 text-sm font-medium text-foreground">
                <span>Tema Tampilan</span>
                <svg class="hidden h-4 w-4 dark:block text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <svg class="block h-4 w-4 dark:hidden text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
        </div>
    </div>

    {{-- Bottom Bar: Fixed / Pinned Auth Actions --}}
    <div class="shrink-0 border-t border-border bg-card/95 backdrop-blur-md p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
        @auth
            <a href="{{ route('dashboard') }}" class="block w-full text-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">Dashboard</a>
        @else
            <div class="grid grid-cols-2 gap-2.5">
                <a href="{{ route('login') }}" class="flex items-center justify-center rounded-xl border border-input bg-background px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-muted transition-all text-center">Masuk</a>
                <a href="{{ route('register') }}" class="flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all text-center">Buat akun</a>
            </div>
        @endauth
    </div>
</div>
