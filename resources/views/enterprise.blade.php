@extends('layouts.publik')
@section('title', 'Enterprise — Pesanvo Gateway')

@section('content')
<section class="border-b border-border/60 bg-muted/30 py-8 sm:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl lg:text-4xl">
                Batas dan harganya disusun mengikuti kebutuhan Anda
            </h1>
        </div>
    </div>
</section>

<section class="py-8 sm:py-12"
         x-data="{
             nomor: 3,
             pesan: 150000,
             retensi: 12,
             api: 300,
             pendampingan: false,

             /*
              | Angka satuannya DIBACA dari server, tidak disalin ke sini.
              |
              | Hitungan yang sama juga berjalan di PHP saat permintaan masuk;
              | kalau keduanya menyimpang, pengunjung melihat satu angka lalu
              | tim menerima angka lain — dan yang menemukan selisihnya adalah
              | orang yang sudah telanjur menyebut angka pertama.
             */
             k: @js($komponen),

             get nomorTambahan() {
                 return Math.max(0, this.nomorAman - this.k.included_sessions);
             },

             get nomorAman() {
                 return Math.min(Math.max(1, Number(this.nomor) || 1), 100);
             },

             get pesanAman() {
                 return Math.min(Math.max(0, Number(this.pesan) || 0), 100000000);
             },

             /* Dibulatkan ke ATAS: 60.000 pesan butuh dua blok, bukan satu koma dua. */
             get blokPesan() {
                 return Math.ceil(Math.max(0, this.pesanAman - this.k.included_messages) / this.k.message_block_size);
             },

             get rincian() {
                 const baris = [{
                     label: 'Dasar Enterprise',
                     catatan: `Termasuk ${this.k.included_sessions} nomor, ${this.rupiahPolos(this.k.included_messages)} pesan, retensi 12 bulan, API key & anggota tanpa batas`,
                     jumlah: this.k.base,
                 }];

                 if (this.nomorTambahan > 0) {
                     baris.push({
                         label: `${this.nomorTambahan} nomor WhatsApp tambahan`,
                         catatan: `${this.rupiah(this.k.per_session)} per nomor`,
                         jumlah: this.nomorTambahan * this.k.per_session,
                     });
                 }

                 if (this.blokPesan > 0) {
                     baris.push({
                         label: `${this.blokPesan} × ${this.rupiahPolos(this.k.message_block_size)} pesan tambahan`,
                         catatan: `${this.rupiah(this.k.per_message_block)} per blok`,
                         jumlah: this.blokPesan * this.k.per_message_block,
                     });
                 }

                 if (Number(this.retensi) === 24) {
                     baris.push({
                         label: 'Retensi riwayat 24 bulan',
                         catatan: 'Dari 12 bulan bawaan',
                         jumlah: this.k.retention_24_months,
                     });
                 }

                 if (Number(this.api) === 600) {
                     baris.push({
                         label: 'Batas API 600 permintaan/menit',
                         catatan: 'Dari 300 bawaan',
                         jumlah: this.k.api_600_per_minute,
                     });
                 }

                 return baris;
             },

             get bulanan() {
                 return this.rincian.reduce((t, b) => t + b.jumlah, 0);
             },

             get tahunan() {
                 return this.bulanan * {{ config('plans.yearly_multiplier') }};
             },

             get sekali() {
                 return this.pendampingan ? this.k.onboarding : 0;
             },

             rupiah(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n); },
             rupiahPolos(n) { return new Intl.NumberFormat('id-ID').format(n); },

             /* Pesan WhatsApp disusun dari angka yang sedang terlihat, bukan dari
                template kosong — tim langsung punya konteksnya di pesan pertama. */
             get pesanWa() {
                 return encodeURIComponent(
                     `Halo Pesanvo, saya ingin menanyakan paket Enterprise.\n\n`
                     + `Nomor WhatsApp: ${this.nomorAman}\n`
                     + `Pesan per bulan: ${this.rupiahPolos(this.pesanAman)}\n`
                     + `Retensi: ${this.retensi} bulan\n`
                     + `Batas API: ${this.api}/menit\n`
                     + (this.pendampingan ? `Pendampingan integrasi: ya\n` : '')
                     + `\nPerkiraan dari kalkulator: ${this.rupiah(this.bulanan)}/bulan`
                     + (this.sekali ? ` + ${this.rupiah(this.sekali)} sekali` : '')
                 );
             },
         }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-5">

            {{-- ===================== Penyusun kebutuhan ===================== --}}
            <div class="lg:col-span-3">
                <h2 class="text-lg font-semibold">Susun kebutuhan Anda</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Semua bisa diubah lagi nanti — ini hanya untuk memperkirakan.
                </p>

                <div class="mt-5 space-y-5 rounded-2xl border border-border bg-card p-5 sm:p-6">
                    {{-- Nomor --}}
                    <div>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label for="nomor" class="text-sm font-medium">Nomor WhatsApp aktif</label>
                            <span class="text-sm font-semibold tabular-nums" x-text="nomorAman"></span>
                        </div>
                        <input id="nomor" type="range" min="1" max="20" step="1" x-model.number="nomor"
                               class="mt-2 w-full accent-primary">
                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                            Tiap nomor berarti satu sesi WhatsApp yang berjalan terus-menerus, dan itulah
                            komponen termahal di sisi kami. Kapasitas platform saat ini
                            <strong>{{ $kapasitas }}</strong> nomor aktif; di atas itu kami menambah
                            kapasitas lebih dulu sebelum menyanggupi.
                        </p>
                    </div>

                    {{-- Pesan --}}
                    <div class="border-t border-border pt-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label for="pesan" class="text-sm font-medium">Pesan keluar per bulan</label>
                            <span class="text-sm font-semibold tabular-nums" x-text="rupiahPolos(pesanAman)"></span>
                        </div>
                        <input id="pesan" type="range" min="50000" max="2000000" step="50000" x-model.number="pesan"
                               class="mt-2 w-full accent-primary">
                        <p class="mt-1 text-xs text-muted-foreground">
                            Dihitung per blok <span x-text="rupiahPolos(k.message_block_size)"></span> pesan.
                            Blok pertama sudah termasuk.
                        </p>
                    </div>

                    {{-- Retensi --}}
                    <div class="border-t border-border pt-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label for="retensi" class="text-sm font-medium">Retensi riwayat pesan</label>
                            <span class="text-sm font-semibold tabular-nums" x-text="`${retensi} bulan`"></span>
                        </div>
                        <input id="retensi" type="range" min="12" max="24" step="12" x-model.number="retensi"
                               class="mt-2 w-full accent-primary">
                        <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                            <span>12 bulan (bawaan)</span>
                            <span>24 bulan</span>
                        </div>
                    </div>

                    {{-- API --}}
                    <div class="border-t border-border pt-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label for="api" class="text-sm font-medium">Batas API per menit</label>
                            <span class="text-sm font-semibold tabular-nums" x-text="`${api} / menit`"></span>
                        </div>
                        <input id="api" type="range" min="300" max="600" step="300" x-model.number="api"
                               class="mt-2 w-full accent-primary">
                        <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                            <span>300 / menit (bawaan)</span>
                            <span>600 / menit</span>
                        </div>
                    </div>

                    {{-- Pendampingan --}}
                    <div class="border-t border-border pt-5">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" x-model="pendampingan" class="mt-0.5 rounded border-border">
                            <span>
                                <span class="block text-sm font-medium">Pendampingan integrasi</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                                    Tim kami membantu menyambungkan gateway ke sistem Anda sampai pesan
                                    pertama terkirim. Dibayar <strong>sekali</strong>, bukan tiap bulan.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <ul class="mt-6 grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($plan->features() as $fitur)
                        <li class="flex gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                            <span class="text-muted-foreground">{{ $fitur }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- ===================== Perkiraan ===================== --}}
            <div class="lg:col-span-2">
                <div class="sticky top-24 rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Perkiraan biaya</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <template x-for="baris in rincian" :key="baris.label">
                            <div class="flex justify-between gap-4 border-b border-border pb-3">
                                <dt class="min-w-0">
                                    <span class="block" x-text="baris.label"></span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground" x-text="baris.catatan"></span>
                                </dt>
                                <dd class="whitespace-nowrap font-medium tabular-nums" x-text="rupiah(baris.jumlah)"></dd>
                            </div>
                        </template>
                    </dl>

                    <div class="mt-4 flex items-baseline justify-between gap-3 border-t border-border pt-4">
                        <span class="text-sm font-medium">Total per bulan</span>
                        <span class="text-2xl font-bold tracking-tight text-primary tabular-nums" x-text="rupiah(bulanan)"></span>
                    </div>

                    <p class="mt-1 text-xs text-muted-foreground">
                        Setara <span class="font-medium" x-text="rupiah(tahunan)"></span> per tahun —
                        bayar tahunan berarti membayar sepuluh bulan untuk dua belas.
                    </p>

                    {{-- Dipisah dari total bulanan dan tidak dijumlahkan ke
                         dalamnya. Menjumlahkannya membuat angka bulanan terbaca
                         lebih mahal dari yang sebenarnya. --}}
                    <template x-if="sekali > 0">
                        <p class="mt-3 flex items-baseline justify-between gap-3 rounded-lg bg-muted/50 px-3 py-2 text-sm">
                            <span>Pendampingan integrasi</span>
                            <span class="font-medium tabular-nums">
                                <span x-text="rupiah(sekali)"></span>
                                <span class="text-xs font-normal text-muted-foreground">sekali</span>
                            </span>
                        </p>
                    </template>

                    {{-- WAJIB ikut. Angka yang tampak pasti lalu berubah saat
                         ditagihkan adalah janji yang dilanggar di hadapan orang
                         yang baru saja memutuskan membeli. --}}
                    <p class="mt-4 rounded-lg border border-border bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground">
                        <strong class="text-foreground">Ini perkiraan, bukan penawaran.</strong>
                        Angka pastinya kami kirim setelah kebutuhan Anda ditinjau — dan hampir selalu
                        sama dengan yang tertera di sini kecuali ada syarat khusus yang belum masuk
                        hitungan.
                    </p>

                    <div class="mt-4 space-y-2">
                        <a href="#minta-penawaran"
                           class="block w-full rounded-xl bg-primary px-5 py-3 text-center text-sm font-semibold text-primary-foreground transition hover:opacity-90">
                            Kirim permintaan
                        </a>

                        @if ($whatsapp)
                            {{-- Pesannya sudah terisi angka yang sedang terlihat:
                                 tim langsung punya konteksnya di pesan pertama,
                                 dan pemohon tidak perlu mengetik ulang apa pun. --}}
                            <a :href="`https://wa.me/{{ $whatsapp }}?text=${pesanWa}`"
                               target="_blank" rel="noopener"
                               class="flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-background px-5 py-3 text-center text-sm font-semibold transition hover:bg-muted">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.5 14.4c-.3-.2-1.7-.9-2-1-.3-.1-.5-.2-.7.1-.2.3-.7 1-.9 1.2-.2.2-.3.2-.6.1-.3-.2-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.5-.5c.1-.2.2-.3.3-.5 0-.2 0-.4 0-.5 0-.2-.7-1.6-.9-2.2-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2"/></svg>
                                Chat WhatsApp
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Formulir ===================== --}}
        <div id="minta-penawaran" class="mt-12 scroll-mt-24 border-t border-border pt-10">
            <div class="mx-auto max-w-2xl">
                <h2 class="text-lg font-semibold">Kirim permintaan penawaran</h2>
                <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                    Perkiraan yang Anda susun di atas ikut terkirim. Tim kami menghubungi Anda dalam
                    1&times;24 jam pada hari kerja.
                </p>

                <form method="POST" action="{{ route('enterprise.contact') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                    @csrf

                    {{-- Angka dari kalkulator dititipkan lewat input tersembunyi
                         yang terikat ke keadaan Alpine yang sama — jadi yang
                         terkirim persis yang terlihat, bukan nilai bawaan yang
                         lupa ikut berubah. Server tetap menghitung ulang
                         totalnya; ini cuma susunan kebutuhannya. --}}
                    <input type="hidden" name="estimated_sessions" :value="nomorAman">
                    <input type="hidden" name="estimated_messages" :value="pesanAman">
                    <input type="hidden" name="want_retention_months" :value="retensi">
                    <input type="hidden" name="want_api_rate" :value="api">
                    <input type="hidden" name="want_onboarding" :value="pendampingan ? 1 : 0">

                    @foreach ([
                        ['name', 'Nama Anda', 'text', 'Nama lengkap', true],
                        ['company', 'Perusahaan', 'text', 'PT Contoh Nusantara', false],
                        ['email', 'Email', 'email', 'nama@perusahaan.co.id', true],
                        ['phone', 'Nomor WhatsApp', 'tel', '08xxxxxxxxxx', true],
                    ] as $bidang)
                        <div>
                            <label for="ent_{{ $bidang[0] }}" class="mb-1 block text-xs font-semibold">
                                {{ $bidang[1] }}@unless ($bidang[4])<span class="font-normal text-muted-foreground"> (opsional)</span>@endunless
                            </label>
                            <input id="ent_{{ $bidang[0] }}" name="{{ $bidang[0] }}" type="{{ $bidang[2] }}" @required($bidang[4])
                                   value="{{ old($bidang[0]) }}" placeholder="{{ $bidang[3] }}"
                                   class="w-full rounded-xl border-border bg-background text-sm focus:border-primary focus:ring-primary">
                            @error($bidang[0])<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div class="sm:col-span-2">
                        <label for="ent_needs" class="mb-1 block text-xs font-semibold">
                            Kebutuhan khusus <span class="font-normal text-muted-foreground">(opsional)</span>
                        </label>
                        <textarea id="ent_needs" name="needs" rows="4" maxlength="2000"
                                  placeholder="Sistem yang akan disambungkan, jumlah cabang, jam sibuk, atau syarat lain yang belum masuk hitungan di atas."
                                  class="w-full rounded-xl border-border bg-background text-sm focus:border-primary focus:ring-primary">{{ old('needs') }}</textarea>
                        @error('needs')<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                        <button class="rounded-xl bg-primary px-7 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90">
                            Kirim permintaan
                        </button>
                        <p class="text-xs text-muted-foreground">
                            Data Anda hanya dipakai untuk menyusun penawaran ini.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
