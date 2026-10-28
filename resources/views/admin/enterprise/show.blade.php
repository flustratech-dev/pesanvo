@extends('layouts.admin')
@section('title', 'Permintaan Enterprise')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.enterprise') }}" class="text-sm text-muted-foreground hover:underline">← Semua permintaan</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold">{{ $lead->name }}</h2>
            <x-badge :warna="$lead->warnaStatus()">{{ $lead->labelStatus() }}</x-badge>
        </div>
        <p class="mt-0.5 text-sm text-muted-foreground">
            {{ $lead->company ?: 'perorangan' }} · masuk {{ $lead->created_at->translatedFormat('j F Y, H:i') }}
            @if ($lead->penangan)
                · ditangani {{ $lead->penangan->email }}
            @endif
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-section judul="Yang mereka sampaikan" rapat>
            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    'Email' => $lead->email,
                    'WhatsApp' => $lead->phone,
                    'Perkiraan nomor' => $lead->estimated_sessions ?? 'tidak disebut',
                    'Perkiraan pesan/bulan' => $lead->estimated_messages ? number_format($lead->estimated_messages, 0, ',', '.') : 'tidak disebut',
                    'Retensi diminta' => $lead->want_retention_months ? $lead->want_retention_months.' bulan' : 'tidak disebut',
                    'Batas API diminta' => $lead->want_api_rate ? $lead->want_api_rate.'/menit' : 'tidak disebut',
                    'Pendampingan integrasi' => $lead->want_onboarding ? 'ya' : 'tidak',
                    'Akun terdaftar' => $lead->user?->email ?? 'belum punya akun',
                    'Workspace' => $lead->workspace?->name ?? '—',
                ] as $label => $nilai)
                    <div class="flex justify-between gap-4 border-b border-border pb-2.5 last:border-0 last:pb-0">
                        <dt class="text-muted-foreground">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Angka yang MEREKA lihat, dibekukan saat permintaan masuk.
                 Harga komponennya boleh berubah kapan saja; menyebut angka
                 lebih tinggi daripada yang tertera di layar saat pemohon
                 memutuskan menghubungi kami adalah cara tercepat kehilangan
                 mereka. --}}
            @if ($lead->estimate_monthly)
                <div class="mt-4 rounded-lg border border-primary/25 bg-primary/5 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Perkiraan yang mereka lihat di kalkulator
                    </p>
                    <p class="mt-1.5 text-lg font-semibold tabular-nums">
                        Rp {{ number_format($lead->estimate_monthly, 0, ',', '.') }}
                        <span class="text-sm font-normal text-muted-foreground">/bulan</span>
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Setara Rp {{ number_format($lead->estimate_yearly, 0, ',', '.') }} per tahun
                        @if ($lead->estimate_once)
                            · plus Rp {{ number_format($lead->estimate_once, 0, ',', '.') }} sekali untuk pendampingan
                        @endif
                    </p>
                </div>
            @endif

            @if (filled($lead->needs))
                <div class="mt-4 rounded-lg bg-muted/40 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kebutuhan khusus</p>
                    <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed">{{ $lead->needs }}</p>
                </div>
            @endif
        </x-section>

        <x-section judul="Tandai keadaan" sub="Catatan di sini hanya terlihat tim, tidak pernah dikirim ke pemohon." rapat>
            <form method="POST" action="{{ route('admin.enterprise.status', $lead->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium">Keadaan</label>
                    <select id="status" name="status"
                            class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        @foreach (['baru' => 'Baru', 'diproses' => 'Sedang diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $n => $l)
                            <option value="{{ $n }}" @selected($lead->status === $n)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="admin_note" class="mb-1 block text-sm font-medium">Catatan tim</label>
                    <textarea id="admin_note" name="admin_note" rows="5" maxlength="2000"
                              placeholder="Apa yang sudah dibicarakan, harga yang disepakati, atau alasan ditolak."
                              class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">{{ old('admin_note', $lead->admin_note) }}</textarea>
                </div>

                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Simpan
                </button>
            </form>
        </x-section>
    </div>

    {{-- ===================== Kesepakatan yang berlaku ===================== --}}
    @if ($kesepakatanAktif)
        <x-section judul="Kesepakatan yang sedang berlaku"
                   sub="Batas ini tersalin ke workspace saat tagihannya lunas — bukan saat disimpan."
                   class="mt-5">
            <div class="px-5 py-4">
                <p class="font-medium">{{ $kesepakatanAktif->name }} · {{ $kesepakatanAktif->workspace?->name }}</p>

                <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        'Bulanan' => 'Rp '.number_format($kesepakatanAktif->price_monthly, 0, ',', '.'),
                        'Tahunan' => 'Rp '.number_format($kesepakatanAktif->price_yearly, 0, ',', '.'),
                        'Nomor' => $kesepakatanAktif->max_sessions,
                        'Kuota pesan' => $kesepakatanAktif->monthly_message_quota === 0 ? 'tanpa batas' : number_format($kesepakatanAktif->monthly_message_quota, 0, ',', '.'),
                        'API key' => $kesepakatanAktif->max_api_keys === 0 ? 'tanpa batas' : $kesepakatanAktif->max_api_keys,
                        'Anggota' => $kesepakatanAktif->max_members === 0 ? 'tanpa batas' : $kesepakatanAktif->max_members,
                        'Retensi' => $kesepakatanAktif->message_retention_days.' hari',
                        'API/menit' => $kesepakatanAktif->api_rate_limit_per_minute,
                    ] as $label => $nilai)
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-muted-foreground">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if (filled($kesepakatanAktif->note))
                    <p class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ $kesepakatanAktif->note }}</p>
                @endif

                @if ($kesepakatanAktif->lead_id === $lead->id)
                    <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-border pt-4">
                        <form method="POST" action="{{ route('admin.enterprise.plan.invoice', [$lead->id, $kesepakatanAktif->id]) }}"
                              class="flex flex-wrap items-end gap-2"
                              data-konfirmasi="Terbitkan tagihan Enterprise untuk {{ $kesepakatanAktif->workspace?->name }}? Pelanggan akan menerimanya lewat email.">
                            @csrf
                            <div>
                                <label for="period" class="mb-1 block text-xs font-medium">Periode tagihan</label>
                                <select id="period" name="period"
                                        class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                                    <option value="monthly">Bulanan — Rp {{ number_format($kesepakatanAktif->price_monthly, 0, ',', '.') }}</option>
                                    <option value="yearly">Tahunan — Rp {{ number_format($kesepakatanAktif->price_yearly, 0, ',', '.') }}</option>
                                </select>
                            </div>
                            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                                Terbitkan tagihan
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.enterprise.plan.deactivate', [$lead->id, $kesepakatanAktif->id]) }}"
                              data-konfirmasi="Matikan kesepakatan ini? Batas yang sedang berlaku tidak ikut dicabut — periode yang sudah dibayar tetap utuh.">
                            @csrf
                            <button class="rounded-lg border border-destructive/50 px-4 py-2 text-sm text-destructive transition hover:bg-destructive/10">
                                Matikan
                            </button>
                        </form>
                    </div>
                @else
                    <p class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground">
                        Kesepakatan ini berasal dari permintaan lain. Bukalah dari sana untuk menerbitkan tagihannya.
                    </p>
                @endif
            </div>
        </x-section>
    @endif

    {{-- ===================== Susun kesepakatan ===================== --}}
    <x-card title="{{ $kesepakatanAktif ? 'Susun kesepakatan baru' : 'Susun kesepakatan' }}"
            subtitle="Kesepakatan lama tidak dihapus, hanya dimatikan — riwayat harga yang pernah disepakati adalah hal pertama yang dicari saat ada perselisihan tagihan."
            class="mt-5">
        <form method="POST" action="{{ route('admin.enterprise.plan.store', $lead->id) }}" class="space-y-4" data-validasi>
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="workspace_id" class="mb-1 block text-sm font-medium">Workspace penerima</label>
                    <select id="workspace_id" name="workspace_id" required
                            class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        <option value="">— pilih —</option>
                        @foreach ($kandidat as $ws)
                            <option value="{{ $ws->id }}" @selected(old('workspace_id', $lead->workspace_id) == $ws->id)>
                                {{ $ws->name }} · {{ $ws->owner_email }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Pemohon belum punya workspace? Minta mereka mendaftar dulu — kesepakatan menempel
                        pada workspace, bukan pada orangnya.
                    </p>
                </div>

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Nama kesepakatan</label>
                    <input id="name" name="name" required maxlength="80"
                           value="{{ old('name', 'Enterprise '.($lead->company ?: $lead->name)) }}"
                           class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['price_monthly', 'Harga bulanan (Rp)', 1, null],
                    ['price_yearly', 'Harga tahunan (Rp)', 1, null],
                    ['max_sessions', 'Jumlah nomor', 1, 1],
                    ['monthly_message_quota', 'Kuota pesan/bulan', 0, 0],
                    ['max_api_keys', 'Batas API key', 0, 0],
                    ['max_members', 'Batas anggota', 0, 0],
                    ['message_retention_days', 'Retensi pesan (hari)', 1, 365],
                    ['api_rate_limit_per_minute', 'Batas API per menit', 1, 300],
                ] as $bidang)
                    <div>
                        <label for="{{ $bidang[0] }}" class="mb-1 block text-sm font-medium">{{ $bidang[1] }}</label>
                        <input id="{{ $bidang[0] }}" name="{{ $bidang[0] }}" type="number" required
                               min="{{ $bidang[2] }}" value="{{ old($bidang[0], $bidang[3]) }}"
                               class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        @error($bidang[0])<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <div>
                <label for="note" class="mb-1 block text-sm font-medium">Catatan kesepakatan <span class="font-normal text-muted-foreground">(opsional)</span></label>
                <textarea id="note" name="note" rows="3" maxlength="2000"
                          placeholder="Apa yang dijanjikan di luar angka — SLA, pendampingan, atau syarat khusus."
                          class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">{{ old('note') }}</textarea>
            </div>

            <p class="rounded-lg bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground">
                <strong>0 berarti tanpa batas</strong> untuk kuota pesan, API key, dan anggota — perjanjian
                yang sama dengan paket Elite. Jumlah nomor dibatasi kapasitas engine
                (<code>WA_MAX_SESSIONS</code> = {{ config('gateway.engine.max_sessions') }} untuk
                <em>seluruh</em> pelanggan sekaligus); menjanjikan lebih dari itu berarti menjual sesuatu
                yang belum ada, dan formulir ini akan menolaknya.
            </p>

            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                Simpan kesepakatan
            </button>
        </form>
    </x-card>
@endsection
