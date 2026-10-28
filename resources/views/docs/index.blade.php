@extends('layouts.docs')

@section('title', 'Dokumentasi')
@section('description', 'Dokumentasi lengkap Pesanvo Gateway: instalasi, arsitektur, referensi API, dan panduan operasional.')

@section('content')
    <div class="space-y-12">
        {{-- ===================== HERO DEVELOPER HUB ===================== --}}
        <div class="relative overflow-hidden rounded-3xl border border-border/80 bg-gradient-to-br from-primary/10 via-card to-background p-6 sm:p-10 shadow-sm">
            {{-- Radial ambient glow background --}}
            <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-primary/15 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-16 h-64 w-64 rounded-full bg-primary/10 blur-3xl"></div>

            <div class="relative z-10 max-w-3xl">
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
                    Dokumentasi Pesanvo Gateway
                </h1>
                
                <p class="mt-3 text-base text-muted-foreground leading-relaxed sm:text-lg">
                    Panduan resmi integrasi WhatsApp API ke sistem aplikasi Anda — mulai dari menautkan nomor pertama, otomatisasi notifikasi, webhook real-time, hingga pengiriman broadcast berskala besar.
                </p>

                {{-- Interactive Search Trigger Button --}}
                <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <button type="button" 
                            @click="searchModal = true"
                            class="flex flex-1 items-center gap-3 rounded-2xl border border-border bg-background/90 backdrop-blur-md px-4 py-3 text-sm text-muted-foreground shadow-sm transition hover:border-primary/50 hover:bg-background focus:outline-none">
                        <i class="bi bi-search text-base text-primary"></i>
                        <span class="flex-1 text-left">Cari dokumentasi atau endpoint (mis: webhook, kirim pesan, api key)...</span>
                        <kbd class="hidden sm:inline-block rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Ctrl + K</kbd>
                    </button>
                </div>

                {{-- Feature Badges --}}
                <div class="mt-6 flex flex-wrap items-center gap-2.5 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card/60 px-2.5 py-1">
                        <i class="bi bi-lightning-charge-fill text-amber-500"></i>
                        <span>REST API v1.0 Siap Pakai</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card/60 px-2.5 py-1">
                        <i class="bi bi-broadcast text-emerald-500"></i>
                        <span>Webhook Real-time</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card/60 px-2.5 py-1">
                        <i class="bi bi-phone-fill text-blue-500"></i>
                        <span>Multi-Sesi &amp; Multi-Device</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card/60 px-2.5 py-1">
                        <i class="bi bi-shield-check text-purple-500"></i>
                        <span>Proteksi Anti-Blokir</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- ===================== QUICK-START CARDS ===================== --}}
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground">
                    Langkah Cepat &amp; Topik Utama
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $quickstarts = [
                        [
                            'slug' => 'mulai-cepat',
                            'title' => 'Mulai Cepat',
                            'badge' => '5 Menit',
                            'icon' => 'bi-rocket-takeoff-fill',
                            'color' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                            'desc' => 'Daftar akun, tautkan nomor WhatsApp, dan kirim pesan pertama Anda dalam 5 menit.',
                        ],
                        [
                            'slug' => 'referensi-api',
                            'title' => 'Referensi API',
                            'badge' => 'REST v1',
                            'icon' => 'bi-code-slash',
                            'color' => 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/20',
                            'desc' => 'Spesifikasi endpoint REST API lengkap: kirim teks, media, dokumen, tombol & status.',
                        ],
                        [
                            'slug' => 'webhook',
                            'title' => 'Webhook Event',
                            'badge' => 'Real-time',
                            'icon' => 'bi-broadcast',
                            'color' => 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20',
                            'desc' => 'Integrasikan callback pesan masuk dan status pesan (sent, delivered, read) ke server Anda.',
                        ],
                        [
                            'slug' => 'praktik-baik',
                            'title' => 'Praktik Baik',
                            'badge' => 'Anti-Blokir',
                            'icon' => 'bi-shield-check',
                            'color' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20',
                            'desc' => 'Strategi jeda pengiriman broadcast, warm-up nomor, dan template agar nomor bisnis aman.',
                        ],
                    ];
                @endphp

                @foreach ($quickstarts as $item)
                    <a href="{{ route('docs.show', $item['slug']) }}"
                       class="group relative flex flex-col justify-between rounded-2xl border border-border bg-card p-5 transition-all duration-200 hover:-translate-y-1 hover:border-primary/50 hover:shadow-lg shadow-2xs">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="grid h-10 w-10 place-items-center rounded-xl border {{ $item['color'] }}">
                                    <i class="bi {{ $item['icon'] }} text-lg"></i>
                                </div>
                                <span class="rounded-full border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                    {{ $item['badge'] }}
                                </span>
                            </div>
                            <h3 class="mt-4 font-bold text-foreground group-hover:text-primary transition">
                                {{ $item['title'] }}
                            </h3>
                            <p class="mt-1.5 text-xs text-muted-foreground leading-relaxed">
                                {{ $item['desc'] }}
                            </p>
                        </div>

                        <div class="mt-5 flex items-center gap-1.5 text-xs font-semibold text-primary">
                            <span>Buka Panduan</span>
                            <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ===================== KATALOG DOKUMEN BERDASARKAN KATEGORI ===================== --}}
        <div class="space-y-10">
            @php
                $groupMeta = [
                    'Mulai' => [
                        'icon' => 'bi-compass-fill',
                        'desc' => 'Panduan dasar penggunaan gateway dan manajemen sesi WhatsApp.',
                    ],
                    'Untuk Developer' => [
                        'icon' => 'bi-terminal-fill',
                        'desc' => 'Spesifikasi endpoint REST API, autentikasi API key, webhook, dan contoh kode.',
                    ],
                    'Panduan' => [
                        'icon' => 'bi-journal-bookmark-fill',
                        'desc' => 'Ketentuan batas kuota, tagihan, keamanan akun, dan tanya-jawab umum.',
                    ],
                ];
            @endphp

            @foreach ($catalogue as $group => $pages)
                <section class="space-y-4">
                    <div class="flex items-center justify-between border-b border-border/80 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="grid h-7 w-7 place-items-center rounded-lg bg-primary/10 text-primary">
                                <i class="bi {{ $groupMeta[$group]['icon'] ?? 'bi-folder-fill' }} text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-foreground">{{ $group }}</h2>
                                <p class="text-xs text-muted-foreground">{{ $groupMeta[$group]['desc'] ?? '' }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ count($pages) }} artikel
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($pages as $slug => $page)
                            <a href="{{ route('docs.show', $slug) }}"
                               class="group flex items-start justify-between rounded-xl border border-border bg-card/60 p-4 transition-all hover:border-primary/50 hover:bg-card hover:shadow-2xs">
                                <div class="min-w-0 pr-3">
                                    <h3 class="text-sm font-semibold text-foreground group-hover:text-primary transition">
                                        {{ $page['title'] }}
                                    </h3>
                                    <p class="mt-1 text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                                        {{ $page['summary'] }}
                                    </p>
                                </div>
                                <div class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition">
                                    <i class="bi bi-chevron-right text-xs"></i>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- ===================== CODE SHOWCASE / INTERACTIVE TERMINAL ===================== --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-[#0a101d] p-6 shadow-xl text-slate-100">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-rose-500/80"></span>
                        <span class="h-3 w-3 rounded-full bg-amber-500/80"></span>
                        <span class="h-3 w-3 rounded-full bg-emerald-500/80"></span>
                    </div>
                    <span class="font-mono text-xs text-slate-400">cURL — Kirim Pesan WhatsApp Pertama Anda</span>
                </div>
                <button type="button"
                        onclick="navigator.clipboard.writeText(this.getAttribute('data-code')); this.innerHTML = '<i class=\'bi bi-check2 text-emerald-400\'></i> <span class=\'text-emerald-400\'>Tersalin!</span>'; setTimeout(() => this.innerHTML = '<i class=\'bi bi-clipboard\'></i> <span>Salin cURL</span>', 2000)"
                        data-code="curl -X POST {{ config('app.url') }}/api/v1/messages/text \
  -H &quot;X-Api-Key: vwa_xxxxxxxx.xxxxxxxx&quot; \
  -H &quot;Content-Type: application/json&quot; \
  -d '{&quot;to&quot;:&quot;081234567890&quot;,&quot;message&quot;:&quot;Halo dari Pesanvo Gateway!&quot;}'"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800/80 px-2.5 py-1 text-xs font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white cursor-pointer">
                    <i class="bi bi-clipboard"></i>
                    <span>Salin cURL</span>
                </button>
            </div>

            <p class="mt-4 text-xs text-slate-400">
                Hanya butuh satu permintaan HTTP untuk mengintegrasikan WhatsApp ke backend bahasa pemrograman apa pun.
            </p>

            <pre class="mt-3 overflow-x-auto rounded-xl bg-[#070b14] p-4 text-[13px] leading-relaxed font-mono text-emerald-400 border border-slate-800/80"><code>curl -X POST {{ config('app.url') }}/api/v1/messages/text \
  -H "X-Api-Key: vwa_xxxxxxxx.xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Halo dari Pesanvo Gateway!"}'</code></pre>

            <div class="mt-5 flex flex-wrap items-center gap-4 text-xs font-semibold">
                <a href="{{ route('docs.show', 'mulai-cepat') }}" class="inline-flex items-center gap-1 text-primary hover:underline">
                    <span>Panduan Mulai Cepat Lengkap</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
                <span class="text-slate-600">&bull;</span>
                <a href="{{ route('docs.show', 'referensi-api') }}" class="inline-flex items-center gap-1 text-primary hover:underline">
                    <span>Eksplorasi Referensi API</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        {{-- ===================== BUTUH BANTUAN CALLOUT ===================== --}}
        <div class="rounded-2xl border border-border/80 bg-card p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 shadow-2xs">
            <div>
                <h3 class="text-base font-bold text-foreground">Butuh Bantuan Integrasi Lebih Lanjut?</h3>
                <p class="mt-1 text-xs sm:text-sm text-muted-foreground max-w-xl leading-relaxed">
                    Tim teknis Pesanvo siap membantu setup gateway, konsultasi arsitektur webhook, hingga pengujian broadcast nomor bisnis Anda.
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('docs.show', 'bantuan') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-sm transition hover:opacity-90 active:scale-95">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Hubungi Dukungan</span>
                </a>
                <a href="{{ route('docs.show', 'faq') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-4 py-2.5 text-xs font-semibold text-foreground transition hover:bg-muted">
                    <span>Lihat FAQ</span>
                </a>
            </div>
        </div>
    </div>
@endsection
