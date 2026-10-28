@extends('layouts.app')
@section('title', 'Saldo')

@section('content')
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat label="Saldo"
                :nilai="'Rp '.number_format($saldo, 0, ',', '.')"
                :sub="$saldo < $harga ? 'tidak cukup untuk mengirim' : 'siap dipakai'"
                :nada="$saldo < $harga ? 'bahaya' : 'netral'"
                ikon="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

        <x-stat data-tur="saldo-sisa" label="Sisa pesan"
                :nilai="number_format($sisaPesan, 0, ',', '.')"
                :sub="'Rp '.number_format($harga, 0, ',', '.').' per pesan terkirim'"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />

        <x-stat label="Menunggu pembayaran"
                :nilai="$menunggu->count()"
                :sub="$menunggu->count() > 0 ? 'tagihan isi saldo belum lunas' : 'tidak ada'"
                :nada="$menunggu->count() > 0 ? 'perhatian' : 'netral'"
                ikon="M12 8v4l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
    </div>

    @if ($saldo < $harga)
        <div class="mt-5 flex flex-wrap items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3.5 text-sm text-destructive">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span>
                <strong>Pengiriman pesan berhenti karena saldo habis.</strong>
                Nomor WhatsApp Anda <strong>tetap tertaut</strong> dan pesan masuk tetap diterima —
                yang berhenti hanya pengiriman keluar. Isi saldo di bawah untuk melanjutkan.
            </span>
        </div>
    @endif

    {{-- Tagihan yang belum dibayar ditampilkan lebih dulu daripada form isi
         saldo. Tanpa ini, pelanggan yang menutup halaman bayar kehilangan
         jejak tagihannya lalu menerbitkan yang baru — dua tagihan untuk satu
         niat, dan admin yang harus menebak mana yang dibayar. --}}
    @if ($menunggu->isNotEmpty())
        <x-section judul="Tagihan isi saldo yang menunggu"
                   sub="Lanjutkan yang ini kalau Anda sudah membayarnya, jangan buat tagihan baru."
                   class="mt-5">
            <table class="w-full min-w-[40rem] text-sm">
                <tbody class="divide-y divide-border">
                    @foreach ($menunggu as $inv)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $inv->number }}</td>
                            <td class="px-5 py-3 text-muted-foreground">
                                saldo Rp {{ number_format($inv->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                bayar Rp {{ number_format($inv->total, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('billing.invoice', $inv->id) }}"
                                   class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-muted">
                                    Buka tagihan
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-section>
    @endif

    @if ($bolehBayar)
        <x-card title="Isi saldo"
                :subtitle="'Minimum Rp '.number_format($minimum, 0, ',', '.').' — sekitar '.number_format(intdiv($minimum, $harga), 0, ',', '.').' pesan. Pembayarannya lewat QRIS atau transfer, sama seperti tagihan langganan.'"
                class="mt-5">
            <form method="POST" action="{{ route('balance.topup') }}" class="max-w-xl"
                  x-data="{ jumlah: {{ old('jumlah', $minimum) }} }" data-validasi>
                @csrf

                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach ([$minimum, $minimum * 2, $minimum * 4, $minimum * 10] as $pilihan)
                        <button type="button" @click="jumlah = {{ $pilihan }}"
                                class="rounded-lg border px-3 py-1.5 text-sm transition"
                                :class="jumlah === {{ $pilihan }} ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-muted'">
                            Rp {{ number_format($pilihan, 0, ',', '.') }}
                            <span class="block text-xs opacity-70">{{ number_format(intdiv($pilihan, $harga), 0, ',', '.') }} pesan</span>
                        </button>
                    @endforeach
                </div>

                <label for="jumlah" class="mb-1 block text-sm font-medium">Jumlah (rupiah)</label>
                <div class="flex flex-wrap gap-2">
                    <input id="jumlah" name="jumlah" type="number" x-model.number="jumlah" required
                           min="{{ $minimum }}" step="1000"
                           class="min-w-48 flex-1 rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        Buat tagihan
                    </button>
                </div>

                <p class="mt-2 text-sm text-muted-foreground">
                    Anda akan menerima saldo
                    <strong x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(jumlah)"></strong>
                    — sekitar
                    <strong x-text="new Intl.NumberFormat('id-ID').format(Math.floor(jumlah / {{ $harga }}))"></strong>
                    pesan.
                </p>

                @error('jumlah')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror

                {{-- Disebutkan apa adanya. Kode unik membuat nominal yang
                     ditransfer berbeda dari saldo yang diterima, dan selisih
                     yang tidak dijelaskan terbaca sebagai potongan diam-diam. --}}
                <p class="mt-3 border-t border-border pt-3 text-xs leading-relaxed text-muted-foreground">
                    Saldo bertambah setelah pembayaran Anda diperiksa — biasanya beberapa jam pada
                    hari kerja. Nominal yang ditransfer akan sedikit berbeda dari jumlah di atas:
                    tiga digit terakhirnya adalah kode unik yang membuat pembayaran Anda bisa kami
                    kenali. Yang masuk sebagai saldo tetap jumlah yang Anda pilih di sini.
                </p>
            </form>
        </x-card>
    @endif

    {{-- ===================== Riwayat mutasi =====================

         Ditampilkan penuh, bukan diringkas jadi satu angka. Saldo adalah uang
         pelanggan yang sudah dibayar di depan, dan angka tunggal tanpa
         rinciannya tidak bisa diperiksa maupun dibantah oleh yang memilikinya.
         ======================================================== --}}
    <x-section data-tur="saldo-mutasi" judul="Riwayat mutasi"
               sub="Setiap perubahan saldo, dengan sisa setelahnya. 100 terakhir."
               class="mt-5">
        @if ($mutasi->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Belum ada mutasi saldo.</p>
        @else
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Waktu</th>
                        <th class="px-5 py-2.5 font-medium">Jenis</th>
                        <th class="px-5 py-2.5 font-medium">Keterangan</th>
                        <th class="px-5 py-2.5 text-right font-medium">Jumlah</th>
                        <th class="px-5 py-2.5 text-right font-medium">Sisa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($mutasi as $m)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2.5 text-muted-foreground">
                                {{ $m->created_at->translatedFormat('j M Y, H:i') }}
                            </td>
                            <td class="px-5 py-2.5">{{ $m->labelJenis() }}</td>
                            <td class="px-5 py-2.5 text-muted-foreground">{{ $m->note }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums {{ $m->amount < 0 ? 'text-muted-foreground' : 'text-primary' }}">
                                {{ $m->amount < 0 ? '−' : '+' }}Rp {{ number_format(abs($m->amount), 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums">
                                Rp {{ number_format($m->balance_after, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>
    <x-tur-pengenalan kunci="saldo.mulai" :versi="1" :langkah="[
        [
            'target' => '[data-tur=\'saldo-sisa\']',
            'judul' => 'Saldo dihitung per pesan terkirim',
            'isi' => 'Angka ini perkiraan berapa pesan lagi yang bisa Anda kirim. '
                .'Pesan yang gagal — misalnya nomor tujuan tidak terdaftar di WhatsApp — '
                .'tidak memotong saldo sama sekali.',
        ],
        [
            'judul' => 'Saldo tidak kedaluwarsa',
            'isi' => 'Tidak ada tanggal berakhir dan tidak ada yang hangus di bulan yang Anda '
                .'tidak mengirim apa pun. Yang membatasi pengiriman adalah saldo, bukan waktu.',
        ],
        [
            'target' => '[data-tur=\'saldo-mutasi\']',
            'judul' => 'Setiap mutasi tercatat',
            'isi' => 'Tiap baris menyimpan sisa saldo sesudahnya, jadi Anda bisa menelusuri '
                .'sendiri ke mana saldo Anda pergi — tanpa perlu percaya begitu saja pada satu '
                .'angka di atas.',
        ],
    ]" />
@endsection

@push('scripts')
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    <script>
        (function() {
            var maxAttempts = 15; // 1.5 detik

            function renderQris(attempt) {
                attempt = typeof attempt === 'number' ? attempt : 0;
                var canvases = document.querySelectorAll('canvas[data-qris]');
                if (!canvases.length) return;
                var fallbackMsg = document.getElementById('qris-fallback-msg');

                if (typeof window.QRCode === 'undefined' || !window.QRCode.toCanvas) {
                    if (attempt >= maxAttempts) {
                        console.error('Pustaka QRCode tidak dapat dimuat.');
                        if (fallbackMsg) fallbackMsg.classList.remove('hidden');
                        return;
                    }
                    setTimeout(function() { renderQris(attempt + 1); }, 100);
                    return;
                }

                canvases.forEach(function(el) {
                    var payload = el.getAttribute('data-qris');
                    if (!payload) return;
                    window.QRCode.toCanvas(el, payload, {
                        width: 260,
                        margin: 1,
                        color: {
                            dark: '#000000',
                            light: '#ffffff'
                        }
                    }, function(error) {
                        if (error) {
                            console.error('Gagal merender QRIS:', error);
                            if (fallbackMsg) fallbackMsg.classList.remove('hidden');
                        } else {
                            el.style.width = '160px';
                            el.style.height = '160px';
                            if (fallbackMsg) fallbackMsg.classList.add('hidden');
                        }
                    });
                });
            }

            window.renderQris = renderQris;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', renderQris);
            } else {
                renderQris();
            }
        })();
    </script>
@endpush
