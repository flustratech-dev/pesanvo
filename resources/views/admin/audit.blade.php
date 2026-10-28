@extends('layouts.admin')
@section('title', 'Catatan Audit')

@section('content')
    {{-- Catatan ini sudah lama ditulis oleh hampir setiap tindakan penting, tapi
         sampai sekarang hanya bisa dibaca lewat database. Padahal justru inilah
         yang menjawab pertanyaan tersulit belakangan: siapa membatalkan tagihan
         itu, kapan buktinya masuk, siapa mengubah batas workspace ini. --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 max-w-sm flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari tindakan, workspace, email, atau IP"
                   class="w-full rounded-lg border-border bg-background pl-9 text-sm focus:border-primary focus:ring-primary">
        </div>

        <select name="kelompok" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua tindakan</option>
            @foreach ($kelompokTersedia as $nama)
                <option value="{{ $nama }}" @selected($kelompok === $nama)>{{ $nama }}</option>
            @endforeach
        </select>

        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Saring</button>
        @if ($cari !== '' || $kelompok !== '')
            <a href="{{ route('admin.audit') }}" class="text-sm text-muted-foreground hover:text-foreground">Bersihkan</a>
        @endif
    </form>

    <x-section :sub="number_format($entri->total()).' catatan'">
        <table class="w-full min-w-[62rem] text-sm">
            <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Waktu</th>
                    <th class="px-5 py-2.5 font-medium">Tindakan</th>
                    <th class="px-5 py-2.5 font-medium">Oleh</th>
                    <th class="px-5 py-2.5 font-medium">Workspace</th>
                    <th class="px-5 py-2.5 font-medium">Rincian</th>
                    <th class="px-5 py-2.5 font-medium">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($entri as $baris)
                    @php
                        $adminAksi = str_starts_with($baris->action, 'admin.');
                        $uang = str_starts_with($baris->action, 'invoice.');
                    @endphp
                    <tr class="transition hover:bg-muted/40">
                        <td class="whitespace-nowrap px-5 py-2.5 text-muted-foreground">
                            {{ $baris->created_at->translatedFormat('j M Y, H:i:s') }}
                        </td>
                        <td class="px-5 py-2.5">
                            <x-badge :warna="$adminAksi ? 'kuning' : ($uang ? 'hijau' : 'netral')">
                                <span class="font-mono">{{ $baris->action }}</span>
                            </x-badge>
                        </td>
                        <td class="px-5 py-2.5">
                            {{ $baris->user?->email ?? 'sistem' }}
                        </td>
                        <td class="px-5 py-2.5">
                            @if ($baris->workspace)
                                <a href="{{ route('admin.workspaces.show', $baris->workspace_id) }}" class="hover:underline">{{ $baris->workspace->name }}</a>
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="max-w-sm px-5 py-2.5">
                            @if ($baris->context)
                                <span class="block truncate font-mono text-xs text-muted-foreground"
                                      title="{{ json_encode($baris->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
                                    {{ collect($baris->context)->map(fn ($v, $k) => $k.'='.(is_scalar($v) ? $v : json_encode($v)))->implode(' · ') }}
                                </span>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 font-mono text-xs text-muted-foreground">{{ $baris->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <x-kosong :kolom="6" judul="Tidak ada catatan yang cocok"
                              pesan="Catatan audit tidak pernah dihapus otomatis — kalau kosong, memang belum ada tindakan yang tercatat." />
                @endforelse
            </tbody>
        </table>
    </x-section>

    <div class="mt-4">{{ $entri->links() }}</div>
@endsection
