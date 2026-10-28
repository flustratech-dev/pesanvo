<!DOCTYPE html>
<html lang="id" class="scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-head', [
        'title' => 'Program Kemitraan & Reseller — ' . config('app.name'),
        'description' => 'Bergabunglah dengan Program Mitra Pesanvo Gateway. Dapatkan komisi 20% untuk setiap klien baru yang Anda rekomendasikan, dan klien Anda mendapatkan diskon 10%.',
    ])

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/android-chrome-192x192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('animasi-muncul');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased selection:bg-primary selection:text-primary-foreground overflow-x-clip w-full relative" x-data="{ mobileMenu: false }">

    {{-- ===================== Header & Navigation ===================== --}}
    <header class="sticky top-0 z-40 w-full border-b border-border/80 bg-background/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 font-bold tracking-tight text-foreground transition hover:opacity-90">
                <img src="{{ asset('images/pesanvo.png') }}" alt="" class="h-8 w-auto object-contain">
                <span class="text-base sm:text-lg">Pesanvo</span>
            </a>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-muted-foreground">
                <a href="{{ route('welcome') }}" class="transition-colors hover:text-foreground">Beranda</a>
                <a href="{{ route('welcome') }}#fitur" class="transition-colors hover:text-foreground">Fitur</a>
                <a href="{{ route('welcome') }}#harga" class="transition-colors hover:text-foreground">Harga</a>
                <a href="{{ route('mitra.landing') }}" class="font-semibold text-primary transition-colors">Program Mitra</a>
                <a href="{{ route('docs.index') }}" class="transition-colors hover:text-foreground">Dokumentasi</a>
            </nav>

            <div class="flex items-center gap-3">
                <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                        class="rounded-lg p-2 text-muted-foreground hover:text-foreground transition-colors"
                        aria-label="Toggle Dark Mode">
                    <svg class="hidden h-4 w-4 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="block h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                @auth
                    <a href="{{ route('mitra.index') }}" class="rounded-lg bg-primary px-4 py-2 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90">
                        Dashboard Mitra
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs sm:text-sm font-medium text-muted-foreground transition hover:text-foreground">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-primary px-4 py-2 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90">
                        Daftar Jadi Mitra
                    </a>
                @endauth

                <button @click="mobileMenu = !mobileMenu" class="rounded-lg p-2 text-muted-foreground hover:text-foreground md:hidden" aria-label="Buka menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        {{-- Mobile Drawer --}}
        <div x-show="mobileMenu" x-cloak class="border-b border-border bg-background px-4 py-4 md:hidden space-y-3">
            <a href="{{ route('welcome') }}" class="block text-sm font-medium text-muted-foreground hover:text-foreground">Beranda</a>
            <a href="{{ route('welcome') }}#fitur" class="block text-sm font-medium text-muted-foreground hover:text-foreground">Fitur</a>
            <a href="{{ route('welcome') }}#harga" class="block text-sm font-medium text-muted-foreground hover:text-foreground">Harga</a>
            <a href="{{ route('mitra.landing') }}" class="block text-sm font-semibold text-primary">Program Mitra</a>
            <a href="{{ route('docs.index') }}" class="block text-sm font-medium text-muted-foreground hover:text-foreground">Dokumentasi</a>
        </div>
    </header>

    {{-- ===================== Hero Section ===================== --}}
    <section class="relative overflow-hidden py-20 lg:py-28">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/10 via-background to-background"></div>
        <div class="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
            <div class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3.5 py-1 text-xs font-semibold text-primary">
                <span class="inline-block h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                PROGRAM KEMITRAAN &amp; AFILIASI RESELLER
            </div>

            <h1 class="mt-6 text-3xl font-extrabold tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                Rekomendasikan Pesanvo, <br class="hidden sm:inline">
                <span class="text-primary">Dapatkan Komisi 20%</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base sm:text-lg leading-relaxed text-muted-foreground">
                Ajak rekan developer, klien software house, agensi digital, atau pebisnis menggunakan Pesanvo Gateway. 
                Klien Anda mendapatkan <strong>diskon 10%</strong> untuk tagihan pertama, dan Anda menerima <strong>komisi 20%</strong> langsung ke kantong Anda.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                @auth
                    <a href="{{ route('mitra.index') }}" class="rounded-xl bg-primary px-6 py-3.5 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]">
                        Buka Dashboard Mitra Saya
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-primary px-6 py-3.5 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]">
                        Daftar Jadi Mitra Sekarang — Gratis
                    </a>
                @endauth
                <a href="#cara-kerja" class="rounded-xl border border-border bg-card/60 px-6 py-3.5 text-sm font-semibold text-foreground backdrop-blur-sm transition hover:bg-muted">
                    Pelajari Cara Kerjanya
                </a>
            </div>

            {{-- 4 Stat Badge Cards --}}
            <div class="mt-16 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:gap-6">
                <div class="rounded-2xl border border-border bg-card p-5 text-center shadow-xs">
                    <p class="text-3xl font-bold text-primary">20%</p>
                    <p class="mt-1 text-xs sm:text-sm font-medium text-muted-foreground">Komisi Penjualan</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-5 text-center shadow-xs">
                    <p class="text-3xl font-bold text-foreground">10%</p>
                    <p class="mt-1 text-xs sm:text-sm font-medium text-muted-foreground">Diskon Untuk Klien</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-5 text-center shadow-xs">
                    <p class="text-3xl font-bold text-foreground">5 Digit</p>
                    <p class="mt-1 text-xs sm:text-sm font-medium text-muted-foreground">Kode Referal Unik</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-5 text-center shadow-xs">
                    <p class="text-3xl font-bold text-primary">Rp 0</p>
                    <p class="mt-1 text-xs sm:text-sm font-medium text-muted-foreground">Biaya Pendaftaran</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== Cara Kerja ===================== --}}
    <section id="cara-kerja" class="border-t border-border bg-card/40 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Tiga Langkah Mudah Menghasilkan Pendapatan
                </h2>
                <p class="mt-3 text-sm sm:text-base text-muted-foreground">
                    Tidak ada birokrasi berbelit. Cukup daftar, ambil kode unik, dan mulai bagikan.
                </p>
            </div>

            <div class="mt-12 grid gap-8 md:grid-cols-3">
                <div class="relative rounded-2xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-lg">
                        1
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-foreground">Daftar &amp; Aktifkan Kode</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Buat akun gratis di Pesanvo. Di dashboard akun Anda, buka menu <strong>Program Mitra</strong> lalu klik <em>Aktifkan Kode Referal</em> untuk mendapatkan kode unik 5 huruf.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-lg">
                        2
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-foreground">Bagikan Kode atau Link</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Bagikan link referal atau kode promo unik Anda ke klien, komunitas developer, atau audiens Anda. Saat mereka mendaftar lewat link Anda, kode otomatis terpasang.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-lg">
                        3
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-foreground">Terima Komisi Transparan</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Klien menikmati potongan 10% pada tagihan pertama mereka. Setelah tagihan lunas, komisi 20% otomatis tercatat di dashboard Anda dan siap dicairkan ke rekening Anda.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== Simulasi Komisi ===================== --}}
    <section class="py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Berapa Penghasilan yang Bisa Anda Dapatkan?
                </h2>
                <p class="mt-3 text-sm sm:text-base text-muted-foreground">
                    Semakin banyak klien yang Anda bawa, semakin besar potensi komisi yang terkumpul.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Card Essentials --}}
                <div class="rounded-2xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="rounded-lg bg-muted px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Paket Essentials</span>
                        <span class="text-xs text-muted-foreground">Bulanan</span>
                    </div>
                    <p class="mt-4 text-2xl font-bold text-foreground">Rp 149.000</p>
                    <p class="text-xs text-muted-foreground">Harga normal per bulan</p>
                    <div class="my-5 border-t border-border/80"></div>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Klien membayar (diskon 10%):</span>
                            <span class="font-semibold text-foreground">Rp 134.100</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Komisi Anda (20%):</span>
                            <span class="font-bold text-primary">Rp 26.820 / klien</span>
                        </li>
                    </ul>
                </div>

                {{-- Card Prime --}}
                <div class="relative rounded-2xl border-2 border-primary bg-card p-6 shadow-md">
                    <div class="absolute -top-3 right-6 rounded-full bg-primary px-3 py-0.5 text-xs font-semibold text-primary-foreground">
                        Paling Populer
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-primary">Paket Prime</span>
                        <span class="text-xs text-muted-foreground">Bulanan</span>
                    </div>
                    <p class="mt-4 text-2xl font-bold text-foreground">Rp 249.000</p>
                    <p class="text-xs text-muted-foreground">Harga normal per bulan</p>
                    <div class="my-5 border-t border-border/80"></div>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Klien membayar (diskon 10%):</span>
                            <span class="font-semibold text-foreground">Rp 224.100</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Komisi Anda (20%):</span>
                            <span class="font-bold text-primary">Rp 44.820 / klien</span>
                        </li>
                    </ul>
                </div>

                {{-- Card Prime Tahunan --}}
                <div class="rounded-2xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="rounded-lg bg-muted px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Paket Prime Tahunan</span>
                        <span class="text-xs text-primary font-medium">Hemat 2 Bulan</span>
                    </div>
                    <p class="mt-4 text-2xl font-bold text-foreground">Rp 2.490.000</p>
                    <p class="text-xs text-muted-foreground">Harga normal per tahun</p>
                    <div class="my-5 border-t border-border/80"></div>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Klien membayar (diskon 10%):</span>
                            <span class="font-semibold text-foreground">Rp 2.241.000</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Komisi Anda (20%):</span>
                            <span class="font-bold text-primary">Rp 448.200 / klien</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 rounded-2xl border border-primary/20 bg-primary/5 p-6 text-center">
                <p class="text-sm sm:text-base font-semibold text-foreground">
                    💡 Contoh: Merekomendasikan 5 klien mengambil paket Prime tahunan = <span class="text-primary font-bold">Rp 2.241.000</span> komisi bersih langsung masuk ke dompet Anda!
                </p>
            </div>
        </div>
    </section>

    {{-- ===================== Mengapa Menjadi Mitra ===================== --}}
    <section class="border-t border-border bg-card/40 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Solusi Sempurna untuk Developer &amp; Agensi
                </h2>
                <p class="mt-3 text-sm sm:text-base text-muted-foreground">
                    Tambahkan nilai lebih pada setiap proyek aplikasi klien yang Anda kerjakan.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
                    </div>
                    <h3 class="font-semibold text-foreground">Pencairan Fleksibel</h3>
                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">Komisi dapat dicairkan via transfer bank atau e-wallet setelah verifikasi selesai.</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z"></path></svg>
                    </div>
                    <h3 class="font-semibold text-foreground">Dashboard Real-time</h3>
                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">Pantau berapa banyak klien yang mendaftar dan status pencairan komisi secara langsung.</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-semibold text-foreground">Infrastruktur Stabil</h3>
                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">Gateway berbasis Baileys yang stabil dan andal, membuat klien Anda puas dan tenang.</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-5 0a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"></path></svg>
                    </div>
                    <h3 class="font-semibold text-foreground">Dukungan Prioritas</h3>
                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">Tim teknis Pesanvo siap membantu kendala integrasi atau pertanyaan teknis dari klien Anda.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== FAQ ===================== --}}
    <section class="py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Pertanyaan Seputar Program Mitra
                </h2>
            </div>

            <div class="mt-10 space-y-4">
                <details class="group rounded-xl border border-border bg-card p-5 open:bg-card">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-foreground">
                        <span>Siapa saja yang boleh bergabung menjadi mitra?</span>
                        <svg class="h-5 w-5 text-muted-foreground transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                        Siapa saja dapat bergabung tanpa syarat khusus — mulai dari software developer, freelance programmer, agensi pemasaran digital, pemilik komunitas, hingga pengguna aktif Pesanvo.
                    </p>
                </details>

                <details class="group rounded-xl border border-border bg-card p-5">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-foreground">
                        <span>Bagaimana cara kerja kode referal?</span>
                        <summary-icon></summary-icon>
                        <svg class="h-5 w-5 text-muted-foreground transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                        Setiap mitra memiliki kode acak unik 5 huruf (misalnya <code>KXPMR</code>) atau tautan undangan langsung. Saat calon pelanggan memasukkan kode tersebut pada halaman checkout tagihan pertama mereka, mereka langsung mendapatkan potongan harga 10%, dan sistem mencatat komisi 20% untuk Anda.
                    </p>
                </details>

                <details class="group rounded-xl border border-border bg-card p-5">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-foreground">
                        <span>Kapan dan bagaimana komisi dicairkan?</span>
                        <svg class="h-5 w-5 text-muted-foreground transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                        Setelah tagihan klien terbayar lunas, status komisi Anda akan berstatus <em>Disetujui</em>. Anda dapat meminta pencairan komisi ke rekening bank (BCA, Mandiri, BRI, BNI) atau dompet digital (GoPay, OVO, Dana) dengan mengirimkan tiket melalui menu <strong>Bantuan</strong> di dashboard Anda.
                    </p>
                </details>

                <details class="group rounded-xl border border-border bg-card p-5">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-foreground">
                        <span>Bisakah saya menggunakan kode referal saya sendiri?</span>
                        <svg class="h-5 w-5 text-muted-foreground transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                        Tidak. Sistem kami dirancang untuk mencegah <em>self-referral</em> agar tercipta ekosistem yang sehat dan adil. Kode referal hanya berlaku untuk workspace milik pengguna lain yang Anda ajak.
                    </p>
                </details>
            </div>
        </div>
    </section>

    {{-- ===================== Bottom CTA ===================== --}}
    <section class="border-t border-border bg-primary/5 py-16">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Siap Mulai Menghasilkan Bersama Pesanvo?
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-sm sm:text-base text-muted-foreground">
                Hanya butuh 1 menit untuk mendaftar dan mengaktifkan kode mitra Anda.
            </p>
            <div class="mt-6">
                @auth
                    <a href="{{ route('mitra.index') }}" class="rounded-xl bg-primary px-8 py-3.5 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]">
                        Masuk ke Dashboard Mitra
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-primary px-8 py-3.5 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]">
                        Daftar Jadi Mitra Sekarang
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- ===================== Footer ===================== --}}
    <footer class="border-t border-border bg-background py-10">
        <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <p class="text-xs text-muted-foreground">
                &copy; {{ date('Y') }} Pesanvo. All rights reserved. Created by pesanvo.
            </p>
        </div>
    </footer>

</body>
</html>
