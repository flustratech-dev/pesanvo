@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    @php
        $ls = \App\Support\StatusBadge::subscription($currentWorkspace->status);
        $bolehKelola = auth()->user()->canManage($currentWorkspace);
    @endphp

    {{-- ===================== Angka =====================

         Satu-satunya baris berkotak di halaman ini; sisanya mengalir langsung
         di atas latar, dipisah garis dan jarak.
         ============================================================= --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Workspace" :nilai="$currentWorkspace->name" :sub="$currentWorkspace->slug"
                ikon="M3 7h18M3 12h18M3 17h18" />

        {{-- plan() membaca langganan lebih dulu, baru kolomnya: kolom plan_slug
             baru terisi saat tagihan pertama lunas. --}}
        <x-stat label="Paket" :nilai="$currentWorkspace->plan()->name()"
                :sub="$ls['label']"
                :tautan="route('billing.index')" tautanLabel="Kelola langganan"
                ikon="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />

        <x-stat label="Pesan bulan ini" :nilai="number_format($usage->messages_sent)"
                :sub="'periode '.$usage->period"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />

        <x-stat label="Anggota tim" :nilai="$members->count()"
                sub="termasuk owner workspace"
                ikon="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </div>

    {{-- ===================== Batas paket ===================== --}}
    <x-section judul="Batas yang berlaku"
               sub="Mengikuti langganan dan tidak bisa diubah dari sini, supaya tagihan dan batas pemakaian tidak pernah berbeda.">
        <x-tabel :kepala="['Batas' => '', 'Berlaku' => 'text-right']">
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Nomor WhatsApp</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $currentWorkspace->max_sessions }}</td>
            </tr>
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Kuota pesan per bulan</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">
                    {{ $currentWorkspace->isExempt() ? 'Tanpa batas (bebas tagihan)' : number_format($currentWorkspace->monthly_message_quota) }}
                </td>
            </tr>
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Batas permintaan API</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $currentWorkspace->api_rate_limit_per_minute }}/menit</td>
            </tr>
        </x-tabel>
    </x-section>

    {{-- ===================== Identitas workspace ===================== --}}
    @if ($bolehKelola)
        <x-section judul="Identitas workspace" rapat>
            <form method="POST" action="{{ route('settings.update') }}" class="max-w-xl">
                @csrf
                @method('PUT')

                <label class="mb-1 block text-sm font-medium" for="workspace-name">Nama workspace</label>
                <div class="flex flex-wrap gap-2">
                    <input id="workspace-name" name="name" required maxlength="80"
                           value="{{ old('name', $currentWorkspace->name) }}"
                           class="min-w-48 flex-1 rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                </div>
                @error('name')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Slug <code>{{ $currentWorkspace->slug }}</code> sengaja tidak ikut berubah — ia dipakai
                    sebagai pengenal tetap di catatan audit. Tidak ada API key atau URL yang bergantung padanya.
                </p>

                <label class="mb-1 mt-5 block text-sm font-medium" for="billing-phone">Nomor WhatsApp untuk tagihan</label>
                <input id="billing-phone" name="billing_phone" maxlength="20" inputmode="tel"
                       value="{{ old('billing_phone', $currentWorkspace->billing_phone) }}"
                       placeholder="08xxxxxxxxxx"
                       class="w-full max-w-xs rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                @error('billing_phone')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Ke sinilah pengingat masa berlaku langganan dikirim, beberapa hari sebelum habis.
                    Berbeda dari nomor sesi Anda — nomor ini justru paling dibutuhkan saat sesinya sedang mati.
                    Kosongkan kalau Anda memilih hanya mengandalkan pemberitahuan di dashboard.
                </p>

                <button class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Simpan perubahan
                </button>
            </form>
        </x-section>
    @endif

    {{-- ===================== Anggota ===================== --}}
    <x-section judul="Anggota" sub="Anggota harus sudah pernah login ke gateway ini sebelum bisa ditambahkan.">
        <form method="POST" action="{{ route('settings.members.add') }}" class="mb-5 flex flex-wrap items-end gap-3 px-4 sm:px-0">
            @csrf
            <div class="min-w-56 flex-1">
                <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                <input id="email" name="email" type="email" required
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="role">Peran</label>
                <select id="role" name="role" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="member">Member — hanya melihat &amp; mengirim</option>
                    <option value="admin">Admin — bisa kelola sesi &amp; kunci</option>
                </select>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Tambah</button>
        </form>

        <x-tabel :kepala="['Nama' => '', 'Email' => '', 'Peran' => '', 'Tindakan' => 'text-right']">
            @foreach ($members as $member)
                <tr class="transition hover:bg-muted/40">
                    <td class="px-4 py-2.5 font-medium sm:px-3">{{ $member->name }}</td>
                    <td class="break-all px-4 py-2.5 text-muted-foreground sm:px-3">{{ $member->email }}</td>
                    <td class="px-4 py-2.5 sm:px-3">
                        <x-badge :warna="$member->pivot->role === 'owner' ? 'hijau' : 'netral'">
                            {{ ucfirst($member->pivot->role) }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-2.5 text-right sm:px-3">
                        @if ($member->pivot->role !== 'owner')
                            <form method="POST" action="{{ route('settings.members.remove', $member->id) }}"
                                  data-konfirmasi="Keluarkan {{ $member->name }} dari workspace ini?">
                                @csrf @method('DELETE')
                                <button class="text-xs text-destructive hover:underline">Keluarkan</button>
                            </form>
                        @else
                            <span class="text-xs text-muted-foreground">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabel>
    </x-section>

    {{-- ===================== Riwayat pemakaian ===================== --}}
    <x-section judul="Riwayat pemakaian" sub="Dihitung ulang tiap awal bulan; sisa kuota tidak dibawa ke bulan berikutnya.">
        <x-tabel :kepala="['Periode' => '', 'Keluar' => 'text-right', 'Masuk' => 'text-right', 'Gagal' => 'text-right']">
            @forelse ($history as $row)
                <tr class="transition hover:bg-muted/40">
                    <td class="px-4 py-2.5 font-medium sm:px-3">{{ $row->period }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums sm:px-3">{{ number_format($row->messages_sent) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums sm:px-3">{{ number_format($row->messages_received) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums sm:px-3 {{ $row->messages_failed > 0 ? 'text-destructive' : '' }}">
                        {{ number_format($row->messages_failed) }}
                    </td>
                </tr>
            @empty
                <x-kosong :kolom="4" judul="Belum ada pemakaian tercatat"
                          pesan="Baris pertama muncul setelah pesan pertama terkirim dari workspace ini." />
            @endforelse
        </x-tabel>
    </x-section>

    {{-- ===================== Ekspor data =====================

         Ditaruh TEPAT SEBELUM zona hapus workspace dengan sengaja: yang sedang
         menimbang menghapus workspace hampir selalu belum menyimpan riwayatnya,
         dan "unduh dulu datamu" harus terbaca sebelum tombol merahnya, bukan
         sesudah. Retensi memangkas pesan otomatis dan tidak bisa dibatalkan —
         halaman ini satu-satunya cara menyelamatkannya.
         ========================================================= --}}
    <x-section title="Ekspor data workspace"
               sub="Salinan lengkap milik Anda, dalam format yang bisa dibuka aplikasi lain">
        <p class="text-sm text-muted-foreground">
            Berisi data workspace, anggota, nomor WhatsApp, webhook, template, riwayat tagihan, dan
            <strong>seluruh pesan</strong> yang masih tersimpan sebagai CSV. Kami mengabari Anda lewat
            notifikasi begitu berkasnya siap; tautannya berlaku 7 hari lalu berkasnya kami hapus.
        </p>

        <p class="mt-2 text-sm text-muted-foreground">
            Nilai API key dan kredensial WhatsApp <strong>tidak</strong> disertakan — berkas ini berpindah
            lewat email dan chat, dan kunci di dalamnya adalah kunci yang bocor tanpa Anda pernah tahu.
        </p>

        <form method="POST" action="{{ route('settings.export') }}" class="mt-4"
              data-konfirmasi="Berkasnya memuat seluruh isi percakapan workspace ini. Susun sekarang?">
            @csrf
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                Minta ekspor data
            </button>
            <span class="ml-2 text-xs text-muted-foreground">Satu permintaan per 24 jam.</span>
        </form>

        @if ($ekspor->isNotEmpty())
            <div class="mt-5 divide-y divide-border border-t border-border">
                @foreach ($ekspor as $berkas)
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3 text-sm">
                        <div>
                            <p>{{ $berkas->created_at->translatedFormat('j F Y, H:i') }} WIB</p>
                            <p class="text-xs text-muted-foreground">
                                @switch($berkas->status)
                                    @case('menunggu') Menunggu giliran disusun @break
                                    @case('diproses') Sedang disusun @break
                                    @case('siap')
                                        {{ $berkas->ukuranTerbaca() }} ·
                                        @if ($berkas->bisaDiunduh())
                                            berlaku sampai {{ $berkas->expires_at->translatedFormat('j F Y') }}
                                        @else
                                            tautan sudah kedaluwarsa
                                        @endif
                                        @break
                                    @case('kedaluwarsa') Berkas sudah kami hapus @break
                                    @default Gagal disusun. Silakan minta lagi, atau hubungi kami lewat Bantuan.
                                @endswitch
                            </p>
                        </div>

                        @if ($berkas->bisaDiunduh())
                            <a href="{{ route('settings.export.download', $berkas->id) }}"
                               class="rounded-lg border border-border px-3 py-1.5 text-sm transition hover:bg-muted">
                                Unduh
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-section>

    {{-- ===================== Hapus workspace =====================

         Tetap berbingkai, dan merah: ini satu-satunya tindakan di halaman ini
         yang tidak bisa dibatalkan.
         ============================================================= --}}
    @if (auth()->user()->is_super_admin || auth()->user()->roleIn($currentWorkspace) === 'owner')
        <div class="mt-10 rounded-xl border border-destructive/40 bg-destructive/5 p-5">
            <p class="font-semibold text-destructive">Hapus workspace</p>
            <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Sesi, riwayat pesan, template, webhook, dan seluruh API key milik workspace ini ikut hilang.
                Nomor yang sedang tertaut akan diputus lebih dulu, dan aplikasi mana pun yang memakai API key
                workspace ini langsung berhenti bisa mengirim. Kalau yang Anda mau hanya mengganti nama,
                pakai kolom <strong>Identitas workspace</strong> di atas — tidak perlu menghapus apa pun.
            </p>

            <form method="POST" action="{{ route('settings.destroy') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <label class="mb-1 block text-sm font-medium" for="confirm">
                    Ketik <code class="rounded bg-muted px-1">{{ $currentWorkspace->name }}</code> untuk memastikan
                </label>
                <div class="flex flex-wrap gap-2">
                    <input id="confirm" name="confirm" required autocomplete="off"
                           placeholder="{{ $currentWorkspace->name }}"
                           class="min-w-56 flex-1 rounded-lg border-input bg-background text-sm focus:border-destructive focus:ring-destructive">
                    <button class="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90">
                        Hapus workspace
                    </button>
                </div>
                @error('confirm')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </form>
        </div>
    @endif
@endsection
