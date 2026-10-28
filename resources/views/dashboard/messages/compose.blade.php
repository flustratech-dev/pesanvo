@extends('layouts.app')
@section('title', 'Kirim Pesan')

@section('content')
@php
    $terkunci = ($currentSubscription ?? null) && ! $currentSubscription->isUsable();
@endphp

    @if ($terkunci)
        <x-kunci-langganan :subscription="$currentSubscription" aksi="mengirim pesan" />
    @elseif ($sessions->isEmpty())
        <p class="py-10 text-center text-sm leading-relaxed text-muted-foreground">
            Belum ada sesi yang terhubung.<br>
            <a href="{{ route('sessions.index') }}" class="text-primary hover:underline">Hubungkan satu sesi</a> dulu.
        </p>
    @else
        <div class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <p class="font-semibold">Uji coba pengiriman</p>
            <p class="mb-5 mt-0.5 text-sm leading-relaxed text-muted-foreground">
                Halaman ini memakai jalur yang sama persis dengan REST API — hasilnya bisa dipakai memastikan integrasi bekerja.
            </p>
            <form method="POST" action="{{ route('messages.send') }}" class="space-y-4" x-data="{ body: @js(old('message', '')) }">
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium" for="session_id">Kirim dari</label>
                    <select id="session_id" name="session_id" class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">
                                {{ $session->name }} @if ($session->phone_number) (+{{ $session->phone_number }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="to">Nomor tujuan</label>
                    <textarea id="to" name="to" rows="2" required
                              placeholder="081234567890&#10;081298765432"
                              class="w-full rounded-lg border-input bg-background font-mono text-sm focus:border-primary focus:ring-primary">{{ old('to') }}</textarea>
                    <p class="mt-1 text-xs text-muted-foreground">Bisa lebih dari satu — pisahkan dengan baris baru atau koma. Format 08xx maupun 62xx sama-sama diterima.</p>
                </div>

                {{-- Template bawaan selalu ada, template sendiri menyusul di
                     kelompoknya sendiri. Tanpa yang bawaan, halaman ini kosong
                     bagi pendaftar baru — dan halaman kosong adalah tempat
                     orang berhenti mencoba. --}}
                <div>
                    <label for="template" class="mb-1 block text-sm font-medium">Isi dari template</label>
                    <select id="template"
                            class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary"
                            @change="if ($el.value) body = $el.value">
                        <option value="">— tulis sendiri —</option>

                        @if ($templates->isNotEmpty())
                            <optgroup label="Template workspace ini">
                                @foreach ($templates as $template)
                                    <option value="{{ $template->body }}">{{ $template->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif

                        @foreach ($templateBawaan as $kategori => $daftar)
                            <optgroup label="Bawaan · {{ $kategori }}">
                                @foreach ($daftar as $bawaan)
                                    <option value="{{ $bawaan['body'] }}">{{ $bawaan['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                        Bagian di dalam <code>@{{ kurung }}</code> adalah isian yang harus Anda ganti sebelum kirim.
                        Yang tidak diganti akan terkirim apa adanya —
                        <a href="{{ route('templates.index') }}" class="text-primary hover:underline">salin ke template sendiri</a>
                        kalau ingin mengubahnya permanen.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="message">Pesan</label>
                    <textarea id="message" name="message" rows="6" required maxlength="4096" x-model="body"
                              class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary"></textarea>
                    <p class="mt-1 text-xs text-muted-foreground"><span x-text="body.length"></span> / 4096 karakter</p>
                </div>

                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Kirim</button>
            </form>
        </div>

        <x-section judul="Catatan pengiriman massal" rapat>
            <p class="text-sm leading-relaxed text-muted-foreground">
                Setiap pesan diberi jeda acak
                {{ config('gateway.throttle.min_delay_ms') / 1000 }}–{{ config('gateway.throttle.max_delay_ms') / 1000 }} detik
                sebelum dikirim. Jeda ini disengaja: mengirim ratusan pesan beruntun tanpa jeda adalah pola tercepat
                membuat nomor diblokir WhatsApp. Broadcast besar wajar berjalan berjam-jam.
            </p>
        </x-section>
    @endif
@endsection
