@extends('layouts.app')
@section('title', 'Program Mitra & Reseller')

@section('content')
    @php
        $isApproved = $referralCode && $referralCode->isApproved();
        $isPending = $referralCode && $referralCode->isPending();
        $isRejected = $referralCode && $referralCode->isRejected();
    @endphp

    <div class="space-y-6" x-data="{
        sembunyi: true,
        toggleSembunyi() { this.sembunyi = !this.sembunyi; },
        disalin: false,
        disalinTautan: false,
        modalTarik: false,
        modalTerms: false
    }">

        {{-- ===================== 1. JIKA BELUM MENDAFTAR / DITOLAK ===================== --}}
        @if (! $referralCode || $isRejected)
            <div class="mx-auto max-w-3xl py-4">
                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                    <div class="border-b border-border bg-primary/5 p-6 text-center sm:p-8">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                        </div>
                        <h2 class="mt-4 text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                            Pendaftaran Program Mitra Pesanvo
                        </h2>
                        <p class="mx-auto mt-2 max-w-lg text-xs sm:text-sm text-muted-foreground leading-relaxed">
                            Lengkapi data rekening bank atau e-wallet Anda untuk penerimaan komisi reseller. Setelah permohonan disetujui oleh admin, kode referal unik 5 digit Anda akan aktif permanen.
                        </p>

                        @if ($isRejected)
                            <div class="mt-5 rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-left text-xs sm:text-sm text-destructive">
                                <p class="font-bold flex items-center gap-1.5">
                                    <i class="bi bi-x-circle-fill"></i> Permohonan Sebelumnya Belum Disetujui
                                </p>
                                <p class="mt-1 leading-relaxed">
                                    Alasan: {{ $referralCode->rejection_reason ?: 'Data rekening belum valid.' }} Silakan periksa kembali data Anda dan kirim ulang permohonan.
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Form Pendaftaran Mitra --}}
                    <div class="p-6 sm:p-8">
                        <form action="{{ route('mitra.apply') }}" method="POST" class="space-y-4">
                            @csrf

                            <div>
                                <label for="bank_account_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                    Nama Pemilik Rekening (Sesuai Buku Tabungan / E-Wallet) <span class="text-destructive">*</span>
                                </label>
                                <input type="text" name="bank_account_name" id="bank_account_name"
                                       value="{{ old('bank_account_name', $referralCode?->bank_account_name ?? auth()->user()->name) }}"
                                       required maxlength="100" placeholder="Contoh: BUDI SANTOSO"
                                       class="w-full rounded-xl border @error('bank_account_name') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                                @error('bank_account_name')
                                    <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="bank_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                        Bank / E-Wallet <span class="text-destructive">*</span>
                                    </label>
                                    <input type="text" name="bank_name" id="bank_name"
                                           value="{{ old('bank_name', $referralCode?->bank_name) }}"
                                           required maxlength="50" placeholder="Misal: BCA / Mandiri / BRI / GoPay / Dana"
                                           class="w-full rounded-xl border @error('bank_name') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                                    @error('bank_name')
                                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="bank_account_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                        Nomor Rekening / E-Wallet <span class="text-destructive">*</span>
                                    </label>
                                    <input type="text" name="bank_account_number" id="bank_account_number"
                                           value="{{ old('bank_account_number', $referralCode?->bank_account_number) }}"
                                           required maxlength="50" placeholder="Contoh: 1234567890"
                                           class="w-full rounded-xl border @error('bank_account_number') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                                    @error('bank_account_number')
                                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="whatsapp_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                    Nomor WhatsApp Aktif <span class="text-destructive">*</span>
                                </label>
                                <input type="text" name="whatsapp_number" id="whatsapp_number"
                                       value="{{ old('whatsapp_number', $referralCode?->whatsapp_number ?? auth()->user()->phone) }}"
                                       required maxlength="30" placeholder="Contoh: 081234567890"
                                       class="w-full rounded-xl border @error('whatsapp_number') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                                <p class="mt-1 text-[11px] text-muted-foreground">
                                    Nomor ini digunakan admin untuk konfirmasi pencairan komisi.
                                </p>
                                @error('whatsapp_number')
                                    <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="notes" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                    Rencana Promosi / Nama Bisnis / Website (Opsional)
                                </label>
                                <textarea name="notes" id="notes" rows="2" maxlength="500"
                                          placeholder="Contoh: Saya pemilik software house di Bandung dengan 10+ klien UMKM"
                                          class="w-full rounded-xl border @error('notes') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">{{ old('notes', $referralCode?->notes) }}</textarea>
                            </div>

                            <div class="pt-1">
                                <label class="flex items-start gap-2.5 cursor-pointer text-xs sm:text-sm text-foreground">
                                    <input type="checkbox" name="terms" value="1" required class="mt-0.5 rounded border-input text-primary focus:ring-primary/20">
                                    <span>
                                        Saya telah membaca dan menyetujui <button type="button" @click="modalTerms = true" class="font-semibold text-primary underline hover:text-primary/80">Syarat &amp; Ketentuan Program Kemitraan Pesanvo</button>.
                                    </span>
                                </label>
                                @error('terms')
                                    <p class="mt-1 text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-3">
                                <button type="submit"
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-8 py-3 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.01] active:scale-[0.98]">
                                    <i class="bi bi-send-fill"></i>
                                    <span>Kirim Permohonan Pendaftaran Mitra</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        {{-- ===================== 2. JIKA MENUNGGU VERIFIKASI ADMIN (PENDING) ===================== --}}
        @elseif ($isPending)
            <div class="mx-auto max-w-2xl py-6">
                <div class="overflow-hidden rounded-2xl border border-amber-500/30 bg-card shadow-xs">
                    <div class="border-b border-border bg-amber-500/10 p-6 text-center sm:p-8">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-600 dark:text-amber-400">
                            <svg class="h-7 w-7 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>
                        </div>
                        <h2 class="mt-4 text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                            Permohonan Sedang Ditinjau Admin
                        </h2>
                        <p class="mx-auto mt-2 max-w-md text-xs sm:text-sm text-muted-foreground leading-relaxed">
                            Terima kasih! Permohonan pendaftaran kemitraan Anda telah masuk antrean verifikasi admin Pesanvo. Kami akan memvalidasi data dalam waktu 1x24 jam kerja.
                        </p>
                    </div>

                    <div class="p-6 sm:p-8 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Data Rekening yang Anda Daftarkan
                            </h3>
                            <button type="button" @click="toggleSembunyi()"
                                    class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                <i :class="sembunyi ? 'bi bi-eye' : 'bi bi-eye-slash'"></i>
                                <span x-text="sembunyi ? 'Lihat Data Sensitif' : 'Sembunyikan'"></span>
                            </button>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/40 p-4 space-y-2.5 text-xs sm:text-sm">
                            <div class="flex justify-between py-1 border-b border-border/60">
                                <span class="text-muted-foreground">Pemilik Rekening:</span>
                                <span class="font-semibold text-foreground">{{ $referralCode->bank_account_name }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-border/60">
                                <span class="text-muted-foreground">Bank / E-Wallet:</span>
                                <span class="font-semibold text-foreground">{{ $referralCode->bank_name }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-border/60">
                                <span class="text-muted-foreground">Nomor Rekening:</span>
                                <span x-show="sembunyi" class="font-mono text-muted-foreground">
                                    {{ substr($referralCode->bank_account_number, 0, 3) . '••••' . substr($referralCode->bank_account_number, -3) }}
                                </span>
                                <span x-show="!sembunyi" x-cloak class="font-mono font-semibold text-foreground">
                                    {{ $referralCode->bank_account_number }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-muted-foreground">WhatsApp Konfirmasi:</span>
                                <span x-show="sembunyi" class="font-mono text-muted-foreground">
                                    {{ substr($referralCode->whatsapp_number, 0, 4) . '••••' . substr($referralCode->whatsapp_number, -3) }}
                                </span>
                                <span x-show="!sembunyi" x-cloak class="font-mono font-semibold text-foreground">
                                    {{ $referralCode->whatsapp_number }}
                                </span>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground text-center pt-2">
                            Jika ingin mengganti nomor rekening sebelum di-ACC, silakan hubungi tim kami via WhatsApp resmi Pesanvo.
                        </p>
                    </div>
                </div>
            </div>

        {{-- ===================== 3. JIKA SUDAH DISETUJUI & AKTIF (APPROVED) ===================== --}}
        @else
            {{-- Kartu Info Kode & Tautan --}}
            <div class="rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-foreground">
                                Kode Referal Mitra Anda
                            </h2>
                            <x-badge warna="hijau" :titik="true">Aktif &amp; Terverifikasi</x-badge>
                        </div>
                        <p class="mt-1 text-xs sm:text-sm text-muted-foreground">
                            Bagikan kode atau tautan unik ini ke rekan atau klien Anda.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        {{-- Tampilan Kode --}}
                        <div class="flex items-center gap-2 rounded-xl border border-border bg-muted/50 px-3.5 py-2">
                            <span class="font-mono text-base sm:text-lg font-bold tracking-widest text-primary">
                                {{ $referralCode->code }}
                            </span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $referralCode->code }}'); disalin = true; setTimeout(() => disalin = false, 2000)"
                                    class="rounded-md p-1 text-muted-foreground transition hover:text-foreground"
                                    title="Salin Kode Referal">
                                <span x-show="!disalin">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </span>
                                <span x-show="disalin" x-cloak class="text-xs text-primary font-bold">Tersalin!</span>
                            </button>
                        </div>

                        {{-- Tombol Salin Tautan --}}
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $tautanReferal }}'); disalinTautan = true; setTimeout(() => disalinTautan = false, 2000)"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs sm:text-sm font-medium transition hover:bg-muted">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            <span x-text="disalinTautan ? 'Tautan Tersalin!' : 'Salin Tautan'"></span>
                        </button>

                        {{-- Bagikan via WhatsApp --}}
                        @php
                            $pesanWa = urlencode("Halo! Dapatkan diskon {$referralCode->discount_percent}% langganan WhatsApp Gateway di Pesanvo menggunakan kode promo referal saya: {$referralCode->code}. Daftar di sini: {$tautanReferal}");
                        @endphp
                        <a href="https://api.whatsapp.com/send?text={{ $pesanWa }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs sm:text-sm font-medium text-white shadow-xs transition hover:bg-emerald-700">
                            <i class="bi bi-whatsapp"></i>
                            <span>Bagikan ke WA</span>
                        </a>
                    </div>
                </div>

                {{-- Baris Detail Rekening & Ketentuan (dengan Toggle Ikon Mata) --}}
                <div class="mt-5 border-t border-border/70 pt-4 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-muted-foreground">Rekening Pencairan:</span>
                        <span class="font-semibold text-foreground">{{ $referralCode->bank_name }}</span>
                        <span class="text-border">•</span>
                        <span x-show="sembunyi" class="font-mono text-muted-foreground">
                            {{ substr($referralCode->bank_account_number, 0, 3) . '••••' . substr($referralCode->bank_account_number, -3) }}
                        </span>
                        <span x-show="!sembunyi" x-cloak class="font-mono font-bold text-foreground">
                            {{ $referralCode->bank_account_number }}
                        </span>
                        <span class="text-border">•</span>
                        <span class="text-foreground">a.n {{ $referralCode->bank_account_name }}</span>

                        <button type="button" @click="toggleSembunyi()" class="text-primary hover:underline p-0.5" title="Lihat/Sembunyikan Nomor Rekening">
                            <i :class="sembunyi ? 'bi bi-eye' : 'bi bi-eye-slash'"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 text-muted-foreground">
                        <span>Diskon Pembeli: <strong class="text-foreground">{{ $referralCode->discount_percent }}%</strong></span>
                        <span class="text-border">•</span>
                        <span>Komisi Anda: <strong class="text-primary">{{ $referralCode->commission_percent }}%</strong></span>
                    </div>
                </div>
            </div>

            {{-- 4 Stat Kartu --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat label="Total Referal"
                        :nilai="$totalReferral"
                        sub="Workspace yang memakai kode Anda"
                        ikon="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />

                <x-stat label="Tagihan Lunas"
                        :nilai="$totalSukses"
                        sub="Transaksi berhasil terkonfirmasi"
                        ikon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-stat label="Komisi Siap Cair"
                        :nilai="'Rp '.number_format($komisiMenunggu, 0, ',', '.')"
                        sub="Telah disetujui (Min. Rp 100.000)"
                        :nada="$komisiMenunggu >= 100_000 ? 'perhatian' : 'netral'"
                        ikon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-stat label="Komisi Telah Ditransfer"
                        :nilai="'Rp '.number_format($komisiCair, 0, ',', '.')"
                        sub="Total uang masuk ke rekening Anda"
                        ikon="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </div>

            {{-- ===================== KOTAK PENCAIRAN DANA & TUTORIAL ===================== --}}
            <div class="rounded-2xl border border-primary/25 bg-card p-5 sm:p-6 shadow-xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-foreground">
                            Pencairan Dana Komisi
                        </h3>
                        <p class="mt-0.5 text-xs sm:text-sm text-muted-foreground">
                            Komisi yang berstatus disetujui dapat ditarik langsung ke rekening terdaftar Anda (minimal Rp 100.000).
                        </p>
                    </div>

                    {{-- Tombol Tindakan Tarik Dana --}}
                    <div>
                        @if ($komisiMenunggu < 100_000)
                            <div class="inline-flex items-center gap-2 rounded-xl border border-border bg-muted/50 px-4 py-2 text-xs font-semibold text-muted-foreground cursor-not-allowed">
                                <i class="bi bi-lock-fill"></i>
                                <span>Minimal Tarik Rp 100.000</span>
                            </div>
                        @elseif ($hasPendingPayout)
                            <div class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Transfer Sedang Diproses Admin</span>
                            </div>
                        @else
                            <button type="button" @click="modalTarik = true"
                                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]">
                                <i class="bi bi-cash-stack"></i>
                                <span>Tarik Dana Komisi (Rp {{ number_format($komisiMenunggu, 0, ',', '.') }})</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Alert Status Saldo / Payout --}}
                @if ($komisiMenunggu < 100_000)
                    <div class="rounded-xl border border-border bg-muted/30 p-3.5 text-xs text-muted-foreground flex items-center gap-2.5">
                        <i class="bi bi-info-circle-fill text-primary text-base shrink-0"></i>
                        <div>
                            Saldo komisi siap cair Anda saat ini: <strong>Rp {{ number_format($komisiMenunggu, 0, ',', '.') }}</strong>. 
                            Anda membutuhkan <strong>Rp {{ number_format(100_000 - $komisiMenunggu, 0, ',', '.') }}</strong> lagi untuk dapat melakukan pencairan dana.
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5">
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base shrink-0"></i>
                        <div>
                            Saldo Anda telah memenuhi syarat minimum pencairan (Rp 100.000). Klik tombol di atas untuk meneruskan notifikasi transfer langsung ke WhatsApp dan Gmail admin.
                        </div>
                    </div>
                @endif

                {{-- Tutorial 4 Langkah Pencairan --}}
                <div class="border-t border-border/70 pt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3">
                        Tutorial &amp; Alur Pencairan Komisi
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs mb-2">1</span>
                            <p class="font-semibold text-foreground">Kumpulkan Komisi</p>
                            <p class="mt-1 text-muted-foreground leading-relaxed">Capai akumulasi saldo minimal Rp 100.000 dari transaksi langganan klien Anda.</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs mb-2">2</span>
                            <p class="font-semibold text-foreground">Klik Tarik Dana</p>
                            <p class="mt-1 text-muted-foreground leading-relaxed">Tekan tombol penarikan ke rekening bank / e-wallet yang sudah Anda daftarkan.</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs mb-2">3</span>
                            <p class="font-semibold text-foreground">Notifikasi ke Admin</p>
                            <p class="mt-1 text-muted-foreground leading-relaxed">Sistem otomatis mengirim pemberitahuan instan via WhatsApp dan Gmail ke Admin Pesanvo.</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs mb-2">4</span>
                            <p class="font-semibold text-foreground">Transfer 1x24 Jam</p>
                            <p class="mt-1 text-muted-foreground leading-relaxed">Admin mentransfer manual ke rekening Anda dan status komisi otomatis menjadi "Sudah Ditransfer".</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat Pencairan Dana (Payouts) --}}
            @if ($payoutRequests->isNotEmpty())
                <x-section judul="Riwayat Pengajuan Pencairan Dana"
                           sub="Daftar transaksi penarikan komisi yang pernah Anda ajukan.">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[42rem] text-sm text-left">
                            <thead class="border-b border-border bg-muted/30 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">No. Dokumen</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3 text-right">Komisi Bruto</th>
                                    <th class="px-4 py-3 text-right">Transfer Bersih</th>
                                    <th class="px-4 py-3">Rekening Tujuan</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($payoutRequests as $pr)
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-foreground">
                                            {{ $pr->payout_number ?: '#PO-'.$pr->id }}
                                        </td>
                                        <td class="px-4 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                            {{ $pr->created_at->translatedFormat('d M Y, H:i') }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-medium text-muted-foreground tabular-nums whitespace-nowrap">
                                            {{ $pr->formattedAmount() }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-bold text-primary tabular-nums whitespace-nowrap">
                                            {{ $pr->formattedNetAmount() }}
                                        </td>
                                        <td class="px-4 py-3.5 text-xs">
                                            <span class="font-medium text-foreground">{{ $pr->bank_name }}</span> - {{ $pr->bank_account_number }}
                                            <span class="block text-muted-foreground text-[11px]">a.n {{ $pr->bank_account_name }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            @if ($pr->isPaid())
                                                <x-badge warna="hijau">Sudah Ditransfer</x-badge>
                                            @elseif ($pr->isPending())
                                                <x-badge warna="kuning">Menunggu Transfer</x-badge>
                                            @else
                                                <x-badge warna="merah">Ditolak</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <a href="{{ route('mitra.payout.invoice', $pr->id) }}"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground hover:bg-muted transition shadow-2xs">
                                                <svg class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                <span>Invoice</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-section>
            @endif

            {{-- Tabel Riwayat Penukaran & Komisi Klien --}}
            <x-section judul="Riwayat Transaksi Downline Klien"
                       sub="Daftar workspace yang menggunakan kode referal Anda beserta status pembayarannya.">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm text-left">
                        <thead class="border-b border-border bg-muted/30 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-5 py-3">Workspace Klien</th>
                                <th class="px-5 py-3">No. Tagihan</th>
                                <th class="px-5 py-3 text-right">Diskon Klien</th>
                                <th class="px-5 py-3 text-right">Komisi Anda</th>
                                <th class="px-5 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($redemptions as $item)
                                <tr class="hover:bg-muted/30 transition-colors">
                                    <td class="px-5 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                        {{ $item->created_at->translatedFormat('d M Y, H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5 font-medium">
                                        {{ $item->workspace?->name ?? 'Workspace Dihapus' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-muted-foreground">
                                        {{ $item->invoice?->number ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right tabular-nums text-muted-foreground">
                                        Rp {{ number_format($item->discount_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-primary">
                                        Rp {{ number_format($item->commission_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($item->status === 'paid')
                                            <x-badge warna="hijau">Sudah Cair</x-badge>
                                        @elseif ($item->status === 'approved')
                                            <x-badge warna="biru">Disetujui</x-badge>
                                        @elseif ($item->status === 'pending')
                                            <x-badge warna="kuning">Menunggu Bayar</x-badge>
                                        @else
                                            <x-badge warna="merah">Batal</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-kosong kolom="6"
                                          judul="Belum ada penukaran kode"
                                          pesan="Bagikan tautan atau kode referal Anda ke calon pelanggan untuk mulai mengumpulkan komisi." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($redemptions->hasPages())
                    <div class="p-4 border-t border-border">
                        {{ $redemptions->links() }}
                    </div>
                @endif
            </x-section>
        @endif

        {{-- ===================== MODAL TARIK DANA KOMISI ===================== --}}
        @if ($isApproved && ($komisiMenunggu ?? 0) >= 100_000)
            @php
                $tarikBruto = $komisiMenunggu;
                $tarikFee = (int) round($tarikBruto * 0.05);
                $tarikNet = $tarikBruto - $tarikFee;
            @endphp
            <div x-show="modalTarik" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-tarik-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                    <div x-show="modalTarik" x-transition.opacity class="fixed inset-0 bg-background/80 backdrop-blur-sm" @click="modalTarik = false"></div>

                    <div x-show="modalTarik" x-transition.scale.origin.center class="relative transform overflow-hidden rounded-2xl border border-border bg-card text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="border-b border-border p-5 flex items-center justify-between">
                            <h3 class="text-base font-bold text-foreground flex items-center gap-2" id="modal-tarik-title">
                                <i class="bi bi-cash-stack text-primary"></i>
                                <span>Konfirmasi Pencairan Komisi</span>
                            </h3>
                            <button type="button" @click="modalTarik = false" class="text-muted-foreground hover:text-foreground">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <form action="{{ route('mitra.payout') }}" method="POST">
                            @csrf
                            <div class="p-6 space-y-4 text-xs sm:text-sm">
                                <div class="rounded-xl border border-border bg-muted/30 p-4 space-y-2">
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>Total Komisi Bruto:</span>
                                        <span class="font-semibold text-foreground">Rp {{ number_format($tarikBruto, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>Biaya Administrasi (5%):</span>
                                        <span class="font-semibold text-destructive">- Rp {{ number_format($tarikFee, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between border-t border-border pt-2 text-base font-extrabold text-primary">
                                        <span>Transfer Bersih:</span>
                                        <span class="font-mono">Rp {{ number_format($tarikNet, 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-border/70 p-3.5 space-y-1 bg-background text-xs">
                                    <span class="text-muted-foreground font-medium">Rekening Tujuan:</span>
                                    <p class="font-bold text-foreground">{{ $referralCode->bank_name }} - {{ $referralCode->bank_account_number }}</p>
                                    <p class="text-muted-foreground">Atas Nama: <strong class="text-foreground">{{ $referralCode->bank_account_name }}</strong></p>
                                </div>

                                <div>
                                    <label for="payout_notes" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Catatan untuk Finance (Opsional)
                                    </label>
                                    <textarea name="notes" id="payout_notes" rows="2" maxlength="300"
                                              placeholder="Misal: Mohon ditransfer sebelum pukul 17.00 WIB"
                                              class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs transition focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
                                </div>

                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Sistem akan otomatis menerbitkan invoice resmi dan mengirimkan notifikasi instan ke WhatsApp dan Email Admin untuk proses transfer manual 1x24 jam kerja.
                                </p>
                            </div>

                            <div class="border-t border-border bg-muted/20 p-4 flex items-center justify-end gap-2">
                                <button type="button" @click="modalTarik = false" class="rounded-xl border border-border px-4 py-2 text-xs sm:text-sm font-medium text-muted-foreground hover:bg-muted transition">
                                    Batal
                                </button>
                                <button type="submit" class="rounded-xl bg-primary px-6 py-2 text-xs sm:text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition">
                                    Ajukan Pencairan Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===================== MODAL SYARAT & KETENTUAN KEMITRAAN ===================== --}}
        <div x-show="modalTerms" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-terms-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div x-show="modalTerms" x-transition.opacity class="fixed inset-0 bg-background/80 backdrop-blur-sm" @click="modalTerms = false"></div>

                <div x-show="modalTerms" x-transition.scale.origin.center class="relative transform overflow-hidden rounded-2xl border border-border bg-card text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                    <div class="border-b border-border p-5 flex items-center justify-between">
                        <h3 class="text-base font-bold text-foreground flex items-center gap-2" id="modal-terms-title">
                            <i class="bi bi-shield-check text-primary"></i>
                            <span>Syarat &amp; Ketentuan Program Mitra Pesanvo</span>
                        </h3>
                        <button type="button" @click="modalTerms = false" class="text-muted-foreground hover:text-foreground">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-4 text-xs sm:text-sm text-muted-foreground leading-relaxed max-h-[65vh] overflow-y-auto">
                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">1. Ketentuan Akun &amp; Kode Referal</h4>
                            <p>Setiap mitra hanya berhak memiliki 1 (satu) kode referal unik secara permanen yang melekat pada akun terdaftar dan tidak dapat diubah atau dialihkan.</p>
                        </div>

                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">2. Perolehan &amp; Validitas Komisi</h4>
                            <p>Komisi kemitraan diberikan dari nilai pembayaran sah pelanggan baru yang bertransaksi menggunakan kode referal Anda dan telah diverifikasi lunas oleh sistem.</p>
                        </div>

                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">3. Ambang Batas Pencairan Dana</h4>
                            <p>Penarikan dana komisi dapat diajukan setelah akumulasi saldo komisi yang disetujui (approved) mencapai nilai minimum Rp 100.000 (seratus ribu rupiah).</p>
                        </div>

                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">4. Biaya Administrasi &amp; Penanganan Pemrosesan</h4>
                            <p>Setiap transaksi pencairan dana dikenakan biaya administrasi &amp; pemrosesan perbankan sebesar 5% yang langsung dipotong dari total nominal komisi yang dicairkan.</p>
                        </div>

                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">5. Waktu Pemrosesan &amp; Transfer</h4>
                            <p>Pencairan dana diproses secara manual melalui transfer antar bank atau e-wallet dalam waktu 1x24 jam kerja setelah permohonan penarikan diajukan oleh mitra.</p>
                        </div>

                        <div class="space-y-1">
                            <h4 class="font-semibold text-foreground">6. Larangan &amp; Integritas</h4>
                            <p>Mitra dilarang melakukan tindakan manipulasi, spamming massal yang merugikan nama baik brand, atau penukaran kode pada akun milik pribadi sendiri (self-referral). Pelanggaran dapat berakibat pada pembatalan komisi dan penonaktifan kemitraan.</p>
                        </div>
                    </div>

                    <div class="border-t border-border bg-muted/20 p-4 text-right">
                        <button type="button" @click="modalTerms = false" class="rounded-xl bg-primary px-5 py-2 text-xs sm:text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition">
                            Saya Mengerti &amp; Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
