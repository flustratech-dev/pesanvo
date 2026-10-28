@extends('layouts.app')
@section('title', 'Webhooks')

@section('content')
@php
    // Ditentukan sekali di atas: formulir di halaman ini hanya ditampilkan
    // kalau langganannya memang berlaku. Penolakan sebenarnya tetap di
    // EnsureSubscriptionActive — ini supaya tombolnya tidak ada sejak awal.
    $terkunci = ($currentSubscription ?? null) && ! $currentSubscription->isUsable();
@endphp

    @if ($terkunci)
        <x-kunci-langganan :subscription="$currentSubscription" aksi="menambah webhook" />
    @else
    <x-card title="Tambah webhook" subtitle="Gateway akan mengirim POST JSON ke URL ini setiap kejadian yang Anda pilih.">
        <form method="POST" action="{{ route('webhooks.store') }}" class="space-y-4">
            @csrf
            <div data-tur="webhook-url">
                <label class="mb-1 block text-sm font-medium" for="url">URL tujuan</label>
                <input id="url" name="url" type="url" required placeholder="https://app.contoh.id/webhook/whatsapp"
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div data-tur="webhook-pemilik">
                <label class="mb-1 block text-sm font-medium" for="api_key_id">Berlaku untuk</label>
                <select id="api_key_id" name="api_key_id"
                        class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">Seluruh workspace — menerima semua kejadian</option>
                    @foreach ($apiKeys as $key)
                        <option value="{{ $key->id }}" @selected(old('api_key_id') == $key->id)>
                            API key: {{ $key->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                    Pilih satu API key kalau Anda punya beberapa website di workspace ini — status
                    pengiriman hanya sampai ke webhook milik API key yang mengirim pesannya, jadi
                    tiap website tahu kejadian mana yang miliknya. Pesan masuk dan kejadian sesi
                    tetap sampai ke semua, karena keduanya tidak berasal dari integrasi tertentu.
                </p>
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium">Event</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($availableEvents as $value => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="events[]" value="{{ $value }}" checked>
                            {{ $label }} <code class="text-xs text-muted-foreground">{{ $value }}</code>
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Simpan</button>
        </form>
    </x-card>

    @endif

    <x-section judul="Webhook terdaftar">
        @if ($webhooks->isEmpty())
            <p class="text-sm text-muted-foreground">Belum ada webhook.</p>
        @else
            <div class="space-y-4">
                @foreach ($webhooks as $webhook)
                    <div class="rounded-lg border border-border bg-card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-sm">{{ $webhook->url }}</p>
                                <p class="mt-1 text-xs">
                                    @if ($webhook->untukSeluruhWorkspace())
                                        <span class="rounded bg-muted px-1.5 py-0.5 text-muted-foreground">seluruh workspace</span>
                                    @else
                                        <span class="rounded bg-primary/10 px-1.5 py-0.5 text-primary">
                                            API key: {{ $webhook->apiKey?->name ?? 'sudah dicabut' }}
                                        </span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ $webhook->events ? implode(', ', $webhook->events) : 'semua event' }}
                                    @if ($webhook->consecutive_failures > 0)
                                        &middot; <span class="text-destructive">{{ $webhook->consecutive_failures }} kegagalan berturut-turut</span>
                                    @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $webhook->is_active ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground' }}">
                                {{ $webhook->is_active ? 'aktif' : 'nonaktif' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <label class="text-xs uppercase text-muted-foreground">Signing secret</label>
                            <input readonly value="{{ $webhook->secret }}"
                                   class="mt-1 w-full rounded-lg border-input bg-muted/50 font-mono text-xs focus:ring-0">
                            <p class="mt-1 text-xs text-muted-foreground">
                                Bandingkan header <code>X-Pesanvo-Signature</code> dengan
                                <code>hash_hmac('sha256', $rawBody, $secret)</code> sebelum memproses payload.
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('webhooks.test', $webhook->id) }}">
                                @csrf
                                <button class="rounded-lg border border-input bg-background px-3 py-1.5 text-xs hover:bg-muted">Kirim uji coba</button>
                            </form>
                            <form method="POST" action="{{ route('webhooks.toggle', $webhook->id) }}">
                                @csrf
                                <button class="rounded-lg border border-input bg-background px-3 py-1.5 text-xs hover:bg-muted">
                                    {{ $webhook->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('webhooks.destroy', $webhook->id) }}"
                                  data-konfirmasi="Hapus webhook ini?">
                                @csrf @method('DELETE')
                                <button class="rounded-lg border border-destructive/50 px-3 py-1.5 text-xs text-destructive hover:bg-destructive/10">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-section>

    <x-section judul="Pengiriman terakhir">
        @if ($deliveries->isEmpty())
            <p class="text-sm text-muted-foreground">Belum ada pengiriman.</p>
        @else
                            <table class="w-full min-w-[600px] text-sm">
                    <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2">Waktu</th>
                            <th class="px-5 py-2">Event</th>
                            <th class="px-5 py-2">Kode</th>
                            <th class="px-5 py-2">Balasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($deliveries as $delivery)
                            <tr class="transition hover:bg-muted/40">
                                <td class="whitespace-nowrap px-5 py-2 text-muted-foreground">{{ $delivery->created_at->format('d M H:i:s') }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $delivery->event }}</td>
                                <td class="px-5 py-2">
                                    <span class="{{ $delivery->delivered_at ? 'text-primary' : 'text-destructive' }}">{{ $delivery->response_code ?? 'gagal' }}</span>
                                </td>
                                <td class="max-w-xs truncate px-5 py-2 text-xs text-muted-foreground">{{ $delivery->response_body }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-section>
    <x-tur-pengenalan kunci="webhook.mulai" :versi="1" :langkah="[
        [
            'target' => '[data-tur=\'webhook-url\']',
            'judul' => 'Alamat yang kami panggil',
            'isi' => 'Kami mengirim POST berisi JSON ke alamat ini setiap kali ada kejadian. '
                .'Alamatnya harus bisa dijangkau dari internet — localhost tidak akan pernah '
                .'menerima apa pun.',
        ],
        [
            'target' => '[data-tur=\'webhook-pemilik\']',
            'judul' => 'Punya beberapa website?',
            'isi' => 'Tautkan webhook ke satu API key, dan status pengiriman hanya sampai ke '
                .'webhook milik API key yang mengirim pesannya. Pesan masuk dan kejadian sesi '
                .'tetap sampai ke semua — keduanya tidak berasal dari integrasi tertentu.',
        ],
        [
            'judul' => 'Verifikasi tanda tangannya',
            'isi' => 'Setiap kiriman membawa header X-Pesanvo-Signature. Cocokkan dengan '
                .'signing secret Anda sebelum memproses isinya — tanpa itu, siapa pun yang tahu '
                .'alamat webhook Anda bisa mengirim data palsu ke sana.',
        ],
    ]" />
@endsection
