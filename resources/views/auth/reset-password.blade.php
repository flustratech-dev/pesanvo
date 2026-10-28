@extends('layouts.auth')

@section('title', 'Atur Ulang Kata Sandi - Pesanvo Gateway')

@section('content')
<div>
    {{-- Header Form --}}
    <div class="mb-6">
        <h2 class="text-2xl sm:text-[1.7rem] font-bold tracking-tight text-foreground">
            Kata Sandi Baru
        </h2>
        <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground leading-relaxed">
            Buat kata sandi baru yang kuat minimal 8 karakter untuk akun Anda.
        </p>
    </div>

    {{-- Pesan Galat Umum --}}
    @if ($errors->any() && !$errors->has('password') && !$errors->has('email'))
        <div class="mb-5 rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs sm:text-sm text-destructive flex items-start gap-2.5">
            <i class="bi bi-exclamation-triangle mt-0.5 shrink-0 text-base"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- Form Reset Password --}}
    <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Field Email --}}
        <div>
            <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                Email
            </label>
            <div class="relative">
                <input type="email" name="email" id="email" 
                       value="{{ old('email', $email) }}" 
                       required autocomplete="email"
                       placeholder="nama@perusahaan.com"
                       class="w-full rounded-xl border @error('email') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
            </div>
            @error('email')
                <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Field Password Baru --}}
        <div>
            <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                Kata Sandi Baru
            </label>
            <div class="relative">
                <input type="password" name="password" id="password" 
                       required autocomplete="new-password" autofocus
                       placeholder="Minimal 8 karakter"
                       class="w-full rounded-xl border @error('password') border-destructive focus:ring-destructive/20 focus:border-destructive @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 pr-11 text-sm transition focus:outline-none focus:ring-2">
                <button type="button" 
                        onclick="togglePasswordVisibility('password', this)" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition hover:text-foreground p-1"
                        title="Tampilkan / Sembunyikan Kata Sandi">
                    <i class="bi bi-eye text-base"></i>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 flex items-center gap-1.5 text-xs text-destructive font-medium">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Field Konfirmasi Password --}}
        <div>
            <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                Konfirmasi Kata Sandi Baru
            </label>
            <div class="relative">
                <input type="password" name="password_confirmation" id="password_confirmation" 
                       required autocomplete="new-password"
                       placeholder="Ulangi kata sandi baru"
                       class="w-full rounded-xl border border-input focus:border-primary focus:ring-primary/20 bg-background px-4 py-2.5 pr-11 text-sm transition focus:outline-none focus:ring-2">
                <button type="button" 
                        onclick="togglePasswordVisibility('password_confirmation', this)" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition hover:text-foreground p-1"
                        title="Tampilkan / Sembunyikan Kata Sandi">
                    <i class="bi bi-eye text-base"></i>
                </button>
            </div>
        </div>

        {{-- Tombol Submit --}}
        <div class="pt-2">
            <button type="submit" 
                    class="w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20">
                Simpan Kata Sandi Baru
            </button>
        </div>
    </form>
</div>
@endsection
