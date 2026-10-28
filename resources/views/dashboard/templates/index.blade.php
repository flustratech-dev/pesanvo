@extends('layouts.app')
@section('title', 'Template Pesan')

@section('content')
@php
    // Ditentukan sekali di atas: formulir di halaman ini hanya ditampilkan
    // kalau langganannya memang berlaku. Penolakan sebenarnya tetap di
    // EnsureSubscriptionActive — ini supaya tombolnya tidak ada sejak awal.
    $terkunci = ($currentSubscription ?? null) && ! $currentSubscription->isUsable();
@endphp

    @if ($terkunci)
        <x-kunci-langganan :subscription="$currentSubscription" aksi="menyimpan template" />
    @else
    <x-card title="Template baru">
        <x-slot:subtitle>
            Tulis placeholder dengan <code>@{{ nama }}</code> — nilainya dikirim lewat API saat pesan dibuat.
        </x-slot:subtitle>
        <form method="POST" action="{{ route('templates.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="name">Nama</label>
                    <input id="name" name="name" required maxlength="60" value="{{ old('name') }}"
                           class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="slug">Slug</label>
                    <input id="slug" name="slug" required maxlength="60" value="{{ old('slug') }}"
                           placeholder="pengingat-invoice" pattern="[a-z0-9\-]+"
                           class="w-full rounded-lg border-input bg-background font-mono text-sm focus:border-primary focus:ring-primary">
                    <p class="mt-1 text-xs text-muted-foreground">Ini yang dipakai di API: <code>"template": "pengingat-invoice"</code></p>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="body">Isi</label>
                <textarea id="body" name="body" rows="5" required maxlength="4096"
                          placeholder="Halo @{{ nama }}, faktur @{{ nomor }} jatuh tempo @{{ tanggal }}."
                          class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">{{ old('body') }}</textarea>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Simpan</button>
        </form>
    </x-card>

    @endif

    {{-- ===================== Template bawaan =====================

         Selalu ada, untuk semua workspace, tanpa perlu dibuat lebih dulu.
         Menyalinnya membuat salinan yang jadi milik workspace sepenuhnya —
         sejak itu ia tidak ikut berubah lagi kalau kami memperbaiki kalimat
         bawaannya, dan itu memang yang diinginkan: orang yang sudah
         menyesuaikannya dengan gaya bahasanya sendiri tidak boleh kehilangannya
         karena kami mengganti satu kata.
         ============================================================= --}}
    <x-section judul="Template bawaan"
               sub="Siap dipakai langsung dari halaman Kirim Pesan. Salin ke sini kalau ingin mengubah kalimatnya.">
        <x-tabel :kepala="['Template' => '', 'Isi' => '', 'Tindakan' => 'text-right']">
            @foreach ($templateBawaan as $kategori => $daftar)
                <tr class="bg-muted/30">
                    <td colspan="3" class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:px-3">
                        {{ $kategori }}
                    </td>
                </tr>
                @foreach ($daftar as $bawaan)
                    <tr class="align-top transition hover:bg-muted/40">
                        <td class="whitespace-nowrap px-4 py-3 font-medium sm:px-3">{{ $bawaan['name'] }}</td>
                        <td class="px-4 py-3 sm:px-3">
                            <pre class="max-w-lg whitespace-pre-wrap break-words font-sans text-xs leading-relaxed text-muted-foreground">{{ $bawaan['body'] }}</pre>
                            @if ($bawaan['variables'] !== [])
                                <p class="mt-1.5 text-xs text-muted-foreground">
                                    Isian:
                                    @foreach ($bawaan['variables'] as $v)
                                        <code class="rounded bg-muted px-1">{{ $v }}</code>@if (! $loop->last), @endif
                                    @endforeach
                                </p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right sm:px-3">
                            <form method="POST" action="{{ route('templates.copy', $bawaan['slug']) }}">
                                @csrf
                                <button class="whitespace-nowrap rounded-lg border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-muted">
                                    {{ in_array($bawaan['slug'], $slugTerpakai, true) ? 'Salin lagi' : 'Salin ke saya' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </x-tabel>
    </x-section>

    <x-section judul="Template tersimpan" rapat>
    <div class="space-y-4">
        @forelse ($templates as $template)
            <div class="border-b border-border pb-4 last:border-0">
                <form method="POST" action="{{ route('templates.update', $template->id) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="flex flex-wrap items-center gap-3">
                        <input name="name" value="{{ $template->name }}" required maxlength="60"
                               class="flex-1 rounded-lg border-input bg-background text-sm font-medium focus:border-primary focus:ring-primary">
                        <code class="rounded bg-muted px-2 py-1 text-xs">{{ $template->slug }}</code>
                        <label class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Aktif
                        </label>
                    </div>
                    <textarea name="body" rows="4" required maxlength="4096"
                              class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">{{ $template->body }}</textarea>
                    @if ($template->variables)
                        <p class="text-xs text-muted-foreground">Variabel: {{ implode(', ', $template->variables) }}</p>
                    @endif
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90">Simpan</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('templates.destroy', $template->id) }}" class="mt-2"
                      data-konfirmasi="Hapus template {{ $template->name }}?">
                    @csrf @method('DELETE')
                    <button class="text-xs text-destructive hover:underline">Hapus template</button>
                </form>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-muted-foreground">
                Belum ada template. Yang Anda simpan di atas akan muncul di sini dan bisa dipanggil lewat API.
            </p>
        @endforelse
    </div>
    </x-section>
@endsection
