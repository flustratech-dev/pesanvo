@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi - Pesanvo Gateway')

@section('content')
<div>
    {{-- Header Form --}}
    <div class="mb-6">
        <h2 class="text-2xl sm:text-[1.7rem] font-bold tracking-tight text-foreground">
            Lupa Kata Sandi?
        </h2>
        <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground leading-relaxed">
            Masukkan alamat email akun Anda. Kami akan mengirimkan tautan aman untuk mengatur ulang kata sandi.
        </p>
    </div>

    {{-- Pesan Status / Sukses --}}
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-3.5 text-xs sm:text-sm text-primary flex items-start gap-2.5">
            <i class="bi bi-check-circle mt-0.5 shrink-0 text-base"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Form Lupa Password --}}
    <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
        @csrf

        {{-- Field Email --}}
        <div>
            <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                Email Terdaftar
            </label>
            <div class="relative">
                <input type="email" name="email" id="email" 
                       value="{{ old('email') }}" 
                       required autocomplete="email" autofocus
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

        {{-- Tombol Submit --}}
        <div class="pt-2">
            <button type="submit" 
                    class="w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20">
                Kirim Tautan Atur Ulang
            </button>
        </div>
    </form>

    {{-- Tautan Kembali ke Login --}}
    <div class="mt-6 border-t border-border/80 pt-5 text-center text-xs text-muted-foreground">
        Sudah ingat kata sandi Anda?
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline ml-1">
            Masuk sekarang
        </a>
    </div>
</div>
@endsection
