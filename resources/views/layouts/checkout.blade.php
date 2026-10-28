<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Checkout Pembayaran') &middot; {{ config('app.name') }}</title>

    <!-- Favicon -->
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        function applyTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyTheme();

        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.theme = isDark ? 'dark' : 'light';
        }
    </script>
</head>
<body class="min-h-full flex flex-col bg-background text-foreground antialiased font-sans selection:bg-primary/20 selection:text-primary">

    {{-- ===================== Header Checkout Khusus =====================
         Navigasi yang tenang dan terfokus: tanpa sidebar, tanpa menu dashboard
         yang memecah konsentrasi pengguna saat melakukan transaksi pembayaran.
         =================================================================== --}}
    <header class="sticky top-0 z-40 border-b border-border/80 bg-background/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            
            {{-- Logo & Identitas Brand --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="group flex items-center gap-2.5 transition">
                    <img src="{{ asset('images/pesanvo.png') }}" alt="Pesanvo" class="h-8 w-auto object-contain transition group-hover:scale-105">
                    <div class="flex flex-col">
                        <span class="text-base font-bold tracking-tight text-foreground">Pesanvo</span>
                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Gateway Pembayaran</span>
                    </div>
                </a>

                {{-- Garis Pemisah & Lencana Keamanan --}}
                <div class="hidden h-6 w-px bg-border sm:block"></div>
                <div class="hidden items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary sm:inline-flex">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span>Checkout Aman &amp; Terenkripsi</span>
                </div>
            </div>

            {{-- Aksi Kanan: Ganti Tema & Kembali ke Dashboard --}}
            <div class="flex items-center gap-2">
                {{-- Tombol Ganti Tema --}}
                <button type="button" onclick="toggleDarkMode()"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground active:scale-95"
                        aria-label="Ganti mode tampilan">
                    <svg class="hidden h-4 w-4 text-amber-400 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <svg class="block h-4 w-4 text-foreground dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>

                {{-- Tombol Kembali --}}
                <a href="{{ route('billing.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground sm:text-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    <span><span class="hidden sm:inline">Kembali ke </span>Dashboard</span>
                </a>
            </div>
        </div>
    </header>

    {{-- ===================== Konten Utama ===================== --}}
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        
        {{-- Pesan Status Berhasil --}}
        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/10 p-4 text-sm text-primary shadow-sm">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                <div class="flex-1 font-medium leading-relaxed">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        {{-- Pesan Kesalahan / Validasi --}}
        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive shadow-sm">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div class="flex-1">
                    <p class="font-semibold">Mohon periksa kembali formulir Anda:</p>
                    <ul class="mt-1.5 list-inside list-disc space-y-1 text-xs sm:text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ===================== Footer Checkout ===================== --}}
    <footer class="mt-auto border-t border-border/70 bg-card/40 py-8 text-xs text-muted-foreground">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                
                {{-- Jaminan Keamanan --}}
                <div class="flex flex-wrap items-center justify-center gap-4 sm:justify-start">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <span>Enkripsi SSL 256-Bit</span>
                    </div>
                    <span class="text-border">&bull;</span>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                        <span>Verifikasi Akurat &amp; Cepat</span>
                    </div>
                    <span class="text-border">&bull;</span>
                    <a href="{{ route('docs.show', 'bantuan') }}" target="_blank" class="hover:text-foreground hover:underline">
                        Butuh Bantuan? Hubungi Kami
                    </a>
                </div>

                {{-- Copyright --}}
                <p>&copy; {{ date('Y') }} Pesanvo. All rights reserved. Created by pesanvo.</p>
            </div>
        </div>
    </footer>

    @include('partials.pesan-server')

@stack('scripts')
</body>
</html>
