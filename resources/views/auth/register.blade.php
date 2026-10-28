@extends('layouts.auth')

@section('title', 'Daftar Akun - Pesanvo Gateway')
@section('container_width', 'max-w-[420px]')

@section('content')
<div>
    {{-- Header Form --}}
    <div class="mb-6">
        <h2 class="text-2xl sm:text-[1.7rem] font-bold tracking-tight text-foreground">
            Daftar Akun Baru
        </h2>
        <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground">
            Mulai kelola pesan dan otomatisasi WhatsApp Anda bersama Pesanvo
        </p>
    </div>

    {{-- Pesan Status / Info / Sesi --}}
    @if (session('info'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-3.5 text-xs sm:text-sm text-primary flex items-start gap-2.5">
            <i class="bi bi-info-circle mt-0.5 shrink-0 text-base"></i>
            <div>{{ session('info') }}</div>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-3.5 text-xs sm:text-sm text-primary flex items-start gap-2.5">
            <i class="bi bi-check-circle mt-0.5 shrink-0 text-base"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any() && !$errors->has('name') && !$errors->has('workspace') && !$errors->has('email') && !$errors->has('password') && !$errors->has('password_confirmation') && !$errors->has('terms'))
        <div class="mb-5 rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs sm:text-sm text-destructive flex items-start gap-2.5">
            <i class="bi bi-exclamation-triangle mt-0.5 shrink-0 text-base"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    @if (session('referral_code'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-3.5 text-xs sm:text-sm text-primary flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-tag-fill text-base shrink-0"></i>
                <div>
                    Kode referal <span class="font-bold tracking-wider uppercase font-mono">{{ session('referral_code') }}</span> terpasang: Diskon 10% untuk tagihan pertama Anda.
                </div>
            </div>
        </div>
    @endif

    {{-- Form Register --}}
    <form action="{{ route('register') }}" method="POST" class="space-y-4">
        @csrf

        @if(session('google_id') || old('google_id'))
            <input type="hidden" name="google_id" value="{{ old('google_id', session('google_id')) }}">
        @endif
        @if(session('google_avatar') || old('google_avatar'))
            <input type="hidden" name="google_avatar" value="{{ old('google_avatar', session('google_avatar')) }}">
        @endif

        {{-- Baris Nama Lengkap & Workspace --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Field Nama Lengkap --}}
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                    Nama Lengkap
                </label>
                <input type="text" name="name" id="name" 
                       value="{{ old('name', session('google_name') ?? request('name')) }}" 
                       required autocomplete="name" autofocus
                       placeholder="Nama Anda"
                       class="w-full rounded-xl border @error('name') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                @error('name')
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Field Workspace --}}
            <div>
                <label for="workspace" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                    Nama Workspace
                </label>
                <input type="text" name="workspace" id="workspace" 
                       value="{{ old('workspace') }}" 
                       required autocomplete="organization"
                       placeholder="Workspace Anda"
                       class="w-full rounded-xl border @error('workspace') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                @error('workspace')
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>
        </div>

        {{-- Field Email --}}
        <div>
            <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                Alamat Email
            </label>
            <input type="email" name="email" id="email" 
                   value="{{ old('email', session('google_email') ?? request('email')) }}" 
                   required autocomplete="email"
                   placeholder="nama@email.com"
                   class="w-full rounded-xl border @error('email') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
            @error('email')
                <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Baris Password & Konfirmasi Password --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Field Kata Sandi --}}
            <div>
                <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                    Kata Sandi
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" 
                           required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border @error('password') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 pr-16 text-sm transition focus:outline-none focus:ring-2">
                    <span id="password-length-badge" class="absolute right-10 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-bold rounded bg-muted text-muted-foreground transition-all select-none">0</span>
                    <button type="button" 
                            onclick="togglePasswordVisibility('password', this)" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition hover:text-foreground p-1"
                            title="Tampilkan / Sembunyikan Kata Sandi"
                            aria-label="Tampilkan atau sembunyikan kata sandi">
                        <i class="bi bi-eye text-base"></i>
                    </button>
                </div>
                
                <!-- 5-Segment Progress Bar -->
                <div class="flex gap-1 mt-2">
                    <div class="h-1 flex-1 rounded-full bg-border transition-colors duration-300" id="pass-bar-1"></div>
                    <div class="h-1 flex-1 rounded-full bg-border transition-colors duration-300" id="pass-bar-2"></div>
                    <div class="h-1 flex-1 rounded-full bg-border transition-colors duration-300" id="pass-bar-3"></div>
                    <div class="h-1 flex-1 rounded-full bg-border transition-colors duration-300" id="pass-bar-4"></div>
                    <div class="h-1 flex-1 rounded-full bg-border transition-colors duration-300" id="pass-bar-5"></div>
                </div>

                <!-- Strength Label -->
                <div class="flex justify-between items-center text-[11px] text-muted-foreground mt-1">
                    <span>Kekuatan:</span>
                    <span id="password-strength-text" class="font-bold text-muted-foreground">-</span>
                </div>

                @error('password')
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Field Konfirmasi Sandi --}}
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                    Konfirmasi Sandi
                </label>
                <div class="relative">
                    <input type="password" name="password_confirmation" id="password_confirmation" 
                           required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border @error('password_confirmation') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 pr-11 text-sm transition focus:outline-none focus:ring-2">
                    <button type="button" 
                            onclick="togglePasswordVisibility('password_confirmation', this)" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition hover:text-foreground p-1"
                            title="Tampilkan / Sembunyikan Kata Sandi"
                            aria-label="Tampilkan atau sembunyikan kata sandi">
                        <i class="bi bi-eye text-base"></i>
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-muted-foreground leading-normal">
                    Minimal 8 karakter dengan perpaduan huruf, angka &amp; simbol.
                </p>
                @error('password_confirmation')
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>
        </div>

        {{-- Checkbox Persetujuan Syarat & Ketentuan --}}
        <div class="pt-1">
            <label class="flex items-start gap-2.5 cursor-pointer select-none text-xs text-muted-foreground leading-relaxed">
                <input type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))
                       class="mt-0.5 rounded border-input text-primary focus:ring-primary h-4 w-4 shrink-0">
                <span>
                    Saya menyetujui <a href="{{ route('docs.show', 'syarat-layanan') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">Syarat &amp; Ketentuan</a> serta <a href="{{ route('docs.show', 'kebijakan-privasi') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">Kebijakan Privasi</a> Pesanvo.
                </span>
            </label>
            @error('terms')
                <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Tombol Submit --}}
        <div class="pt-2">
            <button type="submit" 
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90 active:scale-[0.99]">
                <span>Daftar Akun Baru</span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </form>

    {{-- Pemisah --}}
    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-border"></div>
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-background px-3 text-muted-foreground font-medium">atau daftar dengan</span>
        </div>
    </div>

    {{-- Tombol Google --}}
    <div>
        <a href="{{ route('auth.google') }}" 
           class="inline-flex w-full items-center justify-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-foreground shadow-2xs transition hover:bg-muted hover:border-border/80 active:scale-[0.99]">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Daftar dengan Google</span>
        </a>
    </div>

    {{-- Tautan Masuk Akun --}}
    <p class="mt-6 text-center text-xs text-muted-foreground">
        Sudah punya akun? 
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">
            Masuk sekarang
        </a>
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;

    const lengthBadge = document.getElementById('password-length-badge');
    const strengthText = document.getElementById('password-strength-text');
    const bars = [
        document.getElementById('pass-bar-1'),
        document.getElementById('pass-bar-2'),
        document.getElementById('pass-bar-3'),
        document.getElementById('pass-bar-4'),
        document.getElementById('pass-bar-5')
    ];

    const tingkatan = [
        { label: '-', color: 'var(--border)', textColor: 'var(--muted-foreground)' },
        { label: 'Sangat Lemah', color: '#ef4444', textColor: '#ef4444' },
        { label: 'Lemah', color: '#f97316', textColor: '#f97316' },
        { label: 'Sedang', color: '#eab308', textColor: '#eab308' },
        { label: 'Kuat', color: '#84cc16', textColor: '#84cc16' },
        { label: 'Sangat Kuat', color: '#22c55e', textColor: '#22c55e' },
    ];

    passwordInput.addEventListener('input', function() {
        const val = this.value;
        const len = val.length;

        const hasLower = /[a-z]/.test(val);
        const hasUpper = /[A-Z]/.test(val);
        const hasNumber = /[0-9]/.test(val);
        const hasSymbol = /[^A-Za-z0-9]/.test(val);

        const varietyCount = (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNumber ? 1 : 0) + (hasSymbol ? 1 : 0);
        const isRepeatedChar = /^(\x20|.)\1+$/.test(val);
        const isFullyValid = (len >= 8 && hasLower && hasUpper && hasNumber && hasSymbol);

        if (lengthBadge) {
            if (isFullyValid) {
                lengthBadge.textContent = '8+';
                lengthBadge.style.backgroundColor = '#22c55e';
                lengthBadge.style.color = '#ffffff';
            } else if (len >= 8) {
                lengthBadge.textContent = len;
                lengthBadge.style.backgroundColor = '#f97316';
                lengthBadge.style.color = '#ffffff';
            } else {
                lengthBadge.textContent = len;
                lengthBadge.style.backgroundColor = 'var(--muted)';
                lengthBadge.style.color = 'var(--muted-foreground)';
            }
        }

        let score = 0;
        if (len > 0) {
            if (isRepeatedChar || varietyCount === 1) {
                score = (len >= 8) ? 2 : 1;
            } else if (varietyCount === 2) {
                score = (len >= 8) ? 3 : 2;
            } else if (varietyCount === 3) {
                score = (len >= 8) ? 4 : 3;
            } else if (varietyCount === 4) {
                score = (len >= 8) ? 5 : 4;
            }
        }

        const current = tingkatan[score];

        bars.forEach((bar, index) => {
            if (bar) {
                bar.style.backgroundColor = (index < score) ? current.color : 'var(--border)';
            }
        });

        if (strengthText) {
            strengthText.textContent = current.label;
            strengthText.style.color = current.textColor;
        }
    });
});
</script>
@endsection
