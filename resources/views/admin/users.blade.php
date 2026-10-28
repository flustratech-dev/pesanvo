@extends('layouts.admin')
@section('title', 'Pengguna')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama atau email"
               class="w-full max-w-sm rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">Cari</button>
    </form>

    <x-section>
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2 font-medium">Nama</th>
                        <th class="px-5 py-2 font-medium">Email</th>
                        <th class="px-5 py-2 font-medium">Workspace</th>
                        <th class="px-5 py-2 font-medium">Terakhir masuk</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-muted/40">
                            <td class="px-5 py-2.5 font-medium">
                                {{ $user->name }}
                                @if ($user->is_super_admin)
                                    <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">super admin</span>
                                @endif
                                @if ($user->is_exempt)
                                    <span class="ml-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">bebas tagihan</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-muted-foreground">{{ $user->email }}</td>
                            <td class="px-5 py-2.5">{{ $user->workspaces_count }}</td>
                            <td class="px-5 py-2.5 text-muted-foreground">
                                {{ $user->last_login_at?->translatedFormat('j M Y, H:i') ?? 'belum pernah' }}
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    {{-- Satu-satunya jalan kembali bagi pelanggan yang
                                         terkunci: pemulihan mandiri sengaja belum ada,
                                         dan halaman masuk menyuruh mereka menghubungi kami. --}}
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user->id) }}"
                                          data-konfirmasi="Buat kata sandi baru untuk {{ $user->email }}? Kata sandi lamanya langsung tidak berlaku, dan semua sesi 'ingat saya' miliknya ikut dikeluarkan."
                                          data-konfirmasi-ya="Ya, atur ulang">
                                        @csrf
                                        <button class="text-muted-foreground hover:text-foreground hover:underline">Atur ulang sandi</button>
                                    </form>

                                    {{-- Pembebasan ditandai per ORANG, bukan per
                                         workspace: workspace yang ia buat besok ikut
                                         bebas tanpa ada yang perlu ingat menandainya
                                         lagi. Daftar lengkapnya di menu Pengecualian. --}}
                                    <form method="POST" action="{{ route('admin.exemptions.users.toggle', $user->id) }}"
                                          data-konfirmasi="{{ $user->is_exempt
                                              ? 'Cabut pembebasan untuk '.$user->email.'? Seluruh workspace miliknya kembali ditagih seperti pelanggan biasa.'
                                              : 'Bebaskan '.$user->email.' dari penagihan? Seluruh workspace miliknya — termasuk yang dibuat nanti — bisa dipakai penuh tanpa berlangganan.' }}">
                                        @csrf
                                        <button class="hover:underline {{ $user->is_exempt ? 'text-destructive' : 'text-muted-foreground hover:text-foreground' }}">
                                            {{ $user->is_exempt ? 'Cabut bebas tagihan' : 'Bebaskan tagihan' }}
                                        </button>
                                    </form>

                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.super', $user->id) }}"
                                          data-konfirmasi="Ubah hak super admin untuk {{ $user->email }}?">
                                        @csrf
                                        <button class="hover:underline {{ $user->is_super_admin ? 'text-destructive' : 'text-primary' }}">
                                            {{ $user->is_super_admin ? 'Cabut' : 'Jadikan super admin' }}
                                        </button>
                                    </form>
                                @else
                                    {{-- Hak diri sendiri tidak bisa dicabut dari sini: orang
                                         terakhir yang melakukannya mengunci panel dari luar. --}}
                                    <span class="text-xs text-muted-foreground">Anda</span>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-kosong :kolom="5" judul="Tidak ada pengguna yang cocok" pesan="Coba ubah kata kuncinya." />
                    @endforelse
                </tbody>
            </table>
    </x-section>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
