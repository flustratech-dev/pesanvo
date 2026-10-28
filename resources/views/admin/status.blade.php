@extends('layouts.admin')
@section('title', 'Status & Insiden')

@section('content')
    {{-- Halaman ini bicara KE LUAR. Apa pun yang ditulis di sini langsung
         terbaca setiap pengunjung /status, termasuk yang belum jadi pelanggan —
         karena itu ia terpisah dari /admin/sistem, yang dipakai mendiagnosis
         ke dalam. --}}

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Insiden berjalan" :nilai="$berjalan->count()" />
        <x-stat label="Komponen sehat"
                :nilai="collect($komponen)->where('keadaan', \App\Support\StatusLayanan::OPERASIONAL)->count().'/'.count($komponen)" />
        <x-stat label="Halaman publik" nilai="/status" :tautan="route('status')" tautanLabel="Buka" />
        <x-stat label="Denyut ke pemantau luar"
                :nilai="filled($heartbeat) ? 'Aktif' : 'Mati'"
                :nada="filled($heartbeat) ? 'netral' : 'bahaya'" />
    </div>

    @if (blank($heartbeat))
        {{-- Diberi spanduk, bukan sekadar ubin abu-abu: selama ini kosong, satu
             kelas gangguan tidak akan pernah dikabari ke siapa pun — dan itu
             justru kelas yang paling parah. --}}
        <x-card class="mb-6 border-amber-500/40 bg-amber-500/5">
            <p class="font-medium">Tidak ada yang mengabari Anda kalau sistem ini mati total.</p>
            <p class="mt-1.5 text-sm text-muted-foreground">
                Notifikasi email dan WhatsApp dikirim oleh aplikasi ini sendiri, jadi keduanya ikut diam
                saat aplikasinya yang mati. Halaman <code>/status</code> pun ikut mati. Yang menutup lubang
                itu hanya pemantau di luar: isi <code>HEARTBEAT_URL</code> dengan alamat denyut dari
                BetterStack Heartbeats, UptimeRobot, atau Healthchecks.io — semuanya gratis untuk pemakaian
                sebesar ini. Aplikasi mengirim denyut tiap menit selama komponen intinya sehat; begitu
                denyutnya berhenti, pemantau yang menghubungi Anda.
            </p>
        </x-card>
    @endif

    <x-section title="Keadaan komponen sekarang" sub="Dibaca langsung, sama dengan yang dilihat pengunjung">
        <div class="divide-y divide-border">
            @foreach ($komponen as $data)
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ $data['nama'] }}</p>
                        <p class="text-xs text-muted-foreground">{{ $data['jelas'] }}</p>
                    </div>
                    <span class="inline-flex items-center gap-2 text-sm">
                        <span class="h-2 w-2 rounded-full {{ $data['keadaan'] === \App\Support\StatusLayanan::OPERASIONAL ? 'bg-emerald-500' : ($data['keadaan'] === \App\Support\StatusLayanan::TERGANGGU ? 'bg-amber-500' : 'bg-red-500') }}"></span>
                        {{ $data['catatan'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </x-section>

    {{-- Insiden berjalan lebih dulu: saat halaman ini dibuka di tengah gangguan,
         yang dicari adalah kotak untuk mengetik kabar terbaru. --}}
    @foreach ($berjalan as $insiden)
        <x-card class="mt-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-semibold">{{ $insiden->title }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ $insiden->summary }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ $insiden->kind === 'pemeliharaan' ? 'Pemeliharaan terjadwal' : 'Gangguan' }} ·
                        {{ $insiden->label() }} ·
                        mulai {{ $insiden->started_at->translatedFormat('j M Y, H:i') }}
                    </p>
                </div>
            </div>

            @if ($insiden->updates->isNotEmpty())
                <ol class="mt-4 space-y-3 border-l border-border pl-4 text-sm">
                    @foreach ($insiden->updates->sortByDesc('created_at') as $kabar)
                        <li>
                            <p class="text-xs text-muted-foreground">{{ $kabar->created_at->translatedFormat('j M, H:i') }}</p>
                            <p>{{ $kabar->body }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif

            <form method="POST" action="{{ route('admin.status.update', $insiden->id) }}"
                  data-validasi data-konfirmasi="Kabar ini langsung tampil di halaman status publik. Kirim?"
                  class="mt-5 space-y-3 border-t border-border pt-4">
                @csrf
                <textarea name="body" rows="2" required maxlength="2000"
                          placeholder="Kabar terbaru untuk pelanggan — apa yang sudah diketahui, apa yang sedang dikerjakan."
                          class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary"></textarea>

                <div class="flex flex-wrap items-center gap-2">
                    <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        @foreach ($daftarStatus as $s)
                            <option value="{{ $s }}" @selected($s === $insiden->status)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        Kirim kabar
                    </button>
                    <span class="text-xs text-muted-foreground">Memilih <strong>selesai</strong> memindahkannya ke riwayat.</span>
                </div>
            </form>
        </x-card>
    @endforeach

    <x-card class="mt-6">
        <h3 class="font-semibold">Umumkan insiden baru</h3>
        <p class="mt-1 text-sm text-muted-foreground">
            Tampil seketika di halaman status publik, di atas seluruh komponen.
        </p>

        <form method="POST" action="{{ route('admin.status.store') }}" data-validasi
              data-konfirmasi="Ini tampil di halaman publik untuk semua orang, termasuk yang belum jadi pelanggan. Umumkan?"
              class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf

            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Judul</label>
                <input type="text" name="title" required maxlength="160"
                       placeholder="Pengiriman pesan tertunda"
                       class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Ringkasan</label>
                <textarea name="summary" rows="3" required maxlength="2000"
                          placeholder="Sebutkan apa yang pelanggan alami, bukan penyebab teknisnya. Yang membaca ingin tahu apakah pesannya hilang atau cuma tertunda."
                          class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary"></textarea>
            </div>

            <div>
                <label class="text-sm font-medium">Komponen</label>
                <select name="component" class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">Seluruh layanan</option>
                    @foreach ($daftarKomponen as $kunci => $meta)
                        <option value="{{ $kunci }}">{{ $meta['nama'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Jenis</label>
                <select name="kind" class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="gangguan">Gangguan</option>
                    <option value="pemeliharaan">Pemeliharaan terjadwal</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Dampak</label>
                <select name="impact" class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="sebagian">Sebagian pelanggan</option>
                    <option value="total">Seluruh pelanggan</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Mulai</label>
                <input type="datetime-local" name="started_at"
                       class="mt-1 w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                <p class="mt-1 text-xs text-muted-foreground">Kosongkan untuk sekarang.</p>
            </div>

            <div class="sm:col-span-2">
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Umumkan
                </button>
            </div>
        </form>
    </x-card>

    <x-section title="Riwayat insiden" :sub="$lampau->count().' tercatat'" class="mt-6">
        <x-tabel>
                <table class="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <th class="py-2 pr-4 font-medium">Judul</th>
                            <th class="py-2 pr-4 font-medium">Jenis</th>
                            <th class="py-2 pr-4 font-medium">Mulai</th>
                            <th class="py-2 pr-4 font-medium">Lama</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($lampau as $insiden)
                            <tr>
                                <td class="py-2.5 pr-4">{{ $insiden->title }}</td>
                                <td class="py-2.5 pr-4 text-muted-foreground">{{ $insiden->kind === 'pemeliharaan' ? 'Pemeliharaan' : 'Gangguan' }}</td>
                                <td class="py-2.5 pr-4 text-muted-foreground">{{ $insiden->started_at->translatedFormat('j M Y, H:i') }}</td>
                                <td class="py-2.5 pr-4 tabular-nums text-muted-foreground">{{ $insiden->started_at->diffInMinutes($insiden->resolved_at) }} menit</td>
                            </tr>
                        @empty
                            <x-kosong :kolom="4" pesan="Belum ada insiden yang tercatat." />
                        @endforelse
                    </tbody>
                </table>
        </x-tabel>
    </x-section>
@endsection
