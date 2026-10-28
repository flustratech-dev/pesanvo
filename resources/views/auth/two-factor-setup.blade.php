@extends('layouts.auth')

@section('title', 'Pasang Autentikasi Dua Faktor - Pesanvo Gateway')

@section('content')
<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">
            Pasang autentikasi dua faktor
        </h2>
        <p class="mt-1.5 text-xs text-muted-foreground sm:text-sm">
            @if ($wajib)
                {{-- Alasannya disebut, bukan cuma "wajib". Penjagaan yang tidak
                     dijelaskan terbaca sebagai birokrasi, dan yang terbaca
                     begitu akan dicari cara melewatinya. --}}
                Akun administrator wajib memakainya. Akun ini bisa menandai tagihan lunas,
                mengatur ulang kata sandi siapa pun, dan membaca seluruh workspace —
                satu kata sandi yang bocor cukup untuk semuanya.
            @else
                Setelah ini, masuk membutuhkan kata sandi <em>dan</em> kode enam digit dari ponsel Anda.
            @endif
        </p>
    </div>

    <ol class="space-y-5 text-sm">
        <li>
            <p class="font-medium">1. Pasang aplikasi authenticator</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Google Authenticator, Authy, 1Password, atau Microsoft Authenticator — semuanya cocok.
                Kodenya dibuat di ponsel Anda sendiri, jadi tetap bekerja tanpa sinyal maupun internet.
            </p>
        </li>

        <li>
            <p class="font-medium">2. Pindai kode ini</p>
            <div class="mt-3 flex justify-center rounded-xl border border-border bg-white p-4">
                <canvas data-otpauth="{{ $uri }}"></canvas>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                    Tidak bisa memindai? Masukkan kunci ini secara manual
                </summary>
                <p class="mt-2 select-all break-all rounded-lg bg-muted px-3 py-2 font-mono text-xs">
                    {{ $rahasia }}
                </p>
            </details>
        </li>

        <li>
            <p class="font-medium">3. Masukkan kode yang muncul</p>

            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-3">
                @csrf
                <input type="text" name="kode" required autofocus
                       inputmode="numeric" autocomplete="one-time-code"
                       maxlength="7" placeholder="123456"
                       class="w-full rounded-xl border-input bg-background text-center font-mono text-lg tracking-[0.4em] focus:border-primary focus:ring-primary">

                @error('kode')
                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                @enderror

                <button class="mt-4 w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90">
                    Nyalakan 2FA
                </button>
            </form>
        </li>
    </ol>

    <p class="mt-6 text-xs text-muted-foreground">
        Setelah menyala, Anda akan diberi 8 kode pemulihan sekali pakai. Simpan di tempat yang
        <strong>bukan</strong> ponsel yang sama — kode itu satu-satunya jalan masuk kalau ponsel Anda hilang.
    </p>

    @unless ($wajib)
        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button class="text-xs text-muted-foreground underline hover:text-foreground">Keluar</button>
        </form>
    @endunless
</div>
@endsection
