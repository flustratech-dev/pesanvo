@extends('layouts.app')
@section('title', 'Langganan')

@section('content')
    @include('dashboard.billing._nav')

    @php
        $sisa = $subscription->daysRemaining();
        $paket = $subscription->plan();
        $ls = \App\Support\StatusBadge::subscription($subscription->status);
        $kuota = (int) $currentWorkspace->monthly_message_quota;
        $persen = $kuota > 0 ? min(100, round($usage->messages_sent / max($kuota, 1) * 100)) : null;
        $sesiTerpakai = $currentWorkspace->sessions()->count();
        $gratis = $subscription->isFreeTier();
        $terpakaiGratis = $gratis ? $currentWorkspace->freeMessagesUsed() : 0;

        // Disusun di PHP, bukan di dalam atribut Blade: kutip ganda di dalam
        // atribut ber-kutip ganda memotong nilainya diam-diam.
        // Masa coba tidak punya tanggal berakhir sama sekali; tanpa cabang ini
        // `daysRemaining()` menjawab 0 dan ubinnya mengumumkan "berakhir hari
        // ini" untuk langganan yang justru tidak akan pernah berakhir.
        $subSisa = match (true) {
            $gratis => 'tidak ada batas waktu, hanya batas jumlah pesan',
            $subscription->isUsable() && $sisa >= 0 => $sisa === 0
                ? 'berakhir hari ini'
                : ($sisa === 1 ? 'tinggal 1 hari' : 'tinggal '.$sisa.' hari'),
            $subscription->current_period_end !== null => 'masa berlakunya sudah lewat',
            default => 'belum ada masa berlaku berjalan',
        };

        $tanggalAkhir = $subscription->current_period_end && ! $subscription->isUnpaid()
            ? $subscription->current_period_end->translatedFormat('j M Y')
            : ($gratis ? 'Tanpa batas waktu' : '—');

        $nadaSisa = ! $gratis && $subscription->isUsable() && $sisa >= 0 && $sisa <= 7 ? 'perhatian' : 'netral';
    @endphp

    {{-- ===================== Yang butuh tindakan =====================

         Paling atas, sebelum angka apa pun: orang yang layanannya sedang mati
         tidak sedang mencari statistik pemakaiannya. Hanya satu yang muncul
         pada satu waktu, dan tidak ada yang muncul saat keadaannya normal.
         ============================================================= --}}
    @if ($tagihanTerbuka)
        @if ($tagihanTerbuka->isAwaitingVerification())
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary/40 bg-primary/5 px-4 py-3.5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                        </span>
                        <p class="font-medium text-foreground">Tagihan {{ $tagihanTerbuka->number }} sedang dalam proses verifikasi</p>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ $tagihanTerbuka->plan()->name() }} · {{ $tagihanTerbuka->periodLabel() }} ·
                        Rp {{ number_format($tagihanTerbuka->total, 0, ',', '.') }}
                        · Menunggu konfirmasi admin
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="https://wa.me/{{ \App\Support\KontakWhatsApp::nomor() }}?text={{ rawurlencode('Halo Admin Pesanvo, tagihan ' . $tagihanTerbuka->number . ' sebesar Rp ' . number_format($tagihanTerbuka->total, 0, ',', '.') . ' sedang menunggu verifikasi. Mohon dibantu cek mutasi.') }}"
                       target="_blank"
                       class="shrink-0 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3.5 py-2 text-xs font-semibold text-emerald-800 dark:text-emerald-300 transition hover:bg-emerald-500/20">
                        Chat Admin WhatsApp
                    </a>
                    <a href="{{ route('billing.verifying', $tagihanTerbuka->id) }}"
                       class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        Lihat status verifikasi
                    </a>
                </div>
            </div>
        @else
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary/40 bg-primary/5 px-4 py-3.5">
                <div class="min-w-0">
                    <p class="font-medium">Tagihan {{ $tagihanTerbuka->number }} menunggu pembayaran</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ $tagihanTerbuka->plan()->name() }} · {{ $tagihanTerbuka->periodLabel() }} ·
                        Rp {{ number_format($tagihanTerbuka->total, 0, ',', '.') }}
                        @if ($tagihanTerbuka->due_at)
                            · bayar sebelum {{ $tagihanTerbuka->due_at->translatedFormat('j F Y, H:i') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('billing.invoice', $tagihanTerbuka->id) }}"
                   class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Lihat cara bayar
                </a>
            </div>
        @endif
    @elseif ($subscription->isUnpaid())
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary/40 bg-primary/5 px-4 py-3.5">
            <div class="min-w-0">
                <p class="font-medium">Workspace ini belum berlangganan.</p>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">
                    Pilih paket untuk menautkan nomor WhatsApp dan mulai mengirim pesan.
                    Riwayat, template, dan API key tetap bisa Anda siapkan lebih dulu.
                </p>
            </div>
            @if ($bolehBayar)
                <a href="{{ route('billing.plans') }}"
                   class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Pilih paket
                </a>
            @endif
        </div>
    @elseif ($subscription->status === 'past_due')
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3.5 text-destructive">
            <div class="min-w-0">
                <p class="font-medium">Layanan sedang berhenti.</p>
                <p class="mt-0.5 text-sm leading-relaxed">
                    Pengiriman keluar, pesan masuk, dan webhook semuanya berhenti.
                    <strong>WhatsApp di ponsel Anda tidak terpengaruh.</strong>
                    @if ($subscription->sessionsCutOffAt())
                        Nomor dilepas dari gateway kalau belum dibayar sampai
                        {{ $subscription->sessionsCutOffAt()->translatedFormat('j F Y') }} —
                        dan itu pun tanpa perlu scan QR lagi saat Anda kembali.
                    @endif
                </p>
            </div>
            @if ($bolehBayar)
                <a href="{{ route('billing.plans') }}"
                   class="shrink-0 rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
                    Perpanjang
                </a>
            @endif
        </div>
    @elseif ($subscription->status === 'suspended')
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3.5 text-destructive">
            <div class="min-w-0">
                <p class="font-medium">Langganan ditangguhkan dan sesi sudah dilepas.</p>
                <p class="mt-0.5 text-sm leading-relaxed">
                    Riwayat dan pengaturan Anda tetap tersimpan. Setelah pembayaran,
                    nomor bisa dihubungkan lagi dari halaman Sesi.
                </p>
            </div>
            @if ($bolehBayar)
                <a href="{{ route('billing.plans') }}"
                   class="shrink-0 rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
                    Pilih paket
                </a>
            @endif
        </div>
    @elseif ($subscription->isExpiringSoon())
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3.5 text-amber-700 dark:text-amber-300">
            <p class="min-w-0">
                Perpanjang sebelum {{ $subscription->current_period_end->translatedFormat('j F Y') }}
                supaya pengiriman tidak terputus.
            </p>
            @if ($bolehBayar)
                <a href="{{ route('billing.plans') }}"
                   class="shrink-0 rounded-lg border border-current px-4 py-2 text-sm font-medium transition hover:bg-amber-500/10">
                    Perpanjang sekarang
                </a>
            @endif
        </div>
    @endif

    {{-- ===================== Angka =====================

         Satu-satunya baris berkotak. Sisanya mengalir langsung di atas latar.
         ============================================================= --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Paket sekarang" :nilai="$paket->name()" :sub="$ls['label']"
                :nada="$subscription->isUsable() ? 'netral' : 'bahaya'"
                :tautan="$bolehBayar ? route('billing.plans') : null"
                tautanLabel="Ganti paket"
                ikon="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />

        <x-stat label="{{ $subscription->isUsable() ? 'Berlaku sampai' : ($subscription->current_period_end ? 'Berakhir pada' : 'Masa berlaku') }}"
                :nilai="$tanggalAkhir"
                :sub="$subSisa"
                :nada="$nadaSisa"
                ikon="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />

        <x-stat label="{{ $gratis ? 'Jatah coba terpakai' : 'Pesan keluar bulan ini' }}"
                :nilai="$gratis ? $terpakaiGratis : number_format($usage->messages_sent)"
                :sub="$gratis
                    ? 'dari '.$kuota.' pesan gratis, sekali seumur workspace'
                    : ($kuota === 0 ? 'kuota tanpa batas' : 'dari '.number_format($kuota).' kuota paket')"
                :nada="$persen !== null && $persen >= 90 ? 'bahaya' : 'netral'"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />

        <x-stat label="Nomor aktif"
                :nilai="$sesiTerpakai.' / '.$currentWorkspace->max_sessions"
                :sub="$sesiTerpakai >= $currentWorkspace->max_sessions ? 'batas paket tercapai' : 'masih ada ruang untuk nomor baru'"
                :nada="$sesiTerpakai >= $currentWorkspace->max_sessions ? 'perhatian' : 'netral'"
                ikon="M12 2a10 10 0 1 0 4.9 18.7L22 22l-1.3-5.1A10 10 0 0 0 12 2z" />
    </div>

    {{-- ===================== Pemakaian ===================== --}}
    @if ($persen !== null)
        @php
            // Jatah coba dihitung seumur hidup workspace, kuota berbayar per
            // bulan. Batangnya sama, angka di baliknya tidak — dan kalimat yang
            // salah di sini membuat orang menunggu jatah yang tidak akan pulih.
            $dipakai = $gratis ? $terpakaiGratis : $usage->messages_sent;
            $persenBar = min(100, (int) round($dipakai / max($kuota, 1) * 100));
        @endphp

        <x-section :judul="$gratis ? 'Jatah coba gratis' : 'Pemakaian kuota bulan ini'"
                   :sub="$gratis
                       ? 'Dihitung sekali untuk seumur workspace ini — tidak pulih di bulan berikutnya.'
                       : 'Dihitung ulang setiap awal bulan. Sisa kuota tidak dibawa ke bulan berikutnya.'"
                   rapat>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full {{ $persenBar >= 90 ? 'bg-destructive' : 'bg-primary' }}"
                     style="width: {{ $persenBar }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                <span>{{ number_format($dipakai) }} terpakai · {{ $persenBar }}%</span>
                <span>{{ number_format(max(0, $kuota - $dipakai)) }} tersisa</span>
            </div>
        </x-section>
    @endif

    {{-- ===================== Apa yang terjadi selanjutnya =====================

         Ditampilkan hanya saat memang sedang berjalan menuju sesuatu. Yang
         membuat pelanggan panik bukan layanan yang berhenti, melainkan tidak
         tahu apa yang berhenti, kapan, dan apa yang hilang permanen. Tanggalnya
         nyata, bukan "beberapa hari".
         ============================================================= --}}
    @if (in_array($subscription->status, ['past_due', 'suspended'], true))
        @php
            $lepas = $subscription->sessionsCutOffAt();
            $sudahLepas = $subscription->status === 'suspended';
        @endphp

        <x-section judul="Apa yang terjadi dengan nomor Anda" rapat>
            <ol class="space-y-4 text-sm">
                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-destructive"></span>
                    <span>
                        <span class="font-medium">Layanan berhenti</span>
                        <span class="ml-2 text-muted-foreground">
                            {{ $subscription->past_due_at?->translatedFormat('j F Y') ?? 'sudah berlaku' }}
                        </span>
                        <span class="mt-0.5 block leading-relaxed text-muted-foreground">
                            Pengiriman keluar, pesan masuk, dan webhook berhenti bersamaan.
                            Pesan yang masuk tetap ada di WhatsApp ponsel Anda.
                        </span>
                    </span>
                </li>

                <li class="flex gap-3">
                    <span @class([
                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                        'bg-destructive' => $sudahLepas,
                        'bg-border' => ! $sudahLepas,
                    ])></span>
                    <span>
                        <span class="font-medium">Nomor dilepas dari gateway</span>
                        <span class="ml-2 text-muted-foreground">
                            {{ $sudahLepas ? 'sudah terjadi' : ($lepas?->translatedFormat('j F Y') ?? '—') }}
                        </span>
                        <span class="mt-0.5 block leading-relaxed text-muted-foreground">
                            WhatsApp di ponsel Anda tidak terpengaruh — kami hanya perangkat tertaut.
                            Kredensialnya tetap kami simpan.
                        </span>
                    </span>
                </li>

                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-border"></span>
                    <span>
                        <span class="font-medium">Riwayat pesan tetap tersimpan</span>
                        <span class="ml-2 text-muted-foreground">{{ $currentWorkspace->messageRetentionDays() }} hari</span>
                        <span class="mt-0.5 block leading-relaxed text-muted-foreground">
                            Template, webhook, API key, dan anggota tim tidak dihapus karena tidak membayar.
                        </span>
                    </span>
                </li>

                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                    <span>
                        <span class="font-medium text-primary">Begitu tagihan lunas, semuanya kembali sendiri</span>
                        <span class="mt-0.5 block leading-relaxed text-muted-foreground">
                            Nomor yang sama tersambung otomatis dalam hitungan menit, tanpa scan QR,
                            tanpa Anda menekan apa pun.
                        </span>
                    </span>
                </li>
            </ol>
        </x-section>
    @endif

    {{-- ===================== Batas paket =====================

         Angka yang ditegakkan, bukan yang dijanjikan halaman harga: yang tampil
         di sini adalah kolom `workspaces` yang benar-benar dipakai saat menolak
         atau meloloskan pengiriman.
         ============================================================= --}}
    <x-section judul="Batas yang berlaku untuk workspace ini">
        <x-tabel :kepala="['Batas' => '', 'Berlaku' => 'text-right']">
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Nomor WhatsApp</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $currentWorkspace->max_sessions }}</td>
            </tr>
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">{{ $gratis ? 'Jatah pesan' : 'Kuota pesan per bulan' }}</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">
                    @if ($gratis)
                        {{ $kuota }} <span class="text-xs font-normal text-muted-foreground">sekali seumur workspace</span>
                    @else
                        {{ $kuota === 0 ? 'Tanpa batas' : number_format($kuota) }}
                    @endif
                </td>
            </tr>
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Batas permintaan API</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $currentWorkspace->api_rate_limit_per_minute }}/menit</td>
            </tr>
            <tr class="transition hover:bg-muted/40">
                <td class="px-4 py-2.5 sm:px-3">Riwayat pesan disimpan</td>
                <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $currentWorkspace->messageRetentionDays() }} hari</td>
            </tr>
        </x-tabel>
    </x-section>

    @unless ($bolehBayar)
        <p class="mt-6 text-sm text-muted-foreground">
            Hanya owner atau admin workspace yang bisa mengurus langganan.
        </p>
    @endunless
@endsection
