@extends('layouts.app')
@section('title', 'Tiket #'.$ticket->id)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="mb-2">
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition shadow-xs">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Kembali ke Semua Tiket</span>
                </a>
            </div>
            <h2 class="mt-1 text-lg font-semibold">{{ $ticket->subject }}</h2>
            <p class="mt-0.5 text-sm text-muted-foreground">
                Tiket #{{ $ticket->id }} · {{ $ticket->labelKategori() }} · prioritas {{ strtolower($ticket->labelPrioritas()) }}
            </p>
        </div>
        <x-badge :warna="$ticket->warnaStatus()">{{ $ticket->labelStatus() }}</x-badge>
    </div>

    <x-section judul="Percakapan">
        <div class="space-y-4 px-5 py-4">
            @foreach ($ticket->messages as $pesan)
                <div @class(['rounded-lg p-4', 'bg-primary/5 border border-primary/15' => $pesan->is_from_admin, 'bg-muted/40' => ! $pesan->is_from_admin])>
                    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-xs">
                        {{-- Nama admin sengaja tidak ditampilkan: yang menjawab
                             adalah Pesanvo, bukan orang tertentu. --}}
                        <span class="font-medium">
                            {{ $pesan->is_from_admin ? 'Tim Pesanvo' : ($pesan->user?->name ?? 'Anda') }}
                        </span>
                        <span class="text-muted-foreground">{{ $pesan->created_at->translatedFormat('j M Y, H:i') }}</span>
                    </div>

                    <p class="whitespace-pre-line text-sm leading-relaxed">{{ $pesan->body }}</p>

                    @if ($pesan->punyaLampiran())
                        <a href="{{ route('tickets.attachment', [$ticket->id, $pesan->id]) }}"
                           class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs transition hover:bg-muted">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21.4 11.05 12.25 20.2a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 0 1 5.65 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            {{ $pesan->attachment_name ?: 'Lampiran' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </x-section>

    <x-card :title="$ticket->isClosed() ? 'Tiket ini sudah ditutup' : 'Balas'"
            :subtitle="$ticket->isClosed() ? 'Membalas akan membukanya kembali.' : 'Kami balas secepatnya dan mengabari Anda lewat WhatsApp serta email.'"
            class="mt-5">
        <form method="POST" action="{{ route('tickets.reply', $ticket->id) }}" enctype="multipart/form-data" class="space-y-3" data-validasi>
            @csrf
            <textarea name="body" required rows="4" maxlength="5000"
                      placeholder="Tulis balasan Anda…"
                      class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">{{ old('body') }}</textarea>

            <input name="lampiran" type="file" accept=".{{ implode(',.', $jenisLampiran) }}"
                   class="w-full rounded-lg border-border bg-background text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm">

            @foreach (['body', 'lampiran'] as $kolom)
                @error($kolom)
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            @endforeach

            <div class="flex flex-wrap gap-2">
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Kirim balasan
                </button>
            </div>
        </form>

        @unless ($ticket->isClosed())
            <form method="POST" action="{{ route('tickets.close', $ticket->id) }}" class="mt-3 border-t border-border pt-3"
                  data-konfirmasi="Tutup tiket ini? Anda tetap bisa membalas untuk membukanya lagi.">
                @csrf
                <button class="rounded-lg border border-border px-4 py-2 text-sm transition hover:bg-muted">
                    Masalah sudah selesai — tutup tiket
                </button>
            </form>
        @endunless
    </x-card>
@endsection
