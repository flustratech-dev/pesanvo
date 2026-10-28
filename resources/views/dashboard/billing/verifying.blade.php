@extends('layouts.checkout')
@section('title', 'Verifikasi Pembayaran ' . $invoice->number)

@section('content')
    @php
        $waAdminNomor = $adminWa ?? \App\Support\KontakWhatsApp::nomor();
        $waPesan = rawurlencode(
            "Halo Admin Pesanvo, saya sudah melakukan pembayaran untuk tagihan *{$invoice->number}* "
            ."(Paket {$plan->name()} · {$invoice->periodLabel()}) sebesar *Rp "
            .number_format($invoice->total, 0, ',', '.')
            ."*. Mohon bantuannya untuk verifikasi. Terima kasih."
        );
        $waUrl = "https://wa.me/{$waAdminNomor}?text={$waPesan}";
        $detikMulai = isset($sisaDetik) ? (int) $sisaDetik : 600;
    @endphp

    <div class="mx-auto max-w-xl py-6 sm:py-12"
         x-data="menungguVerifikasi('{{ route('billing.status', $invoice->id) }}', {{ $detikMulai }})"
         x-init="mulai()">
        <div class="overflow-hidden rounded-3xl border border-border bg-card p-6 text-center shadow-xl sm:p-10 transition-all">

            {{-- Animasi Radar / Scanner Gateway --}}
            <div class="relative mx-auto flex h-20 w-20 items-center justify-center">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary/20 duration-1000"></span>
                <span class="absolute inline-flex h-16 w-16 animate-pulse rounded-full bg-primary/10"></span>
                <div class="relative grid h-14 w-14 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-md ring-4 ring-primary/20">
                    <svg class="h-7 w-7 animate-spin [animation-duration:3s]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
            </div>

            {{-- Judul & Pesan Status --}}
            @if ($invoice->proof_path)
                <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Bukti Anda sudah kami terima
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Tagihan <span class="font-mono font-semibold text-foreground">{{ $invoice->number }}</span> sedang diverifikasi tim kami.
                    Anda tidak perlu mengirim ulang atau membayar lagi.
                </p>
            @else
                <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Memverifikasi Pembayaran
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Sistem kami sedang mencocokkan transaksi untuk tagihan <span class="font-mono font-semibold text-foreground">{{ $invoice->number }}</span>.
                    Mohon jangan menutup halaman ini sampai proses selesai.
                </p>
            @endif

            {{-- Countdown Timer Box (10 Menit) --}}
            <div class="mt-6 rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:p-5 text-center">
                <div class="flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary">
                    <svg class="h-4 w-4 animate-pulse" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span>Estimasi Verifikasi Otomatis</span>
                </div>

                {{-- Tampilan Timer Menit:Detik --}}
                <div class="mt-2 flex items-baseline justify-center gap-1 font-mono text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl tabular-nums">
                    <span x-text="formatMenit">--</span>
                    <span class="text-primary animate-pulse">:</span>
                    <span x-text="formatDetik">--</span>
                </div>

                {{-- Progress Bar --}}
                <div class="mt-3.5 h-2 w-full overflow-hidden rounded-full bg-border/60">
                    <div class="h-full rounded-full bg-primary transition-all duration-1000"
                         :style="'width: ' + progressPersen + '%'"></div>
                </div>

                <div class="mt-3 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                    </span>
                    <span x-text="langkahTeks" class="font-medium">Menghubungkan ke sistem verifikasi…</span>
                </div>
            </div>

            {{-- Rincian Tagihan --}}
            <div class="mt-6 rounded-xl border border-border/80 bg-muted/30 p-4 text-left text-sm">
                <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                    <span>Nomor Tagihan</span>
                    <span class="font-mono font-medium text-foreground">{{ $invoice->number }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                    <span>Paket &amp; Periode</span>
                    <span class="font-medium text-foreground">{{ $plan->name() }} &middot; {{ $invoice->periodLabel() }}</span>
                </div>
                @if ($invoice->unique_code > 0)
                    <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                        <span>Kode Unik Transaksi</span>
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400">
                            {{ str_pad($invoice->unique_code, 3, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>
                @endif
                <div class="mt-2 flex items-center justify-between border-t border-border pt-2 font-medium">
                    <span class="text-foreground">Total Nominal Ditransfer</span>
                    <span class="text-base font-bold text-primary">Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- Kotak Bantuan WhatsApp Admin (+62 823-1828-0376) --}}
            <div class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-left sm:p-5">
                <div class="flex items-start gap-3.5">
                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                            Butuh Konfirmasi Cepat?
                        </h2>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                            Jika Anda sudah mentransfer atau waktu di atas hampir habis, Anda dapat langsung menghubungi WhatsApp Admin untuk konfirmasi instan.
                        </p>
                        <div class="mt-3">
                            <a href="{{ $waUrl }}" target="_blank"
                               class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98]">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                <span>Hubungi WhatsApp Admin (+62 823-1828-0376)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tombol Navigasi Bawah --}}
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-xs font-semibold text-primary-foreground shadow-sm transition hover:opacity-90">
                    Buka Dashboard
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('billing.invoice', $invoice->id) }}"
                   class="inline-flex items-center justify-center rounded-xl border border-border bg-card px-5 py-2.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    Lihat Cara Bayar
                </a>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
<script>
    function menungguVerifikasi(alamat, sisaDetikAwal) {
        return {
            sisa: Math.max(0, parseInt(sisaDetikAwal) || 600),
            totalWaktu: 600,
            jedaTimer: null,
            jedaPoll: null,
            langkahIndex: 0,
            langkahDaftar: [
                'Menghubungkan ke sistem verifikasi…',
                'Mencocokkan mutasi pembayaran…',
                'Memeriksa kode unik transaksi…',
                'Mengaktifkan paket workspace…'
            ],

            get formatMenit() {
                const m = Math.floor(this.sisa / 60);
                return m < 10 ? '0' + m : m;
            },

            get formatDetik() {
                const d = this.sisa % 60;
                return d < 10 ? '0' + d : d;
            },

            get progressPersen() {
                return Math.max(0, Math.min(100, Math.round((this.sisa / this.totalWaktu) * 100)));
            },

            get langkahTeks() {
                if (this.sisa <= 0) {
                    return 'Waktu verifikasi otomatis telah selesai. Silakan hubungi admin di bawah.';
                }
                return this.langkahDaftar[this.langkahIndex % this.langkahDaftar.length];
            },

            mulai() {
                // Timer mundur detik per detik
                this.jedaTimer = setInterval(() => {
                    if (this.sisa > 0) {
                        this.sisa--;
                        if (this.sisa % 4 === 0) {
                            this.langkahIndex++;
                        }
                    }
                }, 1000);

                // Polling status setiap 6 detik
                this.periksa();
                this.jedaPoll = setInterval(() => this.periksa(), 6000);

                // Jika pengguna kembali ke tab, periksa langsung
                document.addEventListener('visibilitychange', () => {
                    if (! document.hidden) this.periksa();
                });
            },

            berhenti() {
                clearInterval(this.jedaTimer);
                clearInterval(this.jedaPoll);
            },

            async periksa() {
                try {
                    const jawab = await fetch(alamat, {
                        headers: { Accept: 'application/json' }
                    });
                    if (! jawab.ok) return;

                    const data = await jawab.json();

                    if (data.lunas) {
                        this.berhenti();
                        // Alihkan langsung ke invoice receipt / dashboard
                        window.location = data.lanjut;
                    }
                } catch (e) {
                    // Kegagalan jaringan sesaat diabaikan, akan diulang pada polling berikutnya
                }
            }
        };
    }
</script>
@endpush
