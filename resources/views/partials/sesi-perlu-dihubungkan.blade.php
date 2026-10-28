{{-- Ajakan menghubungkan kembali nomor yang dilepas saat langganan mati.

     Tanpa ini ada lubang di ujung alur pembayaran: pelanggan membayar,
     langganannya menyala, lalu tidak terjadi apa-apa — karena sesinya sudah
     dilepas saat masa tenggang habis dan tidak ada yang menyalakannya kembali.
     Sengaja tidak dinyalakan otomatis: menghubungkan banyak sesi WebSocket
     sekaligus serentak dari satu klik bisa memicu lonjakan handshake koneksi
     dan rate-limit WhatsApp, jadi pemiliknya yang menekan Hubungkan, satu per satu.

     `connected_at` yang terisi membedakan "pernah tertaut lalu dilepas" dari
     "belum pernah discan sama sekali"; yang kedua bukan urusan spanduk ini. --}}

@php
    $perluDihubungkan = collect($sessions ?? [])
        ->filter(fn ($s) => $s->status === 'disconnected' && $s->connected_at !== null);
@endphp

@if ($perluDihubungkan->isNotEmpty() && ($currentSubscription?->isUsable() ?? false))
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-primary">
        <span>
            <strong>Langganan Anda aktif.</strong>
            {{ $perluDihubungkan->count() }} nomor masih terlepas dan perlu dihubungkan lagi —
            kredensialnya masih tersimpan, jadi tidak perlu scan QR ulang.
        </span>
        @unless (request()->routeIs('sessions.index'))
            <a href="{{ route('sessions.index') }}" class="shrink-0 rounded-lg bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90">
                Buka halaman Sesi
            </a>
        @endunless
    </div>
@endif
