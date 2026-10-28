@php
    $tagihan = $pendingInvoice ?? ($currentWorkspace ?? null)?->invoices()
        ?->where('status', 'pending')
        ->where(function ($q) {
            $q->whereNull('due_at')->orWhere('due_at', '>', now());
        })
        ->latest()
        ->first();
@endphp

@if ($tagihan && ! request()->routeIs('billing.*'))
    @php
        $waAdminNomor = \App\Support\KontakWhatsApp::nomor();
        $dikonfirmasi = $tagihan->isAwaitingVerification();
        $pesanWa = rawurlencode(
            "Halo Admin Pesanvo, saya ingin konfirmasi transaksi tagihan *" . $tagihan->number . "* "
            ."(Paket " . $tagihan->plan()->name() . ") sebesar *Rp "
            . number_format($tagihan->total, 0, ',', '.') . "*. "
            . ($dikonfirmasi ? "Transaksi saya masih pending / menunggu verifikasi. Mohon dibantu konfirmasinya." : "Status tagihan masih pending. Mohon dibantu.")
        );
        $waUrl = "https://wa.me/{$waAdminNomor}?text={$pesanWa}";
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 sm:p-5 shadow-xs transition-all">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3.5 min-w-0">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/20 text-amber-900 dark:text-amber-200">
                    @if ($dikonfirmasi)
                        <svg class="h-5 w-5 animate-spin [animation-duration:3s]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/25 px-2.5 py-0.5 text-[11px] font-bold text-amber-950 dark:text-amber-200 uppercase tracking-wider">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            {{ $dikonfirmasi ? 'Verifikasi Pending' : 'Transaksi Pending' }}
                        </span>
                        <span class="font-mono text-xs font-semibold text-foreground">{{ $tagihan->number }}</span>
                    </div>

                    <p class="mt-1.5 text-sm font-bold text-foreground">
                        @if ($dikonfirmasi)
                            Transaksi Anda masih pending dalam proses verifikasi.
                        @else
                            Anda memiliki transaksi pending yang menunggu pembayaran.
                        @endif
                    </p>

                    <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">
                        Paket <strong>{{ $tagihan->plan()->name() }}</strong> ({{ $tagihan->periodLabel() }}) &middot; Total <strong>Rp {{ number_format($tagihan->total, 0, ',', '.') }}</strong>
                        @if ($dikonfirmasi)
                            &mdash; Jika belum aktif, silakan hubungi admin untuk konfirmasi langsung.
                        @else
                            &mdash; Silakan selesaikan pembayaran atau hubungi admin jika butuh bantuan konfirmasi.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Tombol Tindakan --}}
            <div class="flex flex-wrap items-center gap-2.5 sm:shrink-0">
                <a href="{{ $waUrl }}" target="_blank"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98]">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>Hubungi Admin WA</span>
                </a>

                @if ($dikonfirmasi)
                    <a href="{{ route('billing.verifying', $tagihan->id) }}"
                       class="inline-flex items-center justify-center gap-1 rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90">
                        <span>Cek Status</span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @else
                    <a href="{{ route('billing.invoice', $tagihan->id) }}"
                       class="inline-flex items-center justify-center gap-1 rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90">
                        <span>Lihat Tagihan</span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
