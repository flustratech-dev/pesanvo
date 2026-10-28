@extends('layouts.admin')
@section('title', 'Reseller & Program Mitra')

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Permohonan Mitra"
                :nilai="$permohonanMitra->count()"
                :sub="$permohonanMitra->count() > 0 ? 'menunggu persetujuan admin' : 'semua sudah diverifikasi'"
                :nada="$permohonanMitra->count() > 0 ? 'perhatian' : 'netral'"
                ikon="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />

        <x-stat label="Permintaan Pencairan"
                :nilai="$payoutRequests->count()"
                :sub="$payoutRequests->count() > 0 ? 'menunggu transfer manual' : 'tidak ada antrean'"
                :nada="$payoutRequests->count() > 0 ? 'perhatian' : 'netral'"
                ikon="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

        <x-stat label="Komisi terutang"
                :nilai="'Rp '.number_format($totalTerutang, 0, ',', '.')"
                :sub="$terutang->count().' transaksi belum ditransfer'"
                :nada="$totalTerutang > 0 ? 'perhatian' : 'netral'"
                ikon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-stat label="Komisi sudah dibayar"
                :nilai="'Rp '.number_format($totalDibayar, 0, ',', '.')"
                sub="seluruh waktu"
                ikon="M9 12l2 2 4-4M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
    </div>

    {{-- ===================== 1. PERMOHONAN MITRA BARU MENUNGGU ACC ===================== --}}
    @if ($permohonanMitra->isNotEmpty())
        <x-section judul="Permohonan Pendaftaran Mitra Baru (Menunggu ACC)"
                   sub="Pengguna yang mendaftar program reseller. Verifikasi nomor rekening dan setujui untuk mengaktifkan kodenya."
                   class="mt-5 border-amber-500/30">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm text-left">
                    <thead class="border-y border-border bg-amber-500/10 text-xs uppercase tracking-wide text-amber-800 dark:text-amber-300">
                        <tr>
                            <th class="px-5 py-2.5 font-medium">Pemohon</th>
                            <th class="px-5 py-2.5 font-medium">WhatsApp</th>
                            <th class="px-5 py-2.5 font-medium">Rekening Bank / E-Wallet</th>
                            <th class="px-5 py-2.5 font-medium">Kode Disiapkan</th>
                            <th class="px-5 py-2.5 font-medium">Rencana / Catatan</th>
                            <th class="px-5 py-2.5 text-right font-medium">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($permohonanMitra as $p)
                            <tr>
                                <td class="px-5 py-3">
                                    <span class="font-semibold text-foreground">{{ $p->owner?->name }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ $p->owner?->email }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $p->whatsapp_number ?? '') }}" target="_blank" class="text-primary hover:underline">
                                        {{ $p->whatsapp_number ?: '—' }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <span class="font-semibold text-foreground">{{ $p->bank_name }} - {{ $p->bank_account_number }}</span>
                                    <span class="block text-muted-foreground">a.n {{ $p->bank_account_name }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono font-bold text-primary">{{ $p->code }}</td>
                                <td class="px-5 py-3 text-xs text-muted-foreground max-w-xs truncate" title="{{ $p->notes }}">
                                    {{ $p->notes ?: '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Tombol ACC / Setujui --}}
                                        <form method="POST" action="{{ route('admin.referrals.approve', $p->id) }}"
                                              data-konfirmasi="Setujui permohonan reseller {{ $p->owner?->name }} dengan kode {{ $p->code }}?">
                                            @csrf
                                            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                Setujui (ACC)
                                            </button>
                                        </form>

                                        {{-- Tombol Tolak --}}
                                        <form method="POST" action="{{ route('admin.referrals.reject', $p->id) }}"
                                              onsubmit="event.preventDefault(); const f = this; Swal.fire({ title: 'Tolak Permohonan Reseller', text: 'Masukkan alasan penolakan permohonan untuk {{ addslashes($p->owner?->name ?? 'mitra ini') }}:', input: 'textarea', inputPlaceholder: 'Tuliskan alasan penolakan di sini...', showCancelButton: true, confirmButtonText: 'Tolak Permohonan', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626', inputValidator: (val) => { if (!val || !val.trim()) { return 'Alasan penolakan wajib diisi!'; } } }).then((result) => { if (result.isConfirmed && result.value) { f.rejection_reason.value = result.value.trim(); f.submit(); } });">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button type="submit" class="rounded-lg border border-destructive/40 text-destructive px-3 py-1.5 text-xs font-medium transition hover:bg-destructive/10">
                                                Tolak
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-section>
    @endif

    {{-- ===================== 2. PERMINTAAN PENCAIRAN DANA (PAYOUTS) ===================== --}}
    @if ($payoutRequests->isNotEmpty())
        <x-section judul="Permintaan Pencairan Dana Komisi Mitra (Menunggu Transfer Manual)"
                   sub="Mitra telah mengajukan penarikan komisi (minimal Rp 100.000). Lakukan transfer sesuai informasi rekening lalu klik tombol konfirmasi transfer."
                   class="mt-5 border-primary/30">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm text-left">
                    <thead class="border-y border-border bg-primary/10 text-xs uppercase tracking-wide text-primary">
                        <tr>
                            <th class="px-5 py-2.5 font-medium">No. Invoice</th>
                            <th class="px-5 py-2.5 font-medium">Tanggal</th>
                            <th class="px-5 py-2.5 font-medium">Nama Mitra</th>
                            <th class="px-5 py-2.5 font-medium">WhatsApp</th>
                            <th class="px-5 py-2.5 font-medium">Nomor &amp; Bank Tujuan</th>
                            <th class="px-5 py-2.5 text-right font-medium">Rincian Nominal</th>
                            <th class="px-5 py-2.5 text-right font-medium">Tindakan Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($payoutRequests as $pr)
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-foreground whitespace-nowrap">
                                    {{ $pr->payout_number ?: '#PO-'.$pr->id }}
                                </td>
                                <td class="px-5 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                    {{ $pr->created_at->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-semibold text-foreground">{{ $pr->user?->name }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ $pr->user?->email }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $pr->referralCode?->whatsapp_number ?? ($pr->user?->phone ?? '')) }}" target="_blank" class="text-primary hover:underline">
                                        {{ $pr->referralCode?->whatsapp_number ?? ($pr->user?->phone ?: '—') }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <strong class="text-foreground">{{ $pr->bank_name }}</strong> · {{ $pr->bank_account_number }}
                                    <span class="block text-muted-foreground">a.n {{ $pr->bank_account_name }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs tabular-nums">
                                    <span class="text-muted-foreground">Bruto: Rp {{ number_format($pr->amount, 0, ',', '.') }}</span>
                                    <span class="block text-destructive text-[11px]">Pot. 5%: -Rp {{ number_format($pr->fee_amount, 0, ',', '.') }}</span>
                                    <span class="block text-sm font-extrabold text-primary pt-0.5">Net: Rp {{ number_format($pr->net_amount, 0, ',', '.') }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.referrals.payout.invoice', $pr->id) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground hover:bg-muted transition shadow-2xs">
                                            <svg class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span>Invoice</span>
                                        </a>

                                        <form method="POST" action="{{ route('admin.referrals.payout.paid', $pr->id) }}"
                                              data-konfirmasi="Konfirmasi transfer dana bersih Rp {{ number_format($pr->net_amount, 0, ',', '.') }} (setelah potongan 5%) ke {{ $pr->bank_name }} {{ $pr->bank_account_number }} a.n {{ $pr->bank_account_name }} sudah selesai Anda lakukan?">
                                            @csrf
                                            <button class="rounded-lg bg-primary px-3.5 py-1.5 text-xs font-semibold text-primary-foreground transition hover:opacity-90 shadow-xs">
                                                ACC &amp; Ditransfer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-section>
    @endif

    {{-- ===================== 3. KOMISI TERUTANG SATUAN ===================== --}}
    <x-section judul="Komisi terutang satuan"
               sub="Tagihannya sudah lunas. Transfernya dilakukan manusia; tombol di sini hanya mencatat bahwa itu sudah terjadi."
               class="mt-5">
        @if ($terutang->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Tidak ada komisi satuan yang menunggu.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm text-left">
                    <thead class="border-y border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2.5 font-medium">Kode</th>
                            <th class="px-5 py-2.5 font-medium">Reseller</th>
                            <th class="px-5 py-2.5 font-medium">Dari workspace</th>
                            <th class="px-5 py-2.5 font-medium">Tagihan</th>
                            <th class="px-5 py-2.5 text-right font-medium">Komisi</th>
                            <th class="px-5 py-2.5 text-right font-medium">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($terutang as $r)
                            <tr>
                                <td class="px-5 py-3 font-mono font-medium">{{ $r->code?->code }}</td>
                                <td class="px-5 py-3">
                                    {{ $r->code?->owner?->name }}
                                    <span class="block text-xs text-muted-foreground">{{ $r->code?->owner?->email }}</span>
                                </td>
                                <td class="px-5 py-3">{{ $r->workspace?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-muted-foreground text-xs">
                                    {{ $r->invoice?->number ?? '—' }}
                                    <span class="block">disetujui {{ $r->approved_at?->translatedFormat('j M Y') }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-medium tabular-nums">
                                    Rp {{ number_format($r->commission_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.referrals.commission.paid', $r->id) }}"
                                          data-konfirmasi="Tandai komisi Rp {{ number_format($r->commission_amount, 0, ',', '.') }} untuk {{ $r->code?->owner?->name }} sudah ditransfer? Ini hanya mencatat, tidak mengirim uang.">
                                        @csrf
                                        <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-muted">
                                            Tandai dibayar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-section>

    {{-- ===================== 4. BUAT KODE MANUAL ===================== --}}
    <x-card title="Buat kode referal langsung oleh admin"
            subtitle="Kodenya dibuat acak — 5 huruf kapital tanpa I dan O. Admin dapat membuatkan kode khusus tanpa menunggu pendaftaran."
            class="mt-5">
        <form method="POST" action="{{ route('admin.referrals.store') }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-validasi>
            @csrf
            <div class="sm:col-span-2">
                <label for="owner_user_id" class="mb-1 block text-sm font-medium">Pemilik Akun</label>
                <select id="owner_user_id" name="owner_user_id" required
                        class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">— pilih pengguna —</option>
                    @foreach ($kandidatPemilik as $u)
                        <option value="{{ $u->id }}" @selected(old('owner_user_id') == $u->id)>{{ $u->name }} · {{ $u->email }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="discount_percent" class="mb-1 block text-sm font-medium">Diskon pembeli (%)</label>
                <input id="discount_percent" name="discount_percent" type="number" min="0" max="100" required
                       value="{{ old('discount_percent', 10) }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="commission_percent" class="mb-1 block text-sm font-medium">Komisi reseller (%)</label>
                <input id="commission_percent" name="commission_percent" type="number" min="0" max="100" required
                       value="{{ old('commission_percent', 20) }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="max_redemptions" class="mb-1 block text-sm font-medium">Batas pemakaian</label>
                <input id="max_redemptions" name="max_redemptions" type="number" min="1" placeholder="tanpa batas"
                       value="{{ old('max_redemptions') }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div class="sm:col-span-2">
                <label for="expires_at" class="mb-1 block text-sm font-medium">Kedaluwarsa</label>
                <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div class="flex items-end sm:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Buat kode sekarang
                </button>
            </div>
        </form>
    </x-card>

    {{-- ===================== 5. DAFTAR SEMUA KODE REFERAL ===================== --}}
    <x-section judul="Daftar seluruh kode referal" class="mt-5">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[54rem] text-sm text-left">
                <thead class="border-y border-border bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Kode</th>
                        <th class="px-5 py-2.5 font-medium">Pemilik / Reseller</th>
                        <th class="px-5 py-2.5 font-medium">Status Akun</th>
                        <th class="px-5 py-2.5 text-right font-medium">Diskon</th>
                        <th class="px-5 py-2.5 text-right font-medium">Komisi</th>
                        <th class="px-5 py-2.5 text-right font-medium">Pemakaian</th>
                        <th class="px-5 py-2.5 text-right font-medium">Status Kode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($kode as $k)
                        <tr>
                            <td class="px-5 py-3 font-mono font-bold text-primary">{{ $k->code }}</td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-foreground">{{ $k->owner?->name ?? '—' }}</span>
                                <span class="block text-xs text-muted-foreground">{{ $k->owner?->email }}</span>
                                @if ($k->bank_name)
                                    <span class="block text-[11px] text-muted-foreground">{{ $k->bank_name }} · {{ $k->bank_account_number }} ({{ $k->bank_account_name }})</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs">
                                @if ($k->approval_status === 'approved')
                                    <x-badge warna="hijau">Disetujui</x-badge>
                                @elseif ($k->approval_status === 'pending')
                                    <x-badge warna="kuning">Menunggu ACC</x-badge>
                                @else
                                    <x-badge warna="merah">Ditolak</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums">{{ $k->discount_percent }}%</td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums">{{ $k->commission_percent }}%</td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums">
                                {{ $k->dipakai ?? $k->redeemed_count }}
                                @if ($k->max_redemptions)
                                    <span class="text-xs text-muted-foreground font-normal">/ {{ $k->max_redemptions }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('admin.referrals.toggle', $k->id) }}" class="inline-block">
                                    @csrf
                                    <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-muted">
                                        {{ $k->is_active ? 'Matikan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-kosong kolom="7" judul="Belum ada kode referal" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-section>
@endsection
