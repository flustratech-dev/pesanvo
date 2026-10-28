@props(['judul' => 'Belum ada data', 'pesan' => null, 'kolom' => 1])

{{-- Baris kosong di dalam tabel. Halaman yang benar-benar kosong tanpa
     penjelasan membuat orang mengira ada yang rusak. --}}
<tr>
    <td colspan="{{ $kolom }}" class="px-5 py-12 text-center">
        <p class="text-sm font-medium">{{ $judul }}</p>
        @if ($pesan)
            <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-muted-foreground">{{ $pesan }}</p>
        @endif
        {{ $slot }}
    </td>
</tr>
