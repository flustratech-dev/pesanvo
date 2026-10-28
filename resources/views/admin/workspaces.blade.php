@extends('layouts.admin')
@section('title', 'Workspace')

@section('content')
    {{-- ===================== Saringan =====================

         Daftar ini untuk MENCARI, bukan untuk bertindak. Setiap barisnya menuju
         halaman workspace sendiri. Sebelumnya tiap baris bisa dibuka menjadi
         panel berisi empat form sekaligus — daftar yang berubah bentuk saat
         disentuh sulit dipindai, dan form yang bersembunyi di dalam baris
         membuat orang tidak yakin sedang mengubah workspace yang mana.
         ============================================================= --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 max-w-sm flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama, slug, atau email pemilik"
                   class="w-full rounded-lg border-border bg-background pl-9 text-sm focus:border-primary focus:ring-primary">
        </div>
        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua status</option>
            @foreach (['unpaid' => 'Belum berlangganan', 'trialing' => 'Masa percobaan', 'active' => 'Aktif', 'past_due' => 'Lewat jatuh tempo', 'suspended' => 'Ditangguhkan', 'canceled' => 'Dihentikan'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Cari</button>
        @if ($cari !== '' || $status !== '')
            <a href="{{ route('admin.workspaces') }}" class="text-sm text-muted-foreground hover:text-foreground">Bersihkan</a>
        @endif
    </form>

    <x-section :sub="$workspaces->total().' workspace'">
        <table class="w-full min-w-[64rem] text-sm">
            <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Workspace</th>
                    <th class="px-5 py-2.5 font-medium">Paket</th>
                    <th class="px-5 py-2.5 font-medium">Status</th>
                    <th class="px-5 py-2.5 text-right font-medium">Sesi</th>
                    <th class="px-5 py-2.5 text-right font-medium">Pesan bulan ini</th>
                    <th class="px-5 py-2.5 font-medium">Berlaku sampai</th>
                    <th class="px-5 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($workspaces as $workspace)
                    @php
                        $sub = $workspace->subscription;
                        $lencana = \App\Support\StatusBadge::subscription($sub?->status);
                        $terpakai = $pemakaian[$workspace->id] ?? 0;
                        $kuota = (int) $workspace->monthly_message_quota;
                        $penuh = $kuota > 0 && $terpakai >= $kuota;
                        $sesiPenuh = $workspace->sessions_count >= $workspace->max_sessions;
                    @endphp

                    <tr class="transition hover:bg-muted/40">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.workspaces.show', $workspace->id) }}" class="group flex items-center gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                    {{ mb_strtoupper(mb_substr($workspace->name, 0, 2)) }}
                                </span>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5">
                                        <span class="truncate font-medium group-hover:underline">{{ $workspace->name }}</span>
                                        @if ($workspace->is_internal)
                                            <x-badge warna="biru">internal</x-badge>
                                        @endif
                                    </span>
                                    <span class="block truncate text-xs text-muted-foreground">
                                        {{ $workspace->owner_email ?? $workspace->owner?->email ?? '—' }}
                                    </span>
                                </span>
                            </a>
                        </td>

                        <td class="px-5 py-3">{{ $workspace->plan()->name() }}</td>

                        <td class="px-5 py-3">
                            <x-badge :warna="$lencana['warna']" titik>{{ $lencana['label'] }}</x-badge>
                        </td>

                        <td class="px-5 py-3 text-right tabular-nums {{ $sesiPenuh ? 'text-amber-600 dark:text-amber-400' : '' }}">
                            {{ $workspace->sessions_count }}/{{ $workspace->max_sessions }}
                        </td>

                        <td class="px-5 py-3 text-right tabular-nums {{ $penuh ? 'font-medium text-destructive' : '' }}">
                            {{ number_format($terpakai) }}<span class="text-muted-foreground">/{{ $kuota === 0 ? '∞' : number_format($kuota) }}</span>
                        </td>

                        <td class="px-5 py-3 text-muted-foreground">
                            {{ $sub?->current_period_end?->translatedFormat('j M Y') ?? '—' }}
                        </td>

                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.workspaces.show', $workspace->id) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-1 text-xs font-medium transition hover:bg-muted">
                                Kelola
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-kosong :kolom="7" judul="Tidak ada workspace yang cocok"
                              pesan="Coba ubah kata kunci atau saringan statusnya." />
                @endforelse
            </tbody>
        </table>
    </x-section>

    <div class="mt-4">{{ $workspaces->links() }}</div>
@endsection
