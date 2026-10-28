@extends('layouts.app')
@section('title', 'Riwayat tagihan')

@section('content')
    @include('dashboard.billing._nav')

    @if ($invoices->isEmpty())
        <p class="py-10 text-center text-sm leading-relaxed text-muted-foreground">
            Belum ada tagihan.<br>Tagihan pertama terbit beberapa hari sebelum masa berlaku Anda habis.
        </p>
    @else
        <x-section>
                <table class="w-full min-w-[42rem] text-sm">
                    <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nomor</th>
                            <th class="px-5 py-3 font-medium">Paket</th>
                            <th class="px-5 py-3 font-medium">Jumlah</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($invoices as $invoice)
                            <tr class="transition hover:bg-muted/40">
                                <td class="px-5 py-3 font-medium">{{ $invoice->number }}</td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ $invoice->plan()->name() }} · {{ $invoice->periodLabel() }}
                                </td>
                                <td class="px-5 py-3 tabular-nums">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    @php $li = \App\Support\StatusBadge::invoice($invoice->status); @endphp
                                    <x-badge :warna="$li['warna']">{{ $li['label'] }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ ($invoice->paid_at ?? $invoice->created_at)->translatedFormat('j M Y') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    {{-- Tagihan lunas tetap bisa dibuka: rinciannya
                                         adalah bukti pembayaran yang sewaktu-waktu
                                         dibutuhkan untuk pembukuan. --}}
                                    <a href="{{ route('billing.invoice', $invoice->id) }}"
                                       class="text-primary hover:underline">
                                        {{ $invoice->isPending() ? 'Bayar' : 'Rincian' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        </x-section>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
@endsection
