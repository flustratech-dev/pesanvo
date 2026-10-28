@extends('layouts.admin')
@section('title', 'Ringkasan')

@section('content')
    @php
        $persenKapasitas = $kapasitasSesi > 0 ? min(100, round($sesiHidup / $kapasitasSesi * 100)) : 0;

        $labelStatus = [
            'unpaid' => 'Belum berlangganan',
            'trialing' => 'Masa percobaan',
            'active' => 'Aktif',
            'past_due' => 'Lewat jatuh tempo',
            'suspended' => 'Ditangguhkan',
            'canceled' => 'Dihentikan',
        ];
    @endphp

    {{-- ===================== Peringatan =====================

         Hanya dua, dan hanya muncul saat memang bermasalah. Peringatan yang
         selalu ada berhenti dibaca dalam seminggu.
         ============================================================= --}}
    @if ($persenKapasitas >= 90)
        <div class="mb-4 flex flex-wrap items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span>
                <strong>Kapasitas sesi hampir penuh ({{ $sesiHidup }}/{{ $kapasitasSesi }}).</strong>
                Pelanggan berikutnya kemungkinan gagal menautkan nomor — putus sesi yang menganggur,
                atau naikkan RAM sebelum menjual paket lagi.
            </span>
        </div>
    @endif

    @if ($kesehatanAntrean['macet'])
        <div class="mb-4 flex flex-wrap items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span>
                <strong>Antrean tidak bergerak — worker kemungkinan mati.</strong>
                {{ $kesehatanAntrean['menunggu'] }} pekerjaan menunggu dan tidak ada satu pun pesan
                yang terkirim sejak
                {{ $kesehatanAntrean['terakhirBergerak']?->diffForHumans() ?? 'entah kapan' }}.
                Pesan pelanggan diam berstatus <em>Mengantre</em> tanpa satu pun galat —
                mereka mengira gateway-nya lambat, penerimanya tidak menerima apa-apa.
                Periksa proses <code>queue:work</code> di container.
            </span>
        </div>
    @endif

    @unless ($notifikasiSiap && $nomorAdminTerisi)
        <div class="mb-4 flex flex-wrap items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span>
                <strong>Pemberitahuan WhatsApp tidak berjalan.</strong>
                Pelanggan tidak dikabari saat pembayarannya lunas, kuotanya habis, atau nomornya terputus —
                dan tidak ada satu pun yang gagal secara terlihat.
                <a href="{{ route('admin.system') }}" class="underline">Lihat rinciannya di Sistem</a>.
            </span>
        </div>
    @endunless

    {{-- ===================== Angka =====================

         Satu-satunya baris berkotak di halaman ini. Sisanya mengalir langsung di
         atas latar: halaman yang seluruh isinya dibungkus kotak membuat setiap
         bagian tampak sama penting, dan mata kehilangan tempat berpijak.
         ============================================================= --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Pendapatan bulan ini"
                :nilai="'Rp '.number_format($pendapatanBulanIni, 0, ',', '.')"
                sub="dari tagihan yang ditandai lunas"
                ikon="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

        <x-stat label="Bukti menunggu diperiksa"
                :nilai="$tagihanPerluDiperiksa"
                :sub="$tagihanMenunggu.' tagihan menunggu bayar'"
                :nada="$tagihanPerluDiperiksa > 0 ? 'perhatian' : 'netral'"
                :tautan="$tagihanPerluDiperiksa > 0 ? route('admin.invoices') : null"
                tautanLabel="Periksa sekarang"
                ikon="M9 11l3 3 8-8M21 12a9 9 0 1 1-6.22-8.56" />

        <x-stat label="Kapasitas sesi"
                :nilai="$sesiHidup.'/'.$kapasitasSesi"
                :sub="$sesiTersambung.' tersambung penuh'"
                :nada="$persenKapasitas >= 90 ? 'bahaya' : ($persenKapasitas >= 70 ? 'perhatian' : 'netral')"
                ikon="M12 2a10 10 0 1 0 4.9 18.7L22 22l-1.3-5.1A10 10 0 0 0 12 2z" />

        <x-stat label="Pesan keluar bulan ini"
                :nilai="number_format($pesanBulanIni)"
                :sub="$antreanGagal > 0 ? $antrean.' mengantre · '.$antreanGagal.' job gagal' : $antrean.' mengantre'"
                :nada="$antreanGagal > 0 ? 'bahaya' : 'netral'"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- ===================== Sebaran langganan ===================== --}}
        <x-section judul="Langganan per status" sub="Seluruh workspace, termasuk yang belum pernah berlangganan.">
            <x-tabel :kepala="['Status' => '', 'Jumlah' => 'text-right']">
                @foreach ($labelStatus as $status => $teks)
                    @php
                        $jumlah = $langgananPerStatus[$status] ?? 0;
                        $ls = \App\Support\StatusBadge::subscription($status);
                    @endphp
                    <tr class="transition hover:bg-muted/40">
                        <td class="px-4 py-2.5 sm:px-3">
                            <a href="{{ route('admin.workspaces', ['status' => $status]) }}" class="inline-block hover:opacity-80">
                                <x-badge :warna="$ls['warna']" titik>{{ $teks }}</x-badge>
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium tabular-nums sm:px-3">{{ $jumlah }}</td>
                    </tr>
                @endforeach
                <tr class="bg-muted/30">
                    <td class="px-4 py-2.5 font-medium sm:px-3">Total workspace</td>
                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums sm:px-3">{{ $workspaceTotal }}</td>
                </tr>
            </x-tabel>
        </x-section>

        {{-- ===================== Akan berakhir ===================== --}}
        <x-section judul="Berakhir dalam 7 hari"
                   sub="Yang belum membayar sampai tanggalnya akan berhenti mengirim otomatis.">
            <x-tabel :kepala="['Workspace' => '', 'Paket' => '', 'Berakhir' => 'text-right']">
                @forelse ($akanBerakhir as $subscription)
                    <tr class="transition hover:bg-muted/40">
                        <td class="px-4 py-2.5 sm:px-3">
                            @if ($subscription->workspace)
                                <a href="{{ route('admin.workspaces.show', $subscription->workspace_id) }}" class="font-medium hover:underline">
                                    {{ $subscription->workspace->name }}
                                </a>
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground sm:px-3">{{ $subscription->plan()->name() }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right sm:px-3">
                            {{ $subscription->current_period_end->translatedFormat('j M') }}
                            <span class="block text-xs text-muted-foreground">
                                {{ $subscription->daysRemaining() <= 0 ? 'hari ini' : $subscription->daysRemaining().' hari lagi' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong :kolom="3" judul="Tidak ada yang berakhir minggu ini"
                              pesan="Tagihan perpanjangan terbit otomatis tiga hari sebelum masa berlaku habis." />
                @endforelse
            </x-tabel>
        </x-section>
    </div>
@endsection
