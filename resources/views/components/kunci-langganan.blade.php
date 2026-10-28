@props(['subscription', 'aksi' => 'memakai fitur ini'])

@php $alasan = $subscription->alasanNomorBerhenti(); @endphp

{{-- Pengganti formulir saat langganan belum/tidak berlaku.

     `EnsureSubscriptionActive` sudah menolak setiap POST dari workspace yang
     belum membayar, jadi tanpa blok ini pun tidak ada yang bisa dipakai gratis.
     Yang diperbaiki di sini bukan keamanannya melainkan urutannya: sebelumnya
     formulirnya tetap tampil lengkap dengan tombolnya, dan pengguna baru tahu
     dirinya terkunci **setelah** mengisi nama sesi dan menekan Buat — lalu
     dilempar ke halaman lain tanpa isian yang tadi diketiknya. Tombol yang
     hanya bisa gagal lebih buruk daripada tombol yang tidak ada. --}}
@php
    $belumPernah = $subscription->isUnpaid();
@endphp

{{-- Bergaris putus-putus dan tanpa warna: spanduk langganan di layout sudah
     berwarna, dan dua kotak hijau berturut-turut yang mengatakan hal yang sama
     terbaca sebagai satu pesan yang tercetak dua kali. Yang ini bukan
     peringatan melainkan tempat kosong — persis di posisi formulir yang tidak
     ditampilkan. --}}
<div class="rounded-xl border border-dashed border-border bg-muted/30 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <p class="font-semibold">
                {{ $belumPernah ? 'Pilih paket dulu sebelum '.$aksi : 'Langganan tidak aktif, jadi '.$aksi.' sedang ditutup' }}
            </p>
            <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                @if ($belumPernah)
                    Workspace ini belum pernah berlangganan. Nomor WhatsApp, API key, dan pengiriman
                    pesan terbuka begitu tagihan pertama Anda ditandai lunas.
                @else
                    Yang sudah ada tetap tersimpan dan tetap bisa Anda lihat. Perpanjang untuk
                    membukanya kembali.
                @endif
            </p>
        </div>

        <a href="{{ route($belumPernah ? 'billing.plans' : 'billing.index') }}"
           class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
            {{ $belumPernah ? 'Pilih paket' : 'Perpanjang langganan' }}
        </a>
    </div>

    {{-- Nasib nomor yang SUDAH tertaut — pertanyaan yang berbeda dari "kenapa
         saya tidak bisa menambah", dan yang jauh lebih mendesak. Digabung ke
         satu blok, bukan spanduk sendiri: tiga kotak beruntun yang mengatakan
         hal yang sama membuat ketiganya berhenti dibaca. --}}
    @if ($alasan)
        <div class="mt-4 border-t border-border pt-4">
            <p class="text-sm font-medium">{{ $alasan['judul'] }}</p>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">{{ $alasan['pesan'] }}</p>
        </div>
    @endif
</div>
