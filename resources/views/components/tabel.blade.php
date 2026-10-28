@props(['kepala' => []])

{{-- Tabel polos: garis pemisah antar baris, tanpa bingkai luar.

     Kepala tabel diberi latar samar supaya tetap terbaca sebagai kepala saat
     tabelnya panjang, tapi tanpa kotak di sekelilingnya — bingkai ganda (kotak
     panel + garis tabel) adalah yang membuat halaman terasa penuh. --}}
<table {{ $attributes->merge(['class' => 'w-full text-sm']) }}>
    @if ($kepala !== [])
        <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
            <tr>
                @foreach ($kepala as $judul => $kelas)
                    @if (is_int($judul))
                        <th class="px-4 py-2.5 font-medium sm:px-3">{{ $kelas }}</th>
                    @else
                        <th class="px-4 py-2.5 font-medium sm:px-3 {{ $kelas }}">{{ $judul }}</th>
                    @endif
                @endforeach
            </tr>
        </thead>
    @endif

    <tbody class="divide-y divide-border">
        {{ $slot }}
    </tbody>
</table>
