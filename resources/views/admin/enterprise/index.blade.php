@extends('layouts.admin')
@section('title', 'Enterprise')

@section('content')
    {{-- Saringan bawaan "baru", pola yang sama dengan Tagihan dan Tiket:
         permintaan penawaran yang tenggelam adalah calon pelanggan terbesar
         yang pergi tanpa pernah dijawab. --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ([
            'baru' => 'Baru'.($jumlahBaru > 0 ? ' ('.$jumlahBaru.')' : ''),
            'diproses' => 'Sedang diproses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            'semua' => 'Semua',
        ] as $nilai => $label)
            <a href="{{ route('admin.enterprise', ['saringan' => $nilai]) }}"
               class="rounded-lg border px-3.5 py-1.5 text-sm transition {{ $saringan === $nilai ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border hover:bg-muted' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-section judul="Permintaan penawaran"
               :sub="$saringan === 'baru' ? 'Terlama di atas — yang paling lama menunggu adalah yang paling mungkin sudah pergi ke tempat lain.' : 'Terbaru di atas.'">
        @if ($leads->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">
                {{ $saringan === 'baru' ? 'Tidak ada permintaan yang menunggu dijawab.' : 'Tidak ada permintaan.' }}
            </p>
        @else
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Pemohon</th>
                        <th class="px-5 py-2.5 font-medium">Kontak</th>
                        <th class="px-5 py-2.5 text-right font-medium">Nomor</th>
                        <th class="px-5 py-2.5 text-right font-medium">Pesan/bln</th>
                        <th class="px-5 py-2.5 font-medium">Keadaan</th>
                        <th class="px-5 py-2.5 font-medium">Masuk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($leads as $lead)
                        <tr class="transition hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.enterprise.show', $lead->id) }}" class="font-medium hover:underline">
                                    {{ $lead->name }}
                                </a>
                                <span class="block text-xs text-muted-foreground">{{ $lead->company ?: 'perorangan' }}</span>
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $lead->email }}
                                <span class="block text-xs">{{ $lead->phone }}</span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $lead->estimated_sessions ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ $lead->estimated_messages ? number_format($lead->estimated_messages, 0, ',', '.') : '—' }}
                            </td>
                            <td class="px-5 py-3"><x-badge :warna="$lead->warnaStatus()">{{ $lead->labelStatus() }}</x-badge></td>
                            <td class="whitespace-nowrap px-5 py-3 text-muted-foreground">{{ $lead->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>

    {{-- ===================== Kesepakatan yang berlaku =====================

         Dipisah dari daftar permintaan karena keduanya menjawab pertanyaan yang
         berbeda: yang di atas "siapa yang perlu dijawab", yang di sini "apa
         yang sedang kita janjikan kepada siapa". Yang kedua adalah hal yang
         perlu dilihat saat memutuskan kapasitas, bukan saat membalas surat.
         ================================================================= --}}
    <x-section judul="Kesepakatan yang berlaku"
               sub="Batas ini baru benar-benar tersalin ke workspace setelah tagihannya lunas — sama seperti paket biasa."
               class="mt-5">
        @if ($kesepakatan->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Belum ada kesepakatan Enterprise.</p>
        @else
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Kesepakatan</th>
                        <th class="px-5 py-2.5 font-medium">Workspace</th>
                        <th class="px-5 py-2.5 text-right font-medium">Bulanan</th>
                        <th class="px-5 py-2.5 text-right font-medium">Tahunan</th>
                        <th class="px-5 py-2.5 text-right font-medium">Nomor</th>
                        <th class="px-5 py-2.5 text-right font-medium">Kuota</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($kesepakatan as $k)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $k->name }}</td>
                            <td class="px-5 py-3">{{ $k->workspace?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">Rp {{ number_format($k->price_monthly, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">Rp {{ number_format($k->price_yearly, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $k->max_sessions }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ $k->monthly_message_quota === 0 ? '∞' : number_format($k->monthly_message_quota, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($k->lead_id)
                                    <a href="{{ route('admin.enterprise.show', $k->lead_id) }}"
                                       class="rounded-lg border border-border px-3 py-1.5 text-xs transition hover:bg-muted">Buka</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>
@endsection
