@extends('layouts.publik')

@section('title', 'Status Layanan — ' . config('app.name'))
@section('description', 'Keadaan REST API, dashboard, koneksi WhatsApp, dan antrean pengiriman Pesanvo Gateway secara langsung, beserta riwayat gangguannya.')

@php
    $gaya = [
        \App\Support\StatusLayanan::OPERASIONAL => ['Semua layanan berjalan normal', 'bg-emerald-500', 'text-emerald-700 dark:text-emerald-400', 'border-emerald-500/30 bg-emerald-500/10'],
        \App\Support\StatusLayanan::TERGANGGU => ['Sebagian layanan terganggu', 'bg-amber-500', 'text-amber-700 dark:text-amber-400', 'border-amber-500/30 bg-amber-500/10'],
        \App\Support\StatusLayanan::MATI => ['Ada gangguan yang sedang berlangsung', 'bg-red-500', 'text-red-700 dark:text-red-400', 'border-red-500/30 bg-red-500/10'],
    ];

    [$judulRingkas, $titik, $teksWarna, $kotakWarna] = $gaya[$ringkas];
@endphp

@section('content')
<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">

    {{-- Spanduk ringkasan. Satu kalimat, paling besar di halaman: yang membuka
         halaman ini sedang buru-buru dan hanya butuh satu jawaban. --}}
    <div class="rounded-2xl border {{ $kotakWarna }} px-5 py-6 sm:px-7 sm:py-8">
        <div class="flex items-start gap-4">
            <span class="relative mt-1.5 flex h-3 w-3 shrink-0">
                @if ($ringkas !== \App\Support\StatusLayanan::OPERASIONAL)
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $titik }} opacity-60"></span>
                @endif
                <span class="relative inline-flex h-3 w-3 rounded-full {{ $titik }}"></span>
            </span>
            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl {{ $teksWarna }}">{{ $judulRingkas }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Diperiksa {{ now()->translatedFormat('j F Y, H:i') }} WIB. Halaman ini memeriksa ulang setiap kali dimuat.
                </p>
            </div>
        </div>
    </div>

    {{-- Insiden yang sedang berjalan naik ke atas komponen. Saat ada gangguan,
         kalimat dari manusia lebih berguna daripada lima lencana. --}}
    @forelse ($berjalan as $insiden)
        <div class="mt-6 rounded-2xl border border-border bg-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                    {{ $insiden->kind === 'pemeliharaan' ? 'bg-sky-500/15 text-sky-700 dark:text-sky-400' : 'bg-amber-500/15 text-amber-700 dark:text-amber-400' }}">
                    {{ $insiden->kind === 'pemeliharaan' ? 'Pemeliharaan terjadwal' : 'Gangguan' }}
                </span>
                <span class="text-xs text-muted-foreground">{{ $insiden->label() }}</span>
            </div>

            <h2 class="mt-3 text-base font-semibold">{{ $insiden->title }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $insiden->summary }}</p>

            @if ($insiden->updates->isNotEmpty())
                <ol class="mt-5 space-y-4 border-l border-border pl-5">
                    @foreach ($insiden->updates->sortByDesc('created_at') as $kabar)
                        <li class="relative">
                            <span class="absolute -left-[1.4rem] top-1.5 h-2 w-2 rounded-full bg-border"></span>
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ $kabar->created_at->translatedFormat('j M, H:i') }} WIB
                            </p>
                            <p class="mt-0.5 text-sm">{{ $kabar->body }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif

            <p class="mt-5 text-xs text-muted-foreground">
                Mulai {{ $insiden->started_at->translatedFormat('j F Y, H:i') }} WIB
            </p>
        </div>
    @empty
    @endforelse

    {{-- Komponen --}}
    <div class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Komponen</h2>

        <div class="mt-4 divide-y divide-border border-y border-border">
            @foreach ($komponen as $kunci => $data)
                @php
                    $warnaTitik = match ($data['keadaan']) {
                        \App\Support\StatusLayanan::OPERASIONAL => 'bg-emerald-500',
                        \App\Support\StatusLayanan::TERGANGGU => 'bg-amber-500',
                        default => 'bg-red-500',
                    };
                @endphp
                <div class="py-4">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $data['nama'] }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ $data['jelas'] }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-2 text-sm">
                            <span class="h-2 w-2 rounded-full {{ $warnaTitik }}"></span>
                            {{ $data['catatan'] }}
                        </span>
                    </div>

                    {{-- Deretan batang riwayat. Abu-abu berarti belum pernah
                         diukur pada hari itu — bukan gangguan, dan itu perbedaan
                         yang harus terlihat, bukan disamarkan jadi hijau. --}}
                    <div class="mt-3 flex gap-[2px] overflow-hidden" title="{{ $hariRiwayat }} hari terakhir">
                        @foreach ($riwayat[$kunci] ?? [] as $hari => $persen)
                            <span
                                class="h-7 flex-1 rounded-[2px] {{ $persen === null ? 'bg-muted' : ($persen >= 99.5 ? 'bg-emerald-500/70' : ($persen >= 95 ? 'bg-amber-500/70' : 'bg-red-500/70')) }}"
                                title="{{ \Illuminate\Support\Carbon::parse($hari)->translatedFormat('j M Y') }} — {{ $persen === null ? 'belum diukur' : number_format($persen, 2, ',', '.') . '%' }}"></span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
            <span>{{ $hariRiwayat }} hari terakhir</span>
            <span class="flex items-center gap-3">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-[2px] bg-emerald-500/70"></span> normal</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-[2px] bg-amber-500/70"></span> terganggu</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-[2px] bg-red-500/70"></span> gangguan</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-[2px] bg-muted"></span> belum diukur</span>
            </span>
        </div>
    </div>

    {{-- Angka ketersediaan --}}
    <div class="mt-10 grid grid-cols-2 gap-4">
        @foreach (['30' => '30 hari terakhir', '90' => '90 hari terakhir'] as $kunci => $label)
            <div class="rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-xs text-muted-foreground">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ $uptime[$kunci] === null ? '—' : number_format($uptime[$kunci], 2, ',', '.') . '%' }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Batas halaman ini, dinyatakan apa adanya.

         Halaman status yang tidak menyebut batasnya sendiri adalah halaman yang
         menjanjikan lebih dari yang bisa ia buktikan: ia disajikan oleh aplikasi
         yang statusnya ia laporkan, jadi ia TIDAK PERNAH bisa membuktikan sistem
         hidup — kalau containernya mati, halaman ini ikut mati. --}}
    <div class="mt-10 rounded-xl border border-border bg-muted/40 px-5 py-4 text-sm text-muted-foreground">
        <p class="font-medium text-foreground">Yang perlu Anda tahu tentang halaman ini</p>
        <ul class="mt-2 list-disc space-y-1.5 pl-5">
            <li>Halaman ini disajikan oleh sistem yang keadaannya ia laporkan. Jika Anda tidak dapat membukanya sama sekali, itu sendiri sudah menandakan gangguan berat.</li>
            <li>Pemblokiran nomor Anda oleh WhatsApp tidak akan terlihat di sini — itu terjadi pada nomor Anda, bukan pada layanan kami. Keadaannya terlihat di halaman <strong>Sesi</strong> pada dashboard Anda.</li>
            <li>Ketersediaan yang kami janjikan beserta pengecualiannya ada di <a href="{{ route('docs.show', 'sla') }}" class="underline underline-offset-2 hover:text-foreground">SLA</a>.</li>
            <li>Ingin memasang lencana status di aplikasi Anda sendiri? Ada di <a href="{{ route('status.json') }}" class="underline underline-offset-2 hover:text-foreground">status.json</a>, dan cara memakainya di <a href="{{ route('docs.show', 'memantau-koneksi') }}" class="underline underline-offset-2 hover:text-foreground">Memantau Koneksi</a>.</li>
        </ul>
    </div>

    {{-- Riwayat insiden --}}
    <div class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Riwayat {{ $hariRiwayat }} hari</h2>

        @if ($lampau->isEmpty())
            <p class="mt-4 text-sm text-muted-foreground">Tidak ada gangguan yang tercatat pada periode ini.</p>
        @else
            <div class="mt-4 divide-y divide-border border-y border-border">
                @foreach ($lampau as $insiden)
                    <div class="py-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <p class="font-medium">{{ $insiden->title }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $insiden->started_at->translatedFormat('j M Y, H:i') }} —
                                {{ $insiden->resolved_at->translatedFormat('H:i') }} WIB
                                ({{ $insiden->started_at->diffInMinutes($insiden->resolved_at) }} menit)
                            </p>
                        </div>
                        <p class="mt-1.5 text-sm text-muted-foreground">{{ $insiden->summary }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
