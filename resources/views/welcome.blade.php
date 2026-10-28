<!DOCTYPE html>
<html lang="id" class="scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-head', [
        'title' => 'Pesanvo Gateway — Kirim Notifikasi WhatsApp Lewat Satu REST API',
        'description' => 'Kirim notifikasi WhatsApp dari aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi yang tidak putus saat server di-deploy ulang.',
    ])

    <!-- Favicon -->
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

@php
    if (request()->filled('ref')) {
        $kodeRef = \App\Models\ReferralCode::normalkan((string) request()->query('ref'));
        if (\App\Models\ReferralCode::where('code', $kodeRef)->where('is_active', true)->exists()) {
            session(['referral_code' => $kodeRef]);
        }
    }
@endphp

@include('partials.header')


{{-- ===================== Hero Section ===================== --}}
<section class="relative flex min-h-[calc(100vh-4rem)] flex-col justify-center overflow-hidden py-12 sm:py-16 lg:py-20">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12 xl:gap-16">
            {{-- Sisi Kiri: Judul, Ringkasan, Aksi --}}
            <div class="min-w-0 lg:col-span-6 xl:col-span-6">
                <h1 class="muncul text-4xl sm:text-5xl lg:text-[2.85rem] xl:text-[3.25rem] font-bold tracking-tight text-foreground leading-[1.15]">
                    Kirim WhatsApp dari aplikasi Anda <span class="text-primary font-bold">lewat satu REST API</span>
                </h1>

                <p class="muncul mt-6 text-base sm:text-lg leading-relaxed text-muted-foreground max-w-xl" style="--tunda: 70ms">
                    Tautkan nomor sekali, lalu kirim notifikasi transaksi, OTP, tagihan, dan pesan pelanggan dari bahasa atau framework apa pun tanpa khawatir sesi terputus saat deploy ulang.
                </p>

                <div class="muncul mt-8 sm:mt-9 flex flex-wrap items-center gap-3.5" style="--tunda: 130ms">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm sm:text-base font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-[0.98]">
                        <span>Buat akun</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                    <a href="#harga" class="inline-flex items-center justify-center rounded-xl border border-input bg-card px-6 py-3 text-sm sm:text-base font-medium text-foreground hover:bg-muted transition-all">
                        Lihat harga
                    </a>
                </div>

                <div class="muncul mt-8 flex flex-wrap items-center gap-x-6 gap-y-2.5 text-xs sm:text-sm text-muted-foreground" style="--tunda: 180ms">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Putus tautan kapan saja
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Tanpa ikatan kontrak
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Setup instan &lt; 1 menit
                    </span>
                </div>
            </div>

            {{-- Sisi Kanan: Terminal cURL --}}
            <div class="muncul min-w-0 lg:col-span-6 xl:col-span-6" style="--tunda: 200ms">
                <div class="overflow-hidden rounded-2xl border border-border/80 bg-[var(--code-chrome)] shadow-xl">
                    <div class="flex items-center justify-between border-b border-white/10 bg-black/40 px-3.5 sm:px-4 py-3 gap-2">
                        <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#ff5f56]"></span>
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#ffbd2e]"></span>
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#27c93f]"></span>
                            <span class="ml-1 sm:ml-2 font-mono text-xs text-[var(--code-muted)] truncate">kirim-notifikasi.sh</span>
                        </div>
                        <span class="rounded bg-primary/20 border border-primary/30 px-2 sm:px-2.5 py-0.5 font-mono text-[10px] sm:text-[11px] font-semibold text-primary shrink-0">POST /api/v1/messages/text</span>
                    </div>
                    <pre class="overflow-x-auto p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">curl</span> -X POST https://wa.pesanvo.id/api/v1/messages/text \
  -H <span class="text-[var(--code-string)]">"X-Api-Key: vwa_live_9a8b7c6d..."</span> \
  -H <span class="text-[var(--code-string)]">"Content-Type: application/json"</span> \
  -d <span class="text-[var(--code-payload)]">'{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'</span>

<span class="text-[var(--code-muted)]">→ { "success": true, "data": { "status": "queued", "id": "msg_8921" } }</span></code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== Social Proof / Ekosistem Marquee ===================== --}}
<section class="border-y border-border/60 bg-muted/30 py-7 sm:py-8 overflow-hidden relative select-none">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4 sm:gap-6">
            
            {{-- Label Kiri --}}
            <div class="flex items-center gap-2.5 shrink-0 pr-3 sm:pr-5 border-r border-border/70">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs sm:text-sm font-bold tracking-tight text-foreground whitespace-nowrap">
                    Ekosistem &amp; Integrasi
                </span>
            </div>

            {{-- Track Marquee Kanan (Berjalan Mulus dari Kanan ke Kiri) --}}
            <div class="pesanvo-hero-marquee relative flex-1 overflow-hidden">
                {{-- Fade Gradien Kiri & Kanan --}}
                <div class="pointer-events-none absolute left-0 top-0 bottom-0 z-10 w-6 sm:w-12 bg-gradient-to-r from-muted/70 via-muted/30 to-transparent"></div>
                <div class="pointer-events-none absolute right-0 top-0 bottom-0 z-10 w-6 sm:w-12 bg-gradient-to-l from-muted/70 via-muted/30 to-transparent"></div>

                <div class="pesanvo-hero-track flex items-center">
                    @php
                        $marqueeItems = [
                            ['label' => 'Pesanvo.id Portal', 'tag' => 'Official App'],
                            ['label' => 'Laravel & PHP', 'tag' => 'SDK Ready'],
                            ['label' => 'Node.js & Express', 'tag' => 'REST API'],
                            ['label' => 'WooCommerce', 'tag' => 'Toko Online'],
                            ['label' => 'Pesanvo Helpdesk', 'tag' => 'Tiket Dukungan'],
                            ['label' => 'Python & FastAPI', 'tag' => 'Library'],
                            ['label' => 'Webhook Dispatcher', 'tag' => 'Real-time Event'],
                            ['label' => 'WHMCS & Billing', 'tag' => 'Auto-Invoice'],
                            ['label' => 'Pesanvo Artikel', 'tag' => 'Knowledge Base'],
                            ['label' => 'Zapier & n8n', 'tag' => 'Automasi'],
                            ['label' => 'Proteksi Anti-Blokir', 'tag' => 'Smart Delay'],
                            ['label' => 'Multi-Device Pairing', 'tag' => 'Multi-Sesi'],
                        ];
                    @endphp

                    {{-- Loop 2x untuk infinite seamless continuous marquee --}}
                    @for ($i = 0; $i < 2; $i++)
                        @foreach ($marqueeItems as $item)
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-xs sm:text-sm font-semibold text-foreground whitespace-nowrap">{{ $item['label'] }}</span>
                                <span class="text-[11px] text-muted-foreground whitespace-nowrap">({{ $item['tag'] }})</span>
                                <span class="mx-3.5 text-border/80 select-none text-xs">&bull;</span>
                            </div>
                        @endforeach
                    @endfor
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ===================== Cara Kerja ===================== --}}
<section id="cara-kerja" class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Tiga langkah, sekali saja
            </h2>
            <p class="muncul mt-2.5 text-xs sm:text-sm text-muted-foreground" style="--tunda: 60ms">
                Tanpa konfigurasi server mandiri yang merepotkan. Hubungkan nomor dan langsung pakai.
            </p>
        </div>

        <div class="relative mt-12 sm:mt-16">
            <div class="absolute left-0 right-0 top-5 hidden h-px bg-border/80 md:block" aria-hidden="true"></div>

            <div class="grid gap-10 md:grid-cols-3 md:gap-8">
                @php
                    $langkah = [
                        [
                            'nomor' => '01',
                            'judul' => 'Tautkan nomor',
                            'isi' => 'Sekali scan QR di dashboard. Setelah itu sesinya tersimpan permanen di volume terisolasi — termasuk saat server di-deploy ulang.',
                        ],
                        [
                            'nomor' => '02',
                            'judul' => 'Ambil API key',
                            'isi' => 'Satu kunci rahasia per aplikasi atau workspace, bisa dilihat lagi kapan saja dan dicabut sendiri tanpa mempengaruhi nomor lain.',
                        ],
                        [
                            'nomor' => '03',
                            'judul' => 'Panggil API-nya',
                            'isi' => 'Satu permintaan HTTP POST biasa dengan JSON standar. Tidak ada SDK yang wajib dipasang, antrean pesan diurus otomatis oleh gateway.',
                        ],
                    ];
                @endphp
                @foreach ($langkah as $i => $item)
                    <div class="muncul relative" style="--tunda: {{ $i * 80 }}ms">
                        <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary/25 bg-background font-mono text-xs font-semibold text-primary shadow-xs">
                            {{ $item['nomor'] }}
                        </span>
                        <h3 class="mt-4 text-sm sm:text-base font-semibold tracking-tight text-foreground">{{ $item['judul'] }}</h3>
                        <p class="mt-1.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $item['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ===================== Yang Membedakan & Fitur ===================== --}}
<section id="fitur" class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Showcase Arsitektur --}}
        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="min-w-0 lg:col-span-6">
                <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground leading-snug">
                    Deploy ulang tanpa scan QR lagi
                </h2>
                <p class="muncul mt-4 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                    Gateway WhatsApp yang menempel di dalam aplikasi akan meminta scan ulang setiap kali servernya dinyalakan ulang. Di sini kredensial nomor disimpan terpisah dan dicadangkan berkala, jadi nomor Anda menyala sendiri begitu container kembali hidup.
                </p>
                <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 90ms">
                    Nomor tetap bisa Anda ganti kapan saja lewat Putus tautan → Hubungkan.
                </p>
            </div>

            {{-- Timeline Restart --}}
            <div class="muncul lg:col-span-6" style="--tunda: 120ms">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Saat server di-deploy ulang</p>
                <ol class="mt-5 space-y-5">
                    @php
                        $urutan = [
                            ['Container dimatikan', 'Sesi login WhatsApp diserialisasi dan disimpan rapi sebelum proses berhenti.'],
                            ['Versi baru menyala', 'Engine gateway mengontak Laravel untuk memeriksa nomor mana saja yang berstatus aktif.'],
                            ['Nomor tersambung sendiri', 'Koneksi WhatsApp pulih seketika tanpa scan QR dan tanpa tindakan manual dari Anda.'],
                        ];
                    @endphp
                    @foreach ($urutan as [$judul, $isi])
                        <li class="flex gap-3.5">
                            <div class="flex flex-col items-center self-stretch">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground shadow-xs">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                                @if (! $loop->last)
                                    <span class="mt-1.5 w-px flex-1 bg-border"></span>
                                @endif
                            </div>
                            <div class="pb-1">
                                <p class="text-xs sm:text-sm font-medium text-foreground">{{ $judul }}</p>
                                <p class="mt-0.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- 6 Fitur Inti --}}
        <div class="mt-16 sm:mt-20 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $fitur = [
                    ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'Banyak nomor sekaligus', 'Satu nomor bermasalah tidak menghentikan pengiriman nomor lainnya.'],
                    ['M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z', 'Jeda kirim otomatis', 'Tiap pesan diberi jeda acak dan diantre per nomor, supaya nomor Anda tidak terbaca sebagai spam.'],
                    ['M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5', 'Webhook pesan masuk', 'Balasan pelanggan diteruskan ke URL aplikasi Anda, bertanda tangan digital.'],
                    ['M9 11l3 3 8-8M21 12a9 9 0 1 1-6.22-8.56', 'Riwayat & status kirim', 'Terkirim, sampai (delivered), atau dibaca (read) tercatat per pesan secara akurat.'],
                    ['M3 7h18M3 12h18M3 17h18', 'Workspace terpisah', 'Cabang atau klien punya nomor, API key, dan riwayat pengiriman sendiri.'],
                    ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', 'Kunci bisa dicabut sendiri', 'Satu kunci bocor tidak berarti seluruh nomor Anda ikut jatuh atau terancam.'],
                ];
            @endphp
            @foreach ($fitur as $i => [$ikon, $judul, $isi])
                <div class="muncul" style="--tunda: {{ ($i % 3) * 70 }}ms">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $ikon }}"/></svg>
                    </div>
                    <h3 class="mt-3 text-sm sm:text-base font-semibold text-foreground">{{ $judul }}</h3>
                    <p class="mt-1 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Kapan Terpakai ===================== --}}
<section class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1fr_1.6fr] lg:gap-14">
            <div>
                <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground leading-snug">
                    Di mana pun aplikasi Anda perlu bicara
                </h2>
                <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                    Kirim pesan notifikasi otomatis langsung ke aplikasi yang dibuka pelanggan setiap hari.
                </p>
            </div>

            <div class="divide-y divide-border/70 border-y border-border/70">
                @php
                    $pemakaian = [
                        ['Toko & e-commerce', 'Konfirmasi pesanan masuk, rincian tagihan, nomor resi pengiriman, dan pemberitahuan barang sampai ke kurir.'],
                        ['Penagihan & invoice', 'Invoice terbit, jatuh tempo H-3, atau pemberitahuan pembayaran lunas — terkirim langsung ke nomor pelanggan.'],
                        ['Jasa & reservasi terjadwal', 'Pengingat janji temu dokter, reservasi meja, atau jadwal servis sehari sebelumnya secara otomatis dari sistem Anda.'],
                        ['Verifikasi & OTP', 'Kode verifikasi sekali pakai yang dikirim dari nomor resmi brand Anda sendiri demi menjaga kredibilitas dan keamanan.'],
                    ];
                @endphp
                @foreach ($pemakaian as $i => [$judul, $isi])
                    <div class="muncul flex flex-col gap-1.5 py-5 sm:flex-row sm:gap-8" style="--tunda: {{ $i * 60 }}ms">
                        <h3 class="shrink-0 text-sm sm:text-base font-semibold text-foreground sm:w-52">{{ $judul }}</h3>
                        <p class="text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ===================== Harga ===================== --}}
<section id="harga" class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
         x-data="{
             tahunan: false,

             /*
              | Dua kelompok, bukan lima kartu berjajar.
              |
              | Bisnis dan Enterprise menjawab pertanyaan yang berbeda —
              | 'berapa yang saya bayar tiap bulan' versus 'bagaimana kalau
              | kebutuhan saya tidak berbentuk paket'. Menjajarkan kelimanya
              | membuat pengunjung membandingkan angka yang memang tidak
              | sebanding, lalu memilih yang paling murah alih-alih yang paling
              | cocok.
             */
             kelompok: 'bisnis',

             formatRupiah(angka) {
                 return 'Rp' + new Intl.NumberFormat('id-ID').format(angka);
             },
         }">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Bayar sesuai besarnya pemakaian
            </h2>
            <p class="muncul mx-auto mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                Semua paket memakai gateway, API, dan dashboard yang sama. Yang membedakan hanya seberapa besar Anda memakainya.
            </p>

            {{-- Pemilih kelompok. Di ATAS sakelar bulanan/tahunan karena ia
                 menentukan apakah sakelar itu berarti sama sekali — pilihan
                 skala besar tidak punya harga bulanan. --}}
            <div class="muncul mt-7 inline-flex items-center rounded-2xl border border-border bg-card p-1.5 shadow-xs" style="--tunda: 75ms">
                <button @click="kelompok = 'bisnis'" type="button"
                        class="rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="kelompok === 'bisnis' ? 'bg-foreground text-background shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    Bisnis
                </button>
                <button @click="kelompok = 'enterprise'" type="button"
                        class="rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="kelompok === 'enterprise' ? 'bg-foreground text-background shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    Enterprise
                </button>
            </div>

            {{-- Switch Bulanan / Tahunan Modern --}}
            <div x-show="kelompok === 'bisnis'" x-cloak
                 class="muncul mt-4 inline-flex items-center rounded-2xl border border-border bg-card p-1.5 shadow-xs" style="--tunda: 90ms">
                <button @click="tahunan = false"
                        type="button"
                        class="rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="!tahunan ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    Bulanan
                </button>
                <button @click="tahunan = true"
                        type="button"
                        class="flex items-center gap-2 rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="tahunan ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    <span>Tahunan</span>
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-300">
                        Hemat 2 Bulan
                    </span>
                </button>
            </div>
        </div>

        @php
            $paket = collect(\App\Support\Plan::all())->map(fn ($plan) => [
                'nama' => $plan->name(),
                'bulanan' => $plan->monthlyPrice(),
                'tahunan' => $plan->price('yearly'),
                'untuk' => $plan->tagline(),
                'sorot' => $plan->isHighlighted(),
                'fitur' => $plan->features(),

                /*
                 | Harga perkenalan selalu ditampilkan di halaman depan, dan itu
                 | benar: setiap pengunjung di sini belum punya workspace, jadi
                 | pembelian mereka PASTI yang pertama. Di dalam dashboard
                 | ceritanya berbeda — di sana kelayakannya diperiksa per
                 | workspace lewat `belumPernahBayar()`.
                */
                'promo_bulanan' => $plan->introPrice('monthly') ?? 0,
                'promo_tahunan' => $plan->introPrice('yearly') ?? 0,
                'hemat_bulanan' => $plan->introPercent('monthly'),
                'hemat_tahunan' => $plan->introPercent('yearly'),
            ])->all();
        @endphp

        <div x-show="kelompok === 'bisnis'" x-cloak class="mt-12 grid items-stretch gap-8 lg:grid-cols-3">
            @foreach ($paket as $i => $p)
                <div class="muncul relative flex flex-col justify-between rounded-2xl border p-7 sm:p-8 transition-all duration-300 {{ $p['sorot'] ? 'border-primary bg-card ring-2 ring-primary/20 shadow-xl lg:-translate-y-2' : 'border-border/80 bg-card hover:border-primary/40 hover:shadow-md' }}"
                     style="--tunda: {{ $i * 90 }}ms">
                    @if ($p['sorot'])
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                            <span class="inline-flex items-center rounded-full bg-primary px-4 py-1 text-[11px] font-bold uppercase tracking-wider text-primary-foreground shadow-md">
                                Paling Populer
                            </span>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-xl font-bold tracking-tight text-foreground">{{ $p['nama'] }}</h3>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground min-h-[38px]">{{ $p['untuk'] }}</p>

                        <div class="mt-6 border-t border-border/60 pt-6">
                            @if ($p['promo_bulanan'] > 0 || $p['promo_tahunan'] > 0)
                                {{-- Harga normal tercoret di ATAS harga promo.
                                     Kalau cuma angka promonya yang tampil, tidak
                                     ada yang tahu ada potongan sama sekali — dan
                                     potongan yang tidak terlihat tidak menjual
                                     apa pun. --}}
                                <p class="text-xs sm:text-sm font-semibold text-muted-foreground line-through decoration-destructive/70">
                                    <span x-text="formatRupiah(tahunan ? {{ $p['tahunan'] }} : {{ $p['bulanan'] }})"></span>
                                </p>
                                <div class="mt-0.5 flex items-baseline gap-1.5 flex-wrap">
                                    <span class="text-3xl sm:text-4xl font-bold tracking-tight text-primary"
                                          x-text="formatRupiah(tahunan ? {{ $p['promo_tahunan'] }} : {{ $p['promo_bulanan'] }})">
                                        Rp{{ number_format($p['promo_bulanan'], 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs sm:text-sm font-medium text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'">/bulan</span>
                                    <span class="rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                        Hemat <span x-text="tahunan ? {{ $p['hemat_tahunan'] }} : {{ $p['hemat_bulanan'] }}"></span>%
                                    </span>
                                </div>

                                {{-- WAJIB ikut. Harga perkenalan tanpa keterangan
                                     "sekali" akan dibaca sebagai harga tetap, dan
                                     yang menemukan kebenarannya adalah pelanggan
                                     saat tagihan kedua terbit hampir dua kali
                                     lipat. Janji yang dilanggar di hadapan orang
                                     yang baru saja membayar. --}}
                                <p class="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                                    Harga pembelian pertama. Perpanjangan berikutnya
                                    <span class="font-medium" x-text="formatRupiah(tahunan ? {{ $p['tahunan'] }} : {{ $p['bulanan'] }})"></span><span x-text="tahunan ? '/tahun' : '/bulan'"></span>.
                                </p>
                            @else
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground"
                                          x-text="formatRupiah(tahunan ? {{ $p['tahunan'] }} : {{ $p['bulanan'] }})">
                                        Rp{{ number_format($p['bulanan'], 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs sm:text-sm font-medium text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'">/bulan</span>
                                </div>

                                <p class="mt-1 text-xs text-muted-foreground" :class="tahunan ? '' : 'invisible'">
                                    <span x-text="'Setara ' + formatRupiah(Math.floor({{ $p['tahunan'] }} / 12)) + ' per bulan.'"></span>
                                </p>
                            @endif
                        </div>

                        <a href="{{ route('register') }}"
                           class="mt-6 block w-full rounded-xl py-2.5 text-center text-xs sm:text-sm font-semibold transition-all shadow-xs active:scale-[0.98] {{ $p['sorot'] ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md hover:shadow-primary/20' : 'border border-input bg-background hover:bg-muted text-foreground' }}">
                            Pilih {{ $p['nama'] }}
                        </a>

                        <div class="mt-6 border-t border-border/60 pt-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-foreground/80 mb-3.5">Fitur yang didapatkan:</p>
                            <ul class="space-y-3 text-xs sm:text-sm">
                                @foreach ($p['fitur'] as $f)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                        <span class="text-muted-foreground leading-relaxed">{{ $f }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ===================== Enterprise =====================

             Dua pilihan yang tidak berbentuk paket bulanan: bayar sesuai
             pemakaian, dan kesepakatan yang disusun sendiri. Keduanya berdiri
             sejajar di sini karena keduanya menjawab pertanyaan yang sama —
             "bagaimana kalau kebutuhan saya tidak muat di tiga kartu itu".
             ================================================================= --}}
        {{-- Lebarnya disamakan dengan kartu bulanan, bukan dibiarkan melebar.
             Kartu Bisnis = (1216 − 2×32) ÷ 3 = 384px, jadi dua kartu di sini
             butuh 384×2 + 32 = 800px (50rem). Tanpa batas itu, dua kartu
             Enterprise membentang jauh lebih lebar daripada tiga kartu di
             sebelahnya — dan sakelar yang mengubah ukuran kartu saat ditekan
             membuat halamannya terbaca seperti dua halaman berbeda. --}}
        <div x-show="kelompok === 'enterprise'" x-cloak
             class="mx-auto mt-12 grid max-w-[50rem] items-stretch gap-8 sm:grid-cols-2">

        {{-- ===================== Pay as you go Card ===================== --}}
        @php $payg = \App\Support\Plan::payg(); @endphp
        <div class="muncul relative flex flex-col overflow-hidden rounded-2xl border border-border/80 bg-card p-7 sm:p-9 shadow-md transition-all duration-300 hover:border-primary/40 hover:shadow-xl"
             style="--tunda: 90ms">
            <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>

            <div class="flex flex-col gap-6">
                <div class="space-y-3">
                    <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $payg->name() }}</h3>
                    <p class="text-sm sm:text-base text-muted-foreground leading-relaxed">
                        {{ $payg->tagline() }} Isi saldo sesuai kebutuhan, lalu pakai kapan saja — saldo tidak punya masa kedaluwarsa dan hanya berkurang saat pesan benar-benar terkirim.
                    </p>
                    <div class="flex flex-col gap-y-2 text-xs text-muted-foreground pt-1">
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Pesan gagal tidak memotong saldo
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Saldo aktif selamanya (tanpa kedaluwarsa)
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            1 nomor WhatsApp aktif
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-5">
                    <div>
                        {{-- Harga per pesan sengaja TIDAK ditampilkan di sini.
                             Angka satuan di halaman harga mengundang orang
                             menghitung sendiri lalu menyimpulkan paket bulanan
                             lebih murah — padahal PAYG memang bukan untuk yang
                             kirimannya rutin. Yang dijual di kartu ini bentuk
                             pembayarannya, bukan tarifnya. Rinciannya terbuka
                             penuh di halaman Saldo setelah mereka memakainya. --}}
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-xs sm:text-sm font-medium text-muted-foreground">mulai</span>
                            <span class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">
                                Rp {{ number_format(config('billing.payg.min_topup', 50000), 0, ',', '.') }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Isi saldo sekali, pakai kapan saja</p>
                    </div>

                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-primary px-7 py-3 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all active:scale-[0.98]">
                        Pilih {{ $payg->name() }}
                    </a>
                </div>
            </div>

            <div class="mt-auto border-t border-border/60 pt-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-foreground/80 mb-3.5">Fitur paket Pay as you go:</p>
                <ul class="grid gap-3 text-xs sm:text-sm">
                    @foreach ($payg->features() as $f)
                        <li class="flex items-start gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                            <span class="text-muted-foreground leading-relaxed">{{ $f }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- ===================== Enterprise =====================

             Menggantikan tautan keluar ke situs profil perusahaan. Tautan itu
             membuang orang yang paling siap membayar ke situs lain tepat saat
             mereka sedang menimbang — dan tidak meninggalkan satu pun jejak
             siapa yang pernah bertanya. Sekarang formnya di sini dan datanya
             masuk ke panel admin.
             ======================================================== --}}
        @php $ent = \App\Support\Plan::get('enterprise'); @endphp

        <div id="enterprise" class="muncul flex flex-col overflow-hidden rounded-2xl border border-border/80 bg-card shadow-md transition-all duration-300 hover:border-primary/40 hover:shadow-xl" style="--tunda: 180ms">
            <div class="flex flex-1 flex-col gap-6 p-7 sm:p-9">
                <div class="space-y-3">
                    <span class="inline-flex items-center rounded-full bg-foreground/5 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        Untuk kebutuhan besar
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $ent->name() }}</h3>
                    <p class="text-sm sm:text-base leading-relaxed text-muted-foreground">
                        Butuh lebih banyak nomor, kuota di atas Elite, atau retensi riwayat yang lebih
                        panjang? Batas dan harganya kami susun mengikuti kebutuhan Anda — bukan
                        dipaksa masuk salah satu paket Bisnis.
                    </p>
                    <ul class="grid gap-2 pt-1 text-xs sm:text-sm">
                        @foreach ($ent->features() as $f)
                            <li class="flex items-start gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span class="text-muted-foreground leading-relaxed">{{ $f }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-auto">
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Hubungi kami</p>
                    <p class="mt-1 text-xs text-muted-foreground">Dijawab dalam 1&times;24 jam hari kerja</p>
                    {{-- Menuju halaman Enterprise, bukan membuka formulir di
                         sini. Yang menimbang Enterprise butuh memasukkan
                         angkanya lalu melihat hasilnya berubah, dan itu
                         percakapan yang tidak muat di sela-sela kartu paket. --}}
                    <a href="{{ route('enterprise') }}"
                       class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-primary px-7 py-3 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90 active:scale-[0.98]">
                        Hitung perkiraan &amp; minta penawaran
                    </a>
                </div>
            </div>

        </div>
        </div>{{-- /kelompok Enterprise --}}
    </div>
</section>

{{-- ===================== Mitra Bank & Metode Pembayaran ===================== --}}
<section aria-label="Metode Pembayaran yang Didukung"
         class="relative overflow-hidden border-b border-border/60 bg-muted/20 py-3 sm:py-4">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 sm:gap-6 sm:px-6 lg:px-8">
        <!-- Label Tetap -->
        <div class="flex shrink-0 items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-muted-foreground select-none whitespace-nowrap">
                Payment
            </span>
        </div>

        <!-- Track Marquee -->
        <div class="pesanvo-pay-marquee relative flex-1 overflow-hidden">
            <!-- Fade Kiri & Kanan (adaptif light & dark mode) -->
            <div class="pointer-events-none absolute left-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-r from-background via-background/80 to-transparent"></div>
            <div class="pointer-events-none absolute right-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-l from-background via-background/80 to-transparent"></div>

            <div class="pesanvo-pay-track flex w-max items-center gap-3 sm:gap-4">
                @php
                    $marqueePayments = [
                        ['label' => 'QRIS', 'file' => 'qris.svg'],
                        ['label' => 'Bank BCA', 'file' => 'bca.svg'],
                        ['label' => 'Bank BNI', 'file' => 'bni.svg'],
                        ['label' => 'Bank BRI', 'file' => 'bri.svg'],
                        ['label' => 'Bank Mandiri', 'file' => 'mandiri.svg'],
                        ['label' => 'Bank BSI', 'file' => 'bsi.svg'],
                        ['label' => 'Bank Permata', 'file' => 'permata.svg'],
                        ['label' => 'CIMB Niaga', 'file' => 'cimb.svg'],
                        ['label' => 'Bank Sahabat Sampoerna', 'file' => 'bss.svg'],
                        ['label' => 'Indomaret', 'file' => 'indomaret.svg'],
                        ['label' => 'Alfamart', 'file' => 'alfamart.svg'],
                        ['label' => 'AstraPay', 'file' => 'astrapay.svg'],
                        ['label' => 'OVO', 'file' => 'ovo.svg'],
                        ['label' => 'ShopeePay', 'file' => 'shopeepay.svg'],
                        ['label' => 'Akulaku PayLater', 'file' => 'akulaku.svg'],
                    ];
                @endphp

                {{-- Loop 2x untuk infinite seamless scroll --}}
                @for ($i = 0; $i < 2; $i++)
                    @foreach ($marqueePayments as $payment)
                        <div class="flex h-9 sm:h-10 shrink-0 items-center justify-center rounded-xl border border-border/80 bg-white px-3 sm:px-4 shadow-2xs transition-all duration-200"
                             title="{{ $payment['label'] }}">
                            <img src="{{ asset('images/payments/' . $payment['file']) }}"
                                 alt="{{ $payment['label'] }}"
                                 loading="lazy"
                                 class="h-4 sm:h-5 w-auto max-w-[65px] sm:max-w-[78px] object-contain">
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    </div>
</section>

{{-- ===================== Untuk Developer ===================== --}}
<section id="untuk-developer" class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                HTTP dan JSON biasa
            </h2>
            <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                Tidak ada SDK yang wajib dipasang. Kalau bahasa atau framework Anda bisa memanggil URL, ia bisa memakai gateway ini.
            </p>
        </div>

        <div class="muncul mt-8" style="--tunda: 100ms" x-data="{ bahasa: 'php', disalin: false }">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5">
                <div class="flex flex-wrap gap-1 text-xs">
                    @foreach (['php' => 'PHP / Laravel', 'node' => 'Node.js', 'python' => 'Python'] as $kode => $label)
                        <button @click="bahasa = '{{ $kode }}'"
                                class="rounded-lg px-2.5 sm:px-3 py-1 font-medium transition"
                                :class="bahasa === '{{ $kode }}' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-background hover:text-foreground'">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        @click="
                            let text = document.getElementById('code-' + bahasa).innerText;
                            navigator.clipboard.writeText(text);
                            disalin = true;
                            setTimeout(() => disalin = false, 2000);
                        "
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted transition-all">
                    <svg x-show="!disalin" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <svg x-show="disalin" x-cloak class="h-3 w-3 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span x-text="disalin ? 'Tersalin!' : 'Salin'">Salin</span>
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-[var(--code-chrome)] shadow-md">
                <div x-show="bahasa === 'php'" id="code-php">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">use</span> Illuminate\Support\Facades\Http;

Http::withHeaders([
    <span class="text-[var(--code-string)]">'X-Api-Key'</span> =&gt; config(<span class="text-[var(--code-string)]">'services.wa.key'</span>),
])-&gt;post(<span class="text-[var(--code-string)]">'https://wa.pesanvo.id/api/v1/messages/text'</span>, [
    <span class="text-[var(--code-payload)]">'to'</span>      =&gt; $invoice-&gt;customer_phone,
    <span class="text-[var(--code-payload)]">'message'</span> =&gt; <span class="text-[var(--code-string)]">"Invoice {$invoice->number} sudah lunas."</span>,
]);</code></pre>
                </div>

                <div x-show="bahasa === 'node'" x-cloak id="code-node">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">await</span> fetch(<span class="text-[var(--code-string)]">'https://wa.pesanvo.id/api/v1/messages/text'</span>, {
  method: <span class="text-[var(--code-string)]">'POST'</span>,
  headers: {
    <span class="text-[var(--code-payload)]">'X-Api-Key'</span>: process.env.WA_KEY,
    <span class="text-[var(--code-payload)]">'Content-Type'</span>: <span class="text-[var(--code-string)]">'application/json'</span>,
  },
  body: JSON.stringify({ to: pesanan.telepon, message: teks }),
});</code></pre>
                </div>

                <div x-show="bahasa === 'python'" x-cloak id="code-python">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code>requests.post(
    <span class="text-[var(--code-string)]">"https://wa.pesanvo.id/api/v1/messages/text"</span>,
    headers={<span class="text-[var(--code-payload)]">"X-Api-Key"</span>: os.environ[<span class="text-[var(--code-string)]">"WA_KEY"</span>]},
    json={<span class="text-[var(--code-payload)]">"to"</span>: pelanggan.telepon, <span class="text-[var(--code-payload)]">"message"</span>: teks},
    timeout=<span class="text-[var(--code-payload)]">10</span>,
)</code></pre>
                </div>
            </div>
        </div>

        <div class="muncul mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs sm:text-sm font-medium" style="--tunda: 140ms">
            <a href="{{ route('docs.show', 'referensi-api') }}" class="inline-flex items-center gap-1 text-primary hover:underline">
                Referensi API
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('docs.show', 'contoh-integrasi') }}" class="text-muted-foreground hover:text-foreground">Contoh integrasi</a>
            <a href="{{ route('docs.show', 'webhook') }}" class="text-muted-foreground hover:text-foreground">Webhook</a>
            <a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground">Semua dokumentasi</a>
        </div>
    </div>
</section>


{{-- ===================== FAQ ===================== --}}
<section class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Pertanyaan yang sering muncul
            </h2>
            <p class="muncul mt-2 text-xs sm:text-sm text-muted-foreground" style="--tunda: 60ms">
                Semua informasi lengkap yang Anda butuhkan seputar penautan nomor, integrasi API, keamanan data, fitur webhook, dan batas pemakaian gateway.
            </p>
        </div>

        <div class="mt-8 divide-y divide-border/60 border-y border-border/60" x-data="{ terbuka: 0 }">
            @php
                $tanya = [
                    [
                        'Apakah nomor WhatsApp saya terkunci di platform ini?',
                        'Tidak sama sekali. Anda memegang kendali penuh atas nomor WhatsApp yang ditautkan. Anda dapat memutuskan tautan (disconnect) kapan saja melalui dashboard dan menggantinya dengan nomor baru tanpa biaya tambahan atau birokrasi verifikasi yang rumit. Riwayat log pesan sebelumnya tetap tersimpan aman di akun Anda.',
                    ],
                    [
                        'Berapa lama waktu yang dibutuhkan untuk pemasangan pertama kali?',
                        'Proses penautan nomor pertama hanya membutuhkan waktu sekitar 30–60 detik melalui scan kode QR di dashboard Pesanvo. Setelah nomor terhubung dan API Key diterbitkan, integrasi ke aplikasi Anda dapat selesai dalam hitungan menit cukup dengan mengirimkan satu HTTP POST request standar berisi format JSON.',
                    ],
                    [
                        'Apa yang terjadi jika kuota pesan bulanan saya habis?',
                        'Sistem tidak akan membiarkan pesan gagal secara diam-diam (silent failure). Gateway akan mengembalikan respon HTTP 429 Too Many Requests dengan pesan error yang jelas dan detail. Seluruh status pengiriman tercatat secara transparan di dashboard, dan Anda dapat melakukan upgrade paket seketika kapan saja untuk melanjutkan pengiriman tanpa perlu pairing ulang nomor.',
                    ],
                    [
                        'Bagaimana jika aplikasi backend saya tidak menggunakan framework Laravel?',
                        'Pesanvo Gateway dibangun menggunakan standar terbuka REST API berbasis JSON murni. Layanan ini kompatibel 100% dengan bahasa pemrograman, runtime, atau framework apa pun—mulai dari Node.js (Express, NestJS), Python (Django, FastAPI), PHP native, Go, Java (Spring), C# (.NET), hingga platform no-code seperti Make, Zapier, dan n8n. Dokumentasi kami menyediakan contoh kode siap salin untuk berbagai bahasa.',
                    ],
                    [
                        'Apakah nomor WhatsApp saya aman dari risiko pemblokiran (banned)?',
                        'Kami menerapkan arsitektur antrean cerdas (smart queue engine) dengan jeda acak natural (dynamic jitter) antar-pesan untuk mensimulasikan pola interaksi manusia dan menghindari deteksi bot oleh WhatsApp. Selain itu, kami menyarankan pengiriman pesan yang relevan (transaksional, OTP, notifikasi pesanan) dan menghindari spam massal ke nomor yang tidak pernah berinteraksi dengan Anda.',
                    ],
                    [
                        'Bisakah saya menggunakan nomor WhatsApp biasa (Personal) atau wajib WhatsApp Business?',
                        'Anda bebas menggunakan jenis nomor apa pun, baik WhatsApp Personal standar maupun WhatsApp Business biasa. Anda tidak diwajibkan memiliki centang hijau (green tick) ataupun akun Facebook Business Manager yang rumit. Cukup pastikan nomor kartu SIM aktif dan sudah terdaftar di aplikasi WhatsApp resmi ponsel Anda.',
                    ],
                    [
                        'Apakah gateway mendukung pengiriman file media seperti PDF, dokumen, dan gambar?',
                        'Ya, tentu saja. Selain pesan teks reguler, API Pesanvo Gateway mendukung pengiriman berbagai jenis media digital, termasuk gambar (JPEG, PNG), dokumen dokumen faktur/tagihan (PDF, spreadsheet XLSX), audio, hingga pesan lokasi. Cukup sertakan URL media publik yang valid pada payload API pengiriman media.',
                    ],
                    [
                        'Apakah pesan masuk dari pelanggan dapat diterima dan diproses otomatis (Webhook)?',
                        'Ya, sangat bisa. Anda dapat mengonfigurasikan URL Webhook di dashboard untuk menerima notifikasi pesan masuk secara real-time. Setiap kali pelanggan mengirimkan balasan, gateway akan langsung meneruskan payload data ke endpoint server Anda, memungkinkan Anda membangun bot interaktif, integrasi CRM, atau ticketing helpdesk pelanggan.',
                    ],
                    [
                        'Bagaimana dengan keamanan dan privasi data pesan pelanggan saya?',
                        'Keamanan dan privasi data Anda adalah prioritas utama kami. Seluruh lalu lintas data antara aplikasi Anda, gateway kami, dan server WhatsApp dienkripsi menggunakan protokol SSL/TLS 256-bit standar perbankan. Kami tidak pernah membagikan atau menjual isi pesan pelanggan Anda, dan log pesan hanya dapat diakses oleh pemilik akun untuk keperluan audit.',
                    ],
                ];
            @endphp
            @foreach ($tanya as $i => [$judul, $isi])
                <div class="muncul py-4" style="--tunda: {{ $i * 40 }}ms">
                    <button @click="terbuka = terbuka === {{ $i }} ? null : {{ $i }}"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 text-left font-medium text-foreground text-sm sm:text-base">
                        <span>{{ $judul }}</span>
                        <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                             :class="terbuka === {{ $i }} ? 'rotate-180 text-primary' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="terbuka === {{ $i }}" x-collapse x-cloak>
                        <p class="pt-2.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Integrasi AI Agent ===================== --}}
<section id="ai-agent" class="py-20 sm:py-28 overflow-hidden">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
         x-data="{
             agentAktif: 'claude',
             disalin: false,
             prompts: {
                 claude: `Tambahkan integrasi WhatsApp Gateway ke project ini menggunakan REST API Pesanvo (https://wa.pesanvo.id).

Spesifikasi integrasi:
- Base URL: https://wa.pesanvo.id/api/v1
- Endpoint kirim teks: POST /messages/text
- Header:
    X-Api-Key: env('PESANVO_KEY')
    Content-Type: application/json
- Payload JSON:
    {
      &quot;to&quot;: &quot;081234567890&quot;,
      &quot;message&quot;: &quot;Halo! Pesan transaksional dari sistem.&quot;
    }
- Format respon sukses:
    { &quot;success&quot;: true, &quot;data&quot;: { &quot;id&quot;: &quot;msg_1029&quot;, &quot;status&quot;: &quot;queued&quot; } }

Tolong buatkan helper service yang modular dengan validasi nomor tujuan dan error handling yang aman.`,
                 cursor: `# Aturan Pesanvo Gateway untuk .cursorrules

Ketika membuat fitur pengiriman pesan, notifikasi, atau verifikasi OTP WhatsApp:
1. Panggil endpoint REST API: POST https://wa.pesanvo.id/api/v1/messages/text
2. Autentikasi: sertakan header 'X-Api-Key' dari variabel lingkungan PESANVO_KEY.
3. Payload JSON:
   {
     &quot;to&quot;: &quot;08xxxxxxxxxx&quot;,
     &quot;message&quot;: &quot;Isi pesan WhatsApp&quot;
   }
4. Jangan pernah mengekspos API key di client-side / frontend.
5. Tangani respon galat 401 (kunci salah) dan 422 (data tidak valid) dengan elegan.`,
                 antigravity: `Konfigurasikan integrasi WhatsApp otomatis untuk workspace ini menggunakan Pesanvo Gateway API:
- Base URL: https://wa.pesanvo.id/api/v1
- Endpoint: POST /messages/text
- Header: X-Api-Key: [API_KEY_ANDA], Content-Type: application/json
- Body: { &quot;to&quot;: &quot;08xxxxxxxxxx&quot;, &quot;message&quot;: &quot;Pesan otomatis sistem&quot; }
- Dokumentasi API lengkap: https://wa.pesanvo.id/docs
- Pastikan fungsi kirim mengembalikan status antrean pesan tanpa menghentikan thread utama aplikasi.`,
                 opencode: `Integrasikan pengiriman pesan WhatsApp via Pesanvo Gateway.
URL: https://wa.pesanvo.id/api/v1/messages/text
Method: POST
Headers:
  X-Api-Key: os.getenv('WA_KEY')
  Content-Type: application/json
Body:
  {
    &quot;to&quot;: &quot;081234567890&quot;,
    &quot;message&quot;: &quot;Pesan verifikasi sistem&quot;
  }
Buatkan modul client HTTP yang bersih dan siap diuji.`,
                 codex: `Write a clean and robust service module to send WhatsApp messages using Pesanvo Gateway.
API URL: https://wa.pesanvo.id/api/v1/messages/text
Method: POST
Headers:
  X-Api-Key: process.env.WA_API_KEY
  Content-Type: application/json
Body:
  { &quot;to&quot;: recipient_phone, &quot;message&quot;: text_message }
Requirements:
- Validate phone number input (supports 08... or 628...)
- Parse JSON response and log queue message ID
- Add exponential retry on 5xx server errors`,
                 windsurf: `Integrasikan Pesanvo Gateway API ke dalam alur aplikasi:
- Endpoint: POST https://wa.pesanvo.id/api/v1/messages/text
- Header: X-Api-Key: env('WA_API_KEY')
- Request Body: { &quot;to&quot;: &quot;081234567890&quot;, &quot;message&quot;: &quot;Notifikasi pesanan siap dikirim&quot; }
- Tangani status response: 'queued' menandakan pesan telah masuk antrean pengiriman server.`,
                 hermes: `Definisikan skill baru untuk Hermes Agent di folder skills/pesanvo/SKILL.md:
name: pesanvo
description: Kirim pesan WhatsApp notifikasi dan dokumen via Pesanvo Gateway API.

Instruksi teknis untuk agent:
- Base URL: https://wa.pesanvo.id/api/v1
- Method: POST /messages/text
- Headers:
    Content-Type: application/json
    X-Api-Key: \${PESANVO_KEY}
- Parameter fungsi send_whatsapp(to, message):
    to: nomor WhatsApp tujuan (format 08 atau 62)
    message: teks pesan WhatsApp yang dikirim
- Agent harus mengecek apakah API key tersedia sebelum memanggil HTTP POST.
- Kembalikan ID pesan ('id') dan status ('queued') saat pengiriman sukses.`,
                 openclaw: `Konfigurasikan OpenClaw (Clawdbot) dengan tool pengiriman WhatsApp resmi Pesanvo:
1. Daftarkan tool 'send_whatsapp' di manifest tool OpenClaw:
   - Description: Mengirim notifikasi atau balasan WhatsApp ke pengguna.
   - Endpoint: POST https://wa.pesanvo.id/api/v1/messages/text
   - Header: X-Api-Key: \${env.PESANVO_KEY}
   - Body: { &quot;to&quot;: &quot;<recipient_phone>&quot;, &quot;message&quot;: &quot;<message_text>&quot; }
2. Standarisasi format nomor: normalisasi awalan 08 menjadi 628 secara otomatis.
3. Bila agent mendeteksi perintah pengiriman pesan atau alert sistem, jalankan tool ini dan laporkan queue ID yang diterima.`
             },
             salin() {
                 let text = this.prompts[this.agentAktif];
                 navigator.clipboard.writeText(text);
                 this.disalin = true;
                 setTimeout(() => this.disalin = false, 2000);
             }
         }">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div class="max-w-3xl">
                <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Bangun integrasi WhatsApp lebih cepat dengan AI Agent
                </h2>
                <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 50ms">
                    Arsitektur REST API Pesanvo berbasis JSON murni tanpa dependensi rumit. Cukup berikan prompt spesifikasi kami ke AI Agent favorit Anda, dan biarkan AI menuliskan service integrasinya dalam hitungan detik.
                </p>
            </div>
            <div class="muncul shrink-0" style="--tunda: 70ms">
                <a href="{{ route('ai.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all active:scale-[0.98]">
                    <span>Lihat Lengkap Panduan AI</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
        </div>

        {{-- Pilihan AI Tools / Agent Switcher --}}
        <div class="muncul mt-10 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5" style="--tunda: 80ms">
            {{-- Claude Code --}}
            <button @click="agentAktif = 'claude'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'claude' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#D97757]/15 text-[#D97757] border border-[#D97757]/20 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="#D97757">
                        <path d="m4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Claude Code</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Anthropic CLI</span>
            </button>

            {{-- Cursor --}}
            <button @click="agentAktif = 'cursor'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'cursor' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 border border-zinc-800 dark:border-zinc-200 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Cursor</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">.cursorrules</span>
            </button>

            {{-- Hermes Agent --}}
            <button @click="agentAktif = 'hermes'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'hermes' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-pink-500/15 text-pink-500 border border-pink-500/25 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
                        <path d="M5.938 12.835c.127-.039.285.02.373.143.028.038.036.092.046.14.003.014-.02.033-.04.05-.124-.098-.24-.194-.354-.291-.011-.01-.016-.027-.025-.042zM8.396 9.412c.195-.032.39-.06.588-.05a.54.54 0 01.148.026c.202.071.402.147.601.224.028.01.05.036.075.055l-.013.027a9.203 9.203 0 01-.26-.089c-.115-.038-.213-.077-.315-.098-.25-.05-.25-.046-.292-.014l.574.144c.275.139.55.276.823.417.042.022.09.057.107.098.026.06.063.076.117.072.066-.006.132-.017.213-.027l-.04.086c.051.08.142.02.216.064-.074.13-.247.09-.334.199l.061.074-.12.087c0 .106-.038.168-.306.243l.026.085-.196.042.07.124h-.25l-.007.137c-.081-.01-.161-.018-.244-.027l-.053.123c-.027-.008-.052-.011-.073-.023-.067-.038-.128-.056-.195.006-.019.017-.063.014-.093.008-.026-.006-.05-.029-.07-.042-.11.095-.11.095-.208.003-.057.046-.12.074-.186.011-.063.027-.123-.02-.178-.014-.07.007-.097-.035-.133-.07l-.13.033c-.013-.236-.194-.19-.34-.203.005-.072.05-.092.095-.094a.474.474 0 01.159.022c.164.05.32.12.496.138.203.021.405.029.601-.015.265-.059.52-.149.707-.365.049-.056.083-.127.117-.195.019-.038.02-.084-.02-.116a1.397 1.397 0 00-.382-.217c.024.12-.031.182-.115.221 0 .014-.004.025 0 .03.08.115.084.16-.007.267a1.39 1.39 0 01-.218.211.477.477 0 01-.641-.05 1.36 1.36 0 01-.133-.152c-.078-.107-.076-.108-.033-.236-.165-.08-.128-.226-.104-.364.008-.05.028-.096.049-.163-.04.014-.067.017-.087.032a.897.897 0 00-.316.357c-.007.016-.01.034-.02.047-.012.015-.034.038-.045.035-.02-.006-.037-.027-.05-.045-.008-.012-.007-.032-.012-.057h-.126l.053-.172a14.82 14.82 0 00-.039-.049l.11-.284c-.06.026-.091.044-.124.051-.03.007-.064 0-.095 0 0-.031-.01-.07.004-.092.149-.22.305-.428.593-.476z"/>
                        <path d="M8.06 10.788c-.003-.038-.004-.075.037-.062.016.006.034.048.028.067-.01.04-.038.032-.064-.005z"/>
                        <path clip-rule="evenodd" d="M11.981.009c.226-.012.453-.011.679 0 .247.01.495.024.74.062.401.064.798.157 1.19.273.463.138.92.299 1.356.511a7.31 7.31 0 012.948 2.642c.292.469.536.963.739 1.479.219.556.446 1.11.623 1.683.204.654.329 1.326.458 1.997.097.504.182 1.01.29 1.511.156.722.329 1.44.494 2.16.186.812.4 1.615.63 2.415.102.355.193.713.282 1.072.11.436.202.876.254 1.323.031.278.066.557.073.837a7.56 7.56 0 01-.017.88c-.037.413-.1.818-.226 1.212a5.017 5.017 0 01-.915 1.649l-.13.156.018.023c.043-.023.088-.041.127-.068.2-.138.373-.307.531-.49.4-.46.721-.973.975-1.529a3.59 3.59 0 00.325-1.72c-.024-.424-.097-.834-.3-1.213-.013-.027-.015-.06-.03-.121.05.035.082.048.101.072.107.13.22.258.315.398.33.494.46 1.052.486 1.64a3.75 3.75 0 01-.47 1.97c-.36.655-.887 1.14-1.526 1.506-.193.111-.394.21-.595.308-.157.078-.248.211-.318.365a.522.522 0 00-.033.406.359.359 0 01.013.139c-.005.077-.077.155-.14.162-.054.006-.125-.043-.15-.116a1.206 1.206 0 01-.06-.233c-.04-.314-.155-.6-.308-.87a3.906 3.906 0 00-.73-.91 2.129 2.129 0 00-.897-.524 4.093 4.093 0 00-.692-.131c-.075-.008-.15-.04-.22.01.18.06.363.11.538.18.434.173.82.43 1.18.728.308.255.58.543.794.884.098.155.186.315.227.496.027.123.042.25.067.375.013.062-.002.109-.053.144-.047.033-.122.034-.163-.01a.455.455 0 01-.08-.14c-.03-.073-.038-.159-.078-.225a7.314 7.314 0 00-1.423-1.664c-.16-.137-.329-.26-.537-.323-.376-.114-.753-.203-1.15-.154-.213.025-.427.032-.64.053a1.6 1.6 0 00-.736.278 5.14 5.14 0 00-.834.72c-.329.342-.642.699-.955 1.055-.136.155-.264.319-.314.531a5.227 5.227 0 00-.012.051.096.096 0 01-.09.076h-.31c-.046 0-.082-.048-.072-.094.023-.108.045-.216.07-.324.075-.325.19-.635.368-.917.024-.039.04-.088.104-.08l.01.049.027.077c.28-.435.571-.834.996-1.135.283-.204.584-.378.89-.55a.196.196 0 00-.098-.002c-.162.043-.325.084-.485.134-.402.124-.764.33-1.11.566-.147.1-.298.193-.414.333a7.314 7.314 0 00-1.07 1.767.845.845 0 00-.04.12.075.075 0 01-.072.056h-.494c-.04 0-.062-.051-.036-.082.123-.14.246-.282.377-.415.275-.281.58-.532.777-.884.027-.048.063-.09.095-.135.238-.333.54-.607.818-.902.082-.086.175-.16.26-.24.029-.027.053-.057.079-.085l-.018-.025-.135.041c-.034.017-.07.031-.102.05-.248.144-.494.292-.743.433-.408.23-.825.439-1.209.711-.281.2-.591.358-.889.533-.02.012-.044.015-.08.028-.015-.135.143-.201.108-.336-.033.014-.064.02-.085.038-.111.096-.227.19-.328.296-.148.157-.284.325-.425.488-.125.143-.25.286-.373.431A.153.153 0 019.89 24H8.762a.316.316 0 00.016-.042c.028-.09.085-.172.083-.28-.091-.018-.162.001-.212.077a4.45 4.45 0 00-.136.215c-.01.016-.024.03-.042.03h-.093c-.019 0-.029-.022-.017-.037.071-.088.14-.178.209-.268.001-.002-.006-.012-.012-.024-.014.004-.03.006-.045.013-.176.09-.352.181-.527.274a.363.363 0 01-.168.042H5.202c-.026 0-.039-.036-.019-.053.21-.178.402-.374.558-.605.335-.496.538-1.047.667-1.629.004-.02-.003-.043-.006-.091-.037.048-.059.072-.076.1a1.943 1.943 0 01-.334.415c-.28.258-.59.448-.983.464-.297.012-.588 0-.865-.127-.46-.21-.722-.57-.794-1.072-.025-.17-.017-.171-.182-.219A3.513 3.513 0 011.97 20.6a2.286 2.286 0 01-.808-1.13 3.569 3.569 0 01-.16-1.245c.002-.034.016-.067.024-.1.032.023.046.043.05.066.033.153.059.308.096.46.086.355.257.664.516.92.258.256.571.419.91.532.358.118.717.138 1.07-.016a1.89 1.89 0 00.621-.452c.328-.348.533-.76.648-1.223.009-.034.005-.071.007-.11-.015.006-.026.006-.03.011-.031.05-.064.1-.093.152-.284.502-.679.887-1.196 1.135-.351.17-.718.255-1.11.159a1.607 1.607 0 01-.971-.64 2.006 2.006 0 01-.368-.924 2.903 2.903 0 01.02-.886c.05-.439.466-1.17.742-1.271-.02.063-.035.112-.053.16-.043.116-.097.227-.13.345a1.901 1.901 0 00-.05.82c.033.212.09.416.204.6.147.236.346.407.62.465.11.023.225.014.338.018a.576.576 0 00.386-.131c.164-.128.282-.292.366-.481.168-.375.24-.777.309-1.179.05-.296.093-.594.133-.893.039-.281.071-.563.104-.845.026-.232.048-.464.074-.696.024-.228.052-.455.076-.683.024-.227.047-.455.069-.683.013-.14.022-.28.034-.42l.037-.417c.022-.25.041-.5.065-.748.008-.082-.02-.132-.09-.177a2.46 2.46 0 01-.492-.418c-.1-.109-.188-.228-.282-.342-.035-.042-.056-.097-.116-.118a2.084 2.084 0 00.275.597c.06.092.131.176.196.265.063.086.182.115.234.226-.028.003-.046.01-.06.006a4.74 4.74 0 01-.22-.057 2.71 2.71 0 01-1.287-.819c-.435-.487-.656-1.076-.71-1.723a5.206 5.206 0 01.014-1.06c.072-.602.22-1.186.45-1.745.155-.376.338-.741.526-1.102.205-.393.466-.75.765-1.076.512-.559 1.104-1.024 1.726-1.448.717-.49 1.478-.898 2.277-1.233C8.244.828 8.767.632 9.31.494c.655-.166 1.31-.33 1.982-.415.229-.03.458-.058.688-.07z"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Hermes</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">SKILL.md</span>
            </button>

            {{-- OpenClaw --}}
            <button @click="agentAktif = 'openclaw'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'openclaw' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-500/15 text-red-500 border border-red-500/25 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2.568c-6.33 0-9.495 5.275-9.495 9.495 0 4.22 3.165 8.44 6.33 9.494v2.11h2.11v-2.11s1.055.422 2.11 0v2.11h2.11v-2.11c3.165-1.055 6.33-5.274 6.33-9.494S18.33 2.568 12 2.568z" fill="url(#lp-claw-grad)"/>
                        <path d="M3.56 9.953C.396 8.898-.66 11.008.396 13.118c1.055 2.11 3.164 1.055 4.22-1.055.632-1.477 0-2.11-1.056-2.11z" fill="url(#lp-claw-grad)"/>
                        <path d="M20.44 9.953c3.164-1.055 4.22 1.055 3.164 3.165-1.055 2.11-3.164 1.055-4.22-1.055-.632-1.477 0-2.11 1.056-2.11z" fill="url(#lp-claw-grad)"/>
                        <path d="M5.507 1.875c.476-.285 1.036-.233 1.615.037.577.27 1.223.774 1.937 1.488a.316.316 0 01-.447.447c-.693-.693-1.279-1.138-1.757-1.361-.475-.222-.795-.205-1.022-.069a.317.317 0 01-.326-.542zM16.877 1.913c.58-.27 1.14-.323 1.616-.038a.317.317 0 01-.326.542c-.227-.136-.547-.153-1.022.069-.478.223-1.064.668-1.756 1.361a.316.316 0 11-.448-.447c.714-.714 1.36-1.218 1.936-1.487z" fill="#FF4D4D"/>
                        <path d="M8.835 9.109a1.266 1.266 0 100-2.532 1.266 1.266 0 000 2.532zM15.165 9.109a1.266 1.266 0 100-2.532 1.266 1.266 0 000 2.532z" fill="#050810"/>
                        <path d="M9.046 8.16a.527.527 0 100-1.056.527.527 0 000 1.055zM15.376 8.16a.527.527 0 100-1.055.527.527 0 000 1.054z" fill="#00E5CC"/>
                        <defs>
                            <linearGradient id="lp-claw-grad" x1="-.659" x2="27.023" y1=".458" y2="22.855" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#FF4D4D"/>
                                <stop offset="1" stop-color="#991B1B"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">OpenClaw</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">AI Employee</span>
            </button>

            {{-- Antigravity --}}
            <button @click="agentAktif = 'antigravity'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'antigravity' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <mask height="23" id="agy-brand-mask" maskUnits="userSpaceOnUse" width="24" x="0" y="1">
                            <path d="M21.751 22.607c1.34 1.005 3.35.335 1.508-1.508C17.73 15.74 18.904 1 12.037 1 5.17 1 6.342 15.74.815 21.1c-2.01 2.009.167 2.511 1.507 1.506 5.192-3.517 4.857-9.714 9.715-9.714 4.857 0 4.522 6.197 9.714 9.715z" fill="#fff"/>
                        </mask>
                        <g mask="url(#agy-brand-mask)">
                            <g filter="url(#agy-b1)"><path d="M-1.018-3.992c-.408 3.591 2.686 6.89 6.91 7.37 4.225.48 7.98-2.043 8.387-5.633.408-3.59-2.686-6.89-6.91-7.37-4.225-.479-7.98 2.043-8.387 5.633z" fill="#FFE432"/></g>
                            <g filter="url(#agy-b2)"><path d="M15.269 7.747c1.058 4.557 5.691 7.374 10.348 6.293 4.657-1.082 7.575-5.653 6.516-10.21-1.058-4.556-5.691-7.374-10.348-6.292-4.657 1.082-7.575 5.653-6.516 10.21z" fill="#FC413D"/></g>
                            <g filter="url(#agy-b3)"><path d="M-12.443 10.804c1.338 4.703 7.36 7.11 13.453 5.378 6.092-1.733 9.947-6.95 8.61-11.652C8.282-.173 2.26-2.58-3.833-.848-9.925.884-13.78 6.1-12.443 10.804z" fill="#00B95C"/></g>
                            <g filter="url(#agy-b4)"><path d="M-7.608 14.703c3.352 3.424 9.126 3.208 12.896-.483 3.77-3.69 4.108-9.459.756-12.883C2.69-2.087-3.083-1.871-6.853 1.82c-3.77 3.69-4.108 9.458-.755 12.883z" fill="#00B95C"/></g>
                            <g filter="url(#agy-b5)"><path d="M9.932 27.617c1.04 4.482 5.384 7.303 9.7 6.3 4.316-1.002 6.971-5.448 5.93-9.93-1.04-4.483-5.384-7.304-9.7-6.301-4.316 1.002-6.971 5.448-5.93 9.93z" fill="#3186FF"/></g>
                            <g filter="url(#agy-b6)"><path d="M2.572-8.185C.392-3.329 2.778 2.472 7.9 4.771c5.122 2.3 11.042.227 13.222-4.63 2.18-4.855-.205-10.656-5.327-12.955-5.122-2.3-11.042-.227-13.222 4.63z" fill="#FBBC04"/></g>
                            <g filter="url(#agy-b7)"><path d="M-3.267 38.686c-5.277-2.072 3.742-19.117 5.984-24.83 2.243-5.712 8.34-8.664 13.616-6.592 5.278 2.071 11.533 13.482 9.29 19.195-2.242 5.713-23.613 14.298-28.89 12.227z" fill="#3186FF"/></g>
                            <g filter="url(#agy-b8)"><path d="M28.71 17.471c-1.413 1.649-5.1.808-8.236-1.878-3.135-2.687-4.531-6.201-3.118-7.85 1.412-1.649 5.1-.808 8.235 1.878s4.532 6.2 3.119 7.85z" fill="#749BFF"/></g>
                            <g filter="url(#agy-b9)"><path d="M18.163 9.077c5.81 3.93 12.502 4.19 14.946.577 2.443-3.612-.287-9.727-6.098-13.658-5.81-3.931-12.502-4.19-14.946-.577-2.443 3.612.287 9.727 6.098 13.658z" fill="#FC413D"/></g>
                            <g filter="url(#agy-b10)"><path d="M-.915 2.684c-1.44 3.473-.97 6.967 1.05 7.804 2.02.837 4.824-1.3 6.264-4.772 1.44-3.473.97-6.967-1.05-7.804-2.02-.837-4.824 1.3-6.264 4.772z" fill="#FFEE48"/></g>
                        </g>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Antigravity</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Studio</span>
            </button>

            {{-- OpenCode --}}
            <button @click="agentAktif = 'opencode'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'opencode' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-zinc-900 text-zinc-100 dark:bg-zinc-800 dark:text-white border border-zinc-700/60 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 512 512" fill="none">
                        <path d="M320 224V352H192V224H320Z" fill="#71717A"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M384 416H128V96H384V416ZM320 160H192V352H320V160Z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">OpenCode</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Terminal</span>
            </button>

            {{-- OpenAI Codex --}}
            <button @click="agentAktif = 'codex'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'codex' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#6366F1]/10 dark:bg-[#6366F1]/20 border border-[#6366F1]/25 shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd" d="M8.086.457a6.105 6.105 0 013.046-.415c1.333.153 2.521.72 3.564 1.7a.117.117 0 00.107.029c1.408-.346 2.762-.224 4.061.366l.063.03.154.076c1.357.703 2.33 1.77 2.918 3.198.278.679.418 1.388.421 2.126a5.655 5.655 0 01-.18 1.631.167.167 0 00.04.155 5.982 5.982 0 011.578 2.891c.385 1.901-.01 3.615-1.183 5.14l-.182.22a6.063 6.063 0 01-2.934 1.851.162.162 0 00-.108.102c-.255.736-.511 1.364-.987 1.992-1.199 1.582-2.962 2.462-4.948 2.451-1.583-.008-2.986-.587-4.21-1.736a.145.145 0 00-.14-.032c-.518.167-1.04.191-1.604.185a5.924 5.924 0 01-2.595-.622 6.058 6.058 0 01-2.146-1.781c-.203-.269-.404-.522-.551-.821a7.74 7.74 0 01-.495-1.283 6.11 6.11 0 01-.017-3.064.166.166 0 00.008-.074.115.115 0 00-.037-.064 5.958 5.958 0 01-1.38-2.202 5.196 5.196 0 01-.333-1.589 6.915 6.915 0 01.188-2.132c.45-1.484 1.309-2.648 2.577-3.493.282-.188.55-.334.802-.438.286-.12.573-.22.861-.304a.129.129 0 00.087-.087A6.016 6.016 0 015.635 2.31C6.315 1.464 7.132.846 8.086.457zm-.804 7.85a.848.848 0 00-1.473.842l1.694 2.965-1.688 2.848a.849.849 0 001.46.864l1.94-3.272a.849.849 0 00.007-.854l-1.94-3.393zm5.446 6.24a.849.849 0 000 1.695h4.848a.849.849 0 000-1.696h-4.848z" fill="url(#codex-brand-grad)"/>
                        <defs>
                            <linearGradient id="codex-brand-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#6366F1"/>
                                <stop offset="100%" stop-color="#A855F7"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Codex</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">OpenAI</span>
            </button>

            {{-- Windsurf --}}
            <button @click="agentAktif = 'windsurf'"
                    type="button"
                    class="group flex flex-col items-start p-2.5 sm:p-3 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'windsurf' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#09B6A2] text-[#0B100F] shadow-xs mb-2 transition-transform group-hover:scale-105">
                    <svg class="h-4 w-4" viewBox="0 0 1024 1024" fill="currentColor">
                        <path d="M897.246 286.869H889.819C850.735 286.808 819.017 318.46 819.017 357.539V515.589C819.017 547.15 792.93 572.716 761.882 572.716C743.436 572.716 725.02 563.433 714.093 547.85L552.673 317.304C539.28 298.16 517.486 286.747 493.895 286.747C457.094 286.747 423.976 318.034 423.976 356.657V515.619C423.976 547.181 398.103 572.746 366.842 572.746C348.335 572.746 329.949 563.463 319.021 547.881L138.395 289.882C134.316 284.038 125.154 286.93 125.154 294.052V431.892C125.154 438.862 127.285 445.619 131.272 451.34L309.037 705.2C319.539 720.204 335.033 731.344 352.9 735.392C397.616 745.557 438.77 711.135 438.77 667.278V508.406C438.77 476.845 464.339 451.279 495.904 451.279H495.995C515.02 451.279 532.857 460.562 543.785 476.145L705.235 706.661C718.659 725.835 739.327 737.218 763.983 737.218C801.606 737.218 833.841 705.9 833.841 667.308V508.376C833.841 476.815 859.41 451.249 890.975 451.249H897.276C901.233 451.249 904.43 448.053 904.43 444.097V294.021C904.43 290.065 901.233 286.869 897.276 286.869H897.246Z"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-foreground truncate w-full">Windsurf</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Cascade</span>
            </button>
        </div>

        {{-- Jendela Terminal Prompt Interaktif --}}
        <div class="muncul mt-6 rounded-2xl border border-border/80 bg-[var(--code-chrome)] shadow-xl overflow-hidden" style="--tunda: 140ms">
            <div class="flex items-center justify-between border-b border-white/10 bg-black/40 px-3.5 sm:px-4 py-3 gap-2">
                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ff5f56]"></span>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ffbd2e]"></span>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#27c93f]"></span>
                    <span class="ml-1 sm:ml-2 font-mono text-xs text-[var(--code-muted)] truncate"
                          x-text="'prompt-' + agentAktif + (agentAktif === 'cursor' ? '.cursorrules' : (agentAktif === 'claude' ? '-CLAUDE.md' : (agentAktif === 'hermes' ? '-SKILL.md' : (agentAktif === 'openclaw' ? '.json' : '.txt'))))"></span>
                </div>

                <button type="button"
                        @click="salin()"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-white/15 bg-white/10 px-2.5 sm:px-3 py-1 text-xs font-semibold text-white hover:bg-white/20 transition-all active:scale-[0.98]">
                    <svg x-show="!disalin" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <svg x-show="disalin" x-cloak class="h-3.5 w-3.5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span x-text="disalin ? 'Prompt Tersalin!' : 'Salin Prompt'">Salin Prompt</span>
                </button>
            </div>

            <pre class="overflow-x-auto p-4 sm:p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)] whitespace-pre-wrap break-words selection:bg-primary/30" x-text="prompts[agentAktif]"></pre>
        </div>

        {{-- Catatan Developer & Tautan ke Docs --}}
        <div class="muncul mt-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 text-xs sm:text-sm text-muted-foreground" style="--tunda: 170ms">
            <div class="flex items-start sm:items-center gap-2 min-w-0">
                <span class="text-primary font-bold shrink-0">💡 Tip:</span>
                <span class="break-words">Selalu simpan API Key di file <code class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded">.env</code> dan jangan pernah hardcode ke dalam berkas repositori.</span>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('ai.index') }}" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
                    <span>Halaman Khusus AI (Katalog & SDK)</span>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
                <span class="text-border hidden sm:inline">&bull;</span>
                <a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="inline-flex items-center gap-1.5 text-xs sm:text-sm text-muted-foreground hover:text-foreground hover:underline">
                    <span>Dokumentasi</span>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ===================== Penutup ===================== --}}
<section class="bg-primary text-primary-foreground">
    <div class="mx-auto max-w-4xl px-6 py-20 text-center sm:px-8 lg:py-24">
        <h2 class="muncul text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight tracking-tight">
            Kirim pesan pertama Anda hari ini
        </h2>
        <p class="muncul mx-auto mt-4 max-w-lg text-xs sm:text-sm leading-relaxed text-primary-foreground/85" style="--tunda: 60ms">
            Buat akun, tautkan satu nomor, dan sambungkan ke aplikasi Anda. Menautkan nomor pertama biasanya di bawah satu menit.
        </p>
        <div class="muncul mt-8 flex flex-wrap justify-center gap-3" style="--tunda: 110ms">
            <a href="{{ route('register') }}"
               class="rounded-lg bg-primary-foreground px-5 py-2.5 text-xs sm:text-sm font-semibold text-primary transition hover:opacity-90 shadow-xs">
                Buat akun
            </a>
            <a href="{{ route('docs.show', 'mulai-cepat') }}"
               class="rounded-lg border border-primary-foreground/30 px-5 py-2.5 text-xs sm:text-sm font-medium transition hover:bg-primary-foreground/10">
                Baca panduan mulai cepat
            </a>
        </div>
    </div>
</section>

@include('partials.footer')

<script>
    (function () {
        var blok = document.querySelectorAll('.muncul');

        if (! document.documentElement.classList.contains('animasi-muncul')) {
            blok.forEach(function (el) { el.classList.add('terlihat'); });
            return;
        }

        var pengamat = new IntersectionObserver(function (entri) {
            entri.forEach(function (e) {
                if (! e.isIntersecting) return;
                e.target.classList.add('terlihat');
                pengamat.unobserve(e.target);
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });

        blok.forEach(function (el) { pengamat.observe(el); });
    })();
</script>

</body>
</html>
