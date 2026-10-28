@extends('layouts.admin')
@section('title', 'Tiket')

@section('content')
    {{-- Saringan bawaan "perlu dijawab", pola yang sama dengan halaman Tagihan:
         yang menentukan sebuah baris perlu dilihat manusia bukan seluruh
         daftarnya, melainkan adanya sesuatu yang belum dijawab. --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ([
            'perlu-dijawab' => 'Perlu dijawab'.($jumlahPerluDijawab > 0 ? ' ('.$jumlahPerluDijawab.')' : ''),
            'terbuka' => 'Semua yang terbuka',
            'selesai' => 'Selesai',
            'semua' => 'Semua',
        ] as $nilai => $label)
            <a href="{{ route('admin.tickets', ['saringan' => $nilai]) }}"
               class="rounded-lg border px-3.5 py-1.5 text-sm transition {{ $saringan === $nilai ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border hover:bg-muted' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-section judul="Tiket"
               :sub="$saringan === 'perlu-dijawab' ? 'Terlama di atas — yang paling lama menunggu adalah yang paling mendesak.' : 'Terbaru di atas.'">
        @if ($tickets->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">
                {{ $saringan === 'perlu-dijawab' ? 'Tidak ada tiket yang menunggu jawaban.' : 'Tidak ada tiket.' }}
            </p>
        @else
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">#</th>
                        <th class="px-5 py-2.5 font-medium">Judul</th>
                        <th class="px-5 py-2.5 font-medium">Workspace</th>
                        <th class="px-5 py-2.5 font-medium">Kategori</th>
                        <th class="px-5 py-2.5 font-medium">Prioritas</th>
                        <th class="px-5 py-2.5 font-medium">Keadaan</th>
                        <th class="px-5 py-2.5 font-medium">Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($tickets as $t)
                        <tr class="transition hover:bg-muted/40">
                            <td class="px-5 py-3 text-muted-foreground">{{ $t->id }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.tickets.show', $t->id) }}" class="font-medium hover:underline">{{ $t->subject }}</a>
                                <span class="block text-xs text-muted-foreground">{{ $t->messages_count }} pesan</span>
                            </td>
                            <td class="px-5 py-3">{{ $t->workspace?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-muted-foreground">{{ $t->labelKategori() }}</td>
                            <td class="px-5 py-3">
                                @if ($t->priority === 'high')
                                    <x-badge warna="merah">Tinggi</x-badge>
                                @else
                                    <span class="text-muted-foreground">{{ $t->labelPrioritas() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3"><x-badge :warna="$t->warnaStatus()">{{ $t->labelStatus() }}</x-badge></td>
                            <td class="whitespace-nowrap px-5 py-3 text-muted-foreground">
                                {{ $t->last_reply_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>
@endsection
