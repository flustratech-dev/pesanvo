@extends('layouts.admin')
@section('title', 'Lalu Lintas Pesan')

@section('content')
    @php
        $gagal = $ringkasan['failed'] ?? 0;
        $total24 = array_sum($ringkasan);
        $persenGagal = $total24 > 0 ? round($gagal / $total24 * 100, 1) : 0;
    @endphp

    {{-- Yang menentukan ada gangguan atau tidak bukan jumlah kumulatif,
         melainkan berapa yang gagal belakangan ini. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="24 jam terakhir" :nilai="number_format($total24)" sub="pesan tercatat"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />
        <x-stat label="Gagal" :nilai="number_format($gagal)" :sub="$persenGagal.'% dari 24 jam terakhir'"
                :nada="$persenGagal >= 5 ? 'bahaya' : 'netral'"
                ikon="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
        <x-stat label="Mengantre" :nilai="number_format($ringkasan['queued'] ?? 0)" sub="belum diserahkan ke engine"
                :nada="($ringkasan['queued'] ?? 0) > 100 ? 'perhatian' : 'netral'"
                ikon="M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z" />
        <x-stat label="Sampai / dibaca" :nilai="number_format(($ringkasan['delivered'] ?? 0) + ($ringkasan['read'] ?? 0))"
                sub="dikonfirmasi WhatsApp" ikon="M20 6 9 17l-5-5" />
    </div>

    <form method="GET" class="my-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 max-w-sm flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nomor atau isi galat"
                   class="w-full rounded-lg border-border bg-background pl-9 text-sm focus:border-primary focus:ring-primary">
        </div>

        <select name="workspace" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua workspace</option>
            @foreach ($workspaces as $id => $nama)
                <option value="{{ $id }}" @selected((string) $workspaceId === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua status</option>
            @foreach (['queued' => 'Mengantre', 'sending' => 'Mengirim', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'read' => 'Dibaca', 'failed' => 'Gagal'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="arah" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Masuk & keluar</option>
            <option value="outbound" @selected($arah === 'outbound')>Keluar</option>
            <option value="inbound" @selected($arah === 'inbound')>Masuk</option>
        </select>

        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Saring</button>
        @if ($cari !== '' || $status !== '' || $arah !== '' || $workspaceId !== '')
            <a href="{{ route('admin.messages') }}" class="text-sm text-muted-foreground hover:text-foreground">Bersihkan</a>
        @endif
    </form>

    {{-- Isi pesan sengaja tidak ditampilkan: yang dibutuhkan untuk menelusuri
         gangguan adalah status, tujuan, dan galatnya. Isi percakapan pelanggan
         bukan urusan panel operasional. --}}
    <x-section :sub="number_format($messages->total()).' pesan cocok'">
        <table class="w-full min-w-[62rem] text-sm">
            <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Waktu</th>
                    <th class="px-5 py-2.5 font-medium">Workspace</th>
                    <th class="px-5 py-2.5 font-medium">Sesi</th>
                    <th class="px-5 py-2.5 font-medium">Arah</th>
                    <th class="px-5 py-2.5 font-medium">Nomor</th>
                    <th class="px-5 py-2.5 font-medium">Status</th>
                    <th class="px-5 py-2.5 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($messages as $pesan)
                    @php $lm = \App\Support\StatusBadge::message($pesan->status); @endphp
                    <tr class="transition hover:bg-muted/40">
                        <td class="whitespace-nowrap px-5 py-2.5 text-muted-foreground">
                            {{ $pesan->created_at->translatedFormat('j M, H:i:s') }}
                        </td>
                        <td class="px-5 py-2.5">
                            @if ($pesan->workspace)
                                <a href="{{ route('admin.workspaces.show', $pesan->workspace_id) }}" class="hover:underline">{{ $pesan->workspace->name }}</a>
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-muted-foreground">{{ $pesan->session?->name ?? '—' }}</td>
                        <td class="px-5 py-2.5">
                            <x-badge :warna="$pesan->direction === 'inbound' ? 'biru' : 'netral'">
                                {{ $pesan->direction === 'inbound' ? 'masuk' : 'keluar' }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-2.5 font-mono text-xs">
                            {{ $pesan->direction === 'inbound' ? ($pesan->from_number ?? '—') : ($pesan->to_number ?? '—') }}
                        </td>
                        <td class="px-5 py-2.5"><x-badge :warna="$lm['warna']" titik>{{ $lm['label'] }}</x-badge></td>
                        <td class="max-w-xs px-5 py-2.5">
                            @if ($pesan->error)
                                <span class="block truncate text-xs text-destructive" title="{{ $pesan->error }}">{{ $pesan->error }}</span>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-kosong :kolom="7" judul="Tidak ada pesan yang cocok"
                              pesan="Riwayat pesan dipangkas mengikuti masa retensi paket tiap workspace, jadi yang lama memang tidak lagi ada di sini." />
                @endforelse
            </tbody>
        </table>
    </x-section>

    <div class="mt-4">{{ $messages->links() }}</div>
@endsection
