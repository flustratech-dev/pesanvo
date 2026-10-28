@extends('layouts.app')
@section('title', 'Profil Akun')

@section('content')
    <div class="space-y-8" x-data="{
        sembunyikanSensitif: true,
        toggleSensitif() { this.sembunyikanSensitif = !this.sembunyikanSensitif; }
    }">
        {{-- ===================== Header & Info Akun ===================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Profil Akun</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Kelola identitas pribadi dan keamanan akun Anda.
                </p>
            </div>

            <button type="button" @click="toggleSensitif()"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-3.5 py-2 text-xs sm:text-sm font-medium transition hover:bg-muted self-start sm:self-auto">
                <template x-if="sembunyikanSensitif">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Tampilkan Data Sensitif</span>
                    </span>
                </template>
                <template x-if="!sembunyikanSensitif">
                    <span class="flex items-center gap-1.5 text-primary font-semibold">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        <span>Sembunyikan Data Sensitif</span>
                    </span>
                </template>
            </button>
        </div>

        {{-- ===================== Kartu Ringkasan Data Diri ===================== --}}
        <div class="rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-xs">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-4">
                Ringkasan Identitas Akun
            </h3>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                {{-- Avatar Icon (Tanpa Foto) --}}
                <div class="relative shrink-0">
                    <div class="rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0"
                         style="width: 52px; height: 52px; min-width: 52px; min-height: 52px; border-radius: 9999px; aspect-ratio: 1 / 1;">
                        <svg class="text-primary shrink-0" style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                {{-- Detail Diri --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 flex-1 w-full text-xs sm:text-sm">
                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Nama Lengkap</span>
                        <span class="font-semibold text-foreground">{{ $user->name }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Email</span>
                        <span class="text-foreground">{{ $user->email }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Nomor Telepon / WA</span>
                        @if ($user->phone)
                            <span x-show="sembunyikanSensitif" class="font-mono text-muted-foreground">
                                {{ substr($user->phone, 0, 4) . '••••' . substr($user->phone, -3) }}
                            </span>
                            <span x-show="!sembunyikanSensitif" x-cloak class="font-mono text-foreground font-semibold">
                                {{ $user->phone }}
                            </span>
                        @else
                            <span class="text-muted-foreground italic">Belum diisi</span>
                        @endif
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Perusahaan / Usaha</span>
                        <span class="text-foreground">{{ $user->company ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Kota / Domisili</span>
                        <span class="text-foreground">{{ $user->city ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Status Mitra</span>
                        @if ($user->referralCode)
                            @if ($user->referralCode->isApproved())
                                <span class="inline-flex items-center gap-1 font-semibold text-primary">
                                    <span class="h-2 w-2 rounded-full bg-primary"></span>
                                    Mitra Aktif (Kode: <span class="font-mono">{{ $user->referralCode->code }}</span>)
                                </span>
                            @elseif ($user->referralCode->isPending())
                                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    Menunggu Verifikasi Admin
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-destructive font-medium">
                                    Belum Disetujui
                                </span>
                            @endif
                        @else
                            <a href="{{ route('mitra.index') }}" class="text-primary hover:underline">Daftar Mitra</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Edit Data Pribadi ===================== --}}
        <x-section judul="Perbarui Informasi Pribadi"
                   sub="Lengkapi identitas diri Anda sebagai pelengkap akun.">
            <form method="POST" action="{{ route('profile.update') }}" class="max-w-3xl space-y-5">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Nama Lengkap <span class="text-destructive">*</span>
                        </label>
                        <input type="text" name="name" id="name"
                               value="{{ old('name', $user->name) }}"
                               required maxlength="80"
                               class="w-full rounded-xl border @error('name') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('name')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Alamat Email (Tetap)
                        </label>
                        <input type="email" id="email" value="{{ $user->email }}" disabled
                               class="w-full rounded-xl border border-input bg-muted/50 px-4 py-2.5 text-sm text-muted-foreground cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Nomor WhatsApp / Telepon
                        </label>
                        <input type="text" name="phone" id="phone"
                               value="{{ old('phone', $user->phone) }}"
                               placeholder="Contoh: 081234567890" maxlength="30"
                               class="w-full rounded-xl border @error('phone') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('phone')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Perusahaan / Usaha
                        </label>
                        <input type="text" name="company" id="company"
                               value="{{ old('company', $user->company) }}"
                               placeholder="Nama bisnis atau instansi" maxlength="100"
                               class="w-full rounded-xl border @error('company') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('company')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Kota / Kabupaten
                        </label>
                        <input type="text" name="city" id="city"
                               value="{{ old('city', $user->city) }}"
                               placeholder="Kota domisili Anda" maxlength="100"
                               class="w-full rounded-xl border @error('city') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('city')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Alamat Lengkap
                        </label>
                        <input type="text" name="address" id="address"
                               value="{{ old('address', $user->address) }}"
                               placeholder="Alamat tempat tinggal / kantor" maxlength="500"
                               class="w-full rounded-xl border @error('address') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('address')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="bio" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                        Bio Singkat
                    </label>
                    <textarea name="bio" id="bio" rows="2" maxlength="500"
                              placeholder="Deskripsi singkat mengenai Anda atau bidang keahlian Anda"
                              class="w-full rounded-xl border @error('bio') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">{{ old('bio', $user->bio) }}</textarea>
                    @error('bio')
                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20">
                        Simpan Data Profil
                    </button>
                </div>
            </form>
        </x-section>

        {{-- ===================== Ubah Kata Sandi ===================== --}}
        <x-section judul="Keamanan Kata Sandi"
                   sub="Pastikan kata sandi Anda menggunakan minimal 8 karakter dengan kombinasi huruf dan angka.">
            <form method="POST" action="{{ route('profile.password') }}" class="max-w-2xl space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                        Kata Sandi Saat Ini
                    </label>
                    <input type="password" name="current_password" id="current_password"
                           required autocomplete="current-password"
                           class="w-full rounded-xl border @error('current_password') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                    @error('current_password')
                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Kata Sandi Baru
                        </label>
                        <input type="password" name="password" id="password"
                               required autocomplete="new-password"
                               placeholder="Minimal 8 karakter"
                               class="w-full rounded-xl border @error('password') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('password')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Konfirmasi Kata Sandi Baru
                        </label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               required autocomplete="new-password"
                               placeholder="Ulangi kata sandi baru"
                               class="w-full rounded-xl border @error('password_confirmation') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('password_confirmation')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold text-foreground shadow-xs transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary/20">
                        Perbarui Kata Sandi
                    </button>
                </div>
            </form>
        </x-section>

        {{-- ===================== Dua faktor ===================== --}}
        <x-section judul="Autentikasi dua faktor"
                   sub="Lapisan keamanan tambahan di luar kata sandi menggunakan aplikasi authenticator di ponsel Anda.">
            <x-slot:aksi>
                @if ($user->duaFaktorAktif())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                        <span class="h-2 w-2 rounded-full bg-muted-foreground/50"></span> Belum Aktif
                    </span>
                @endif
            </x-slot:aksi>

            {{-- Kode pemulihan hanya ditampilkan LEWAT FLASH, sekali, tepat
                 setelah 2FA dinyalakan. Kalau ia bisa dibuka lagi kapan saja,
                 ia berhenti menjadi faktor kedua: sesi peramban yang tertinggal
                 terbuka cukup untuk membacanya. --}}
            @if (session('kodePemulihan'))
                <div x-data="{
                    kodes: @js(session('kodePemulihan')),
                    disalin: false,
                    unduhCsv() {
                        const csvContent = 'No,Kode Pemulihan\r\n' + this.kodes.map((k, i) => `${i + 1},${k}`).join('\r\n');
                        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'pesanvo-kode-pemulihan-2fa.csv';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                    },
                    unduhTxt() {
                        const txtContent = 'PESANVO GATEWAY - KODE PEMULIHAN 2FA\r\n'
                            + 'Tanggal: ' + new Date().toLocaleDateString('id-ID') + '\r\n'
                            + 'Simpan berkas ini di tempat aman. Setiap kode hanya berlaku sekali.\r\n\r\n'
                            + this.kodes.map((k, i) => `${i + 1}. ${k}`).join('\r\n');
                        const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'pesanvo-kode-pemulihan-2fa.txt';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                    },
                    salinSemua() {
                        const teks = this.kodes.join('\n');
                        navigator.clipboard.writeText(teks).then(() => {
                            this.disalin = true;
                            setTimeout(() => this.disalin = false, 2500);
                        });
                    }
                }" class="mb-6 rounded-2xl border border-amber-500/40 bg-amber-500/5 p-4 sm:p-5 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <i class="bi bi-shield-lock-fill text-amber-600 dark:text-amber-400"></i>
                                <p class="font-semibold text-foreground">Simpan 8 kode pemulihan ini sekarang</p>
                            </div>
                            <p class="mt-1 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                Ini <strong>satu-satunya kali</strong> kode ini ditampilkan. Simpan di tempat yang bukan
                                ponsel yang sama — inilah jalan masuk Anda kalau ponselnya hilang. Tiap kode berlaku sekali.
                            </p>
                        </div>

                        {{-- Tombol Aksi: Unduh CSV, Unduh TXT, Salin Semua --}}
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button type="button"
                                    @click="unduhCsv()"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-600/30 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 transition shadow-2xs cursor-pointer"
                                    title="Unduh 8 kode pemulihan dalam format spreadsheet CSV">
                                <i class="bi bi-file-earmark-spreadsheet-fill text-emerald-600 dark:text-emerald-400"></i>
                                <span>Unduh CSV</span>
                            </button>

                            <button type="button"
                                    @click="unduhTxt()"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted transition shadow-2xs cursor-pointer"
                                    title="Unduh berkas teks .txt">
                                <i class="bi bi-file-text"></i>
                                <span>Unduh TXT</span>
                            </button>

                            <button type="button"
                                    @click="salinSemua()"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted transition shadow-2xs cursor-pointer"
                                    title="Salin seluruh kode ke clipboard">
                                <i class="bi" :class="disalin ? 'bi-check2 text-emerald-500' : 'bi-clipboard'"></i>
                                <span x-text="disalin ? 'Tersalin!' : 'Salin Semua'">Salin Semua</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                        @foreach (session('kodePemulihan') as $kode)
                            <span class="select-all rounded-xl bg-card border border-border/80 px-2.5 py-2 text-center font-medium shadow-2xs text-foreground">{{ $kode }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($user->duaFaktorAktif())
                <div class="rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-xs max-w-3xl space-y-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-3 py-1 text-sm text-emerald-700 dark:text-emerald-400 font-medium">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Aktif
                        </span>
                        <span class="text-sm text-muted-foreground">
                            Menyala sejak {{ $user->two_factor_confirmed_at->translatedFormat('j F Y') }} ·
                            {{ count($user->two_factor_recovery_codes ?? []) }} kode pemulihan tersisa
                        </span>
                    </div>

                    {{-- Tombol Tindakan Kode Pemulihan --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('two-factor.recovery-codes.csv') }}"
                           download
                           class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-600/30 bg-emerald-500/10 px-3.5 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 transition shadow-2xs cursor-pointer"
                           title="Unduh seluruh sisa kode pemulihan dalam format spreadsheet CSV">
                            <i class="bi bi-file-earmark-spreadsheet-fill text-emerald-600 dark:text-emerald-400"></i>
                            <span>Unduh CSV Kode Pemulihan</span>
                        </a>

                        @unless (session('kodePemulihan'))
                            <details class="inline-block">
                                <summary class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground hover:bg-muted transition shadow-2xs cursor-pointer list-none">
                                    <i class="bi bi-eye"></i>
                                    <span>Tampilkan Ulang di Layar</span>
                                </summary>
                                <form method="POST" action="{{ route('two-factor.recovery-codes.show') }}" class="mt-3 flex flex-wrap items-center gap-2 p-3 rounded-xl border border-border bg-muted/30">
                                    @csrf
                                    <input type="password" name="password" required placeholder="Kata sandi akun Anda"
                                           class="rounded-lg border-input bg-background text-xs px-3 py-1.5 focus:border-primary focus:ring-primary min-w-[200px]">
                                    <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:opacity-90 transition">
                                        Buka Kode
                                    </button>
                                </form>
                            </details>
                        @endunless

                        <details class="inline-block">
                            <summary class="inline-flex items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-500/20 transition shadow-2xs cursor-pointer list-none"
                                     title="Terbitkan 8 kode baru jika kode lama sudah habis atau hilang">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Buat Ulang 8 Kode Baru</span>
                            </summary>
                            <form method="POST" action="{{ route('two-factor.recovery-codes.regenerate') }}" class="mt-3 flex flex-wrap items-center gap-2 p-3 rounded-xl border border-amber-500/30 bg-amber-500/5"
                                  data-konfirmasi="Seluruh kode pemulihan lama akan hangus dan digantikan dengan 8 kode baru. Lanjutkan?">
                                @csrf
                                <input type="password" name="password" required placeholder="Kata sandi akun Anda"
                                       class="rounded-lg border-input bg-background text-xs px-3 py-1.5 focus:border-amber-500 focus:ring-amber-500 min-w-[200px]">
                                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 transition">
                                    Terbitkan 8 Kode Baru
                                </button>
                            </form>
                        </details>
                    </div>

                    <div class="pt-3 border-t border-border/60">
                        <form method="POST" action="{{ route('two-factor.disable') }}"
                              data-konfirmasi="Setelah 2FA dilepas, akun Anda hanya dilindungi oleh kata sandi. Apakah Anda yakin ingin melepas autentikasi 2FA?"
                              data-konfirmasi-judul="Lepas Autentikasi 2FA"
                              data-konfirmasi-ya="Ya, Lepas 2FA">
                            @csrf
                            @method('DELETE')
                            <div class="space-y-1 mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-foreground" for="pw_2fa">
                                    Lepas / Matikan 2FA (Masukkan Kata Sandi)
                                </label>
                                <p class="text-xs text-muted-foreground">
                                    Masukkan kata sandi akun Anda untuk melepaskan proteksi 2FA. Berlaku untuk akun pengguna maupun administrator.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 max-w-md">
                                <input id="pw_2fa" type="password" name="password" required autocomplete="current-password"
                                       placeholder="Kata sandi akun Anda..."
                                       class="min-w-56 flex-1 rounded-xl border-input bg-background text-sm focus:border-primary focus:ring-primary px-3.5 py-2">
                                <button type="submit" class="rounded-xl border border-destructive/40 bg-card px-4 py-2 text-xs font-semibold text-destructive transition hover:bg-destructive hover:text-white shadow-2xs">
                                    Lepas / Matikan 2FA
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </form>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-xs max-w-3xl space-y-5">
                    <div class="flex flex-col sm:flex-row items-start gap-4">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-primary/10 grid place-items-center text-primary">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-foreground">Lindungi akun dengan verifikasi dua langkah</h3>
                            <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                Dengan 2FA, kata sandi yang bocor saja tidak cukup untuk masuk ke akun Anda — penyerang juga harus memegang ponsel Anda. Setiap kali masuk, Anda akan diminta memasukkan 6 digit kode dari aplikasi authenticator.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="rounded-xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <i class="bi bi-phone text-primary"></i> Kompatibel Luas
                            </p>
                            <p class="text-[11px] sm:text-xs text-muted-foreground leading-relaxed">
                                Mendukung Google Authenticator, Microsoft Authenticator, 1Password, Authy, dan aplikasi TOTP lainnya.
                            </p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <i class="bi bi-wifi-off text-primary"></i> Berjalan Tanpa Internet
                            </p>
                            <p class="text-[11px] sm:text-xs text-muted-foreground leading-relaxed">
                                Kode dibuat langsung di ponsel Anda secara matematis, sehingga tetap bekerja tanpa sinyal seluler atau internet.
                            </p>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row sm:items-center gap-3">
                        <a href="{{ route('two-factor.setup') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <i class="bi bi-shield-plus"></i>
                            <span>Nyalakan 2FA</span>
                        </a>
                        <span class="text-xs text-muted-foreground">
                            Hanya butuh 1 menit: scan kode QR lalu masukkan 6 digit kode pertama.
                        </span>
                    </div>
                </div>
            @endif
        </x-section>

        {{-- ===================== Hapus akun =====================

             Berbingkai dan merah: ini satu-satunya tindakan di seluruh produk
             yang benar-benar tidak bisa dibatalkan setelah masa tunggunya lewat.

             Dampaknya disebutkan satu per satu SEBELUM tombolnya, bukan sesudah.
             Yang paling sering tidak disadari: workspace yang masih punya
             anggota lain TIDAK ikut terhapus — ia berpindah pemilik, karena
             riwayat percakapan di dalamnya milik orang lain juga.
             ======================================================= --}}
        @if (! $user->is_super_admin)
            <div class="mt-10 rounded-xl border border-destructive/40 bg-destructive/5 p-5">
                @if ($user->deletion_scheduled_for)
                    <p class="font-semibold text-destructive">Akun ini dijadwalkan dihapus</p>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Penghapusan permanen berlangsung pada
                        <strong>{{ $user->deletion_scheduled_for->translatedFormat('j F Y') }}</strong>
                        ({{ (int) now()->diffInDays($user->deletion_scheduled_for, false) }} hari lagi).
                        Sampai saat itu layanan Anda tetap berjalan seperti biasa, dan Anda masih bisa membatalkannya.
                    </p>

                    <form method="POST" action="{{ route('profile.delete.cancel') }}" class="mt-4">
                        @csrf
                        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                            Batalkan penghapusan
                        </button>
                    </form>
                @else
                    <p class="font-semibold text-destructive">Hapus akun</p>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Permintaan ini <strong>tidak langsung dijalankan</strong>. Akun Anda dihapus permanen
                        {{ config('legal.retention.account_grace_days') }} hari setelah permintaan, dan sampai
                        tanggal itu Anda bisa membatalkannya sendiri dari halaman ini.
                    </p>

                    <div class="mt-4 space-y-3 text-sm">
                        <p class="font-medium">Yang akan terjadi:</p>
                        <ul class="list-disc space-y-1.5 pl-5 text-muted-foreground">
                            @if ($dampak['dihapus']->isNotEmpty())
                                <li>
                                    <strong class="text-destructive">{{ $dampak['dihapus']->count() }} workspace ikut dihapus permanen</strong>
                                    ({{ $dampak['dihapus']->pluck('name')->join(', ') }}) — beserta seluruh
                                    riwayat pesan, nomor WhatsApp, API key, dan template di dalamnya.
                                </li>
                            @endif

                            @if ($dampak['dialihkan']->isNotEmpty())
                                <li>
                                    {{ $dampak['dialihkan']->count() }} workspace
                                    ({{ $dampak['dialihkan']->pluck('name')->join(', ') }})
                                    <strong>tidak dihapus</strong> — kepemilikannya berpindah ke anggota terlama,
                                    karena riwayat di dalamnya milik mereka juga.
                                </li>
                            @endif

                            @if ($dampak['saldo'] > 0)
                                <li>
                                    Saldo tersisa <strong>Rp {{ number_format($dampak['saldo'], 0, ',', '.') }}</strong>.
                                    Hubungi kami lewat <a href="{{ route('tickets.index') }}" class="underline">Bantuan</a>
                                    untuk meminta pengembaliannya <em>sebelum</em> tanggal penghapusan — sesudah itu tidak ada lagi yang bisa kami kembalikan.
                                </li>
                            @endif

                            <li>
                                Riwayat tagihan <strong>tetap kami simpan</strong> tanpa kaitan ke identitas Anda,
                                karena dokumen pembukuan wajib disimpan {{ config('legal.retention.billing_years') }} tahun
                                menurut ketentuan perpajakan. Dijelaskan di
                                <a href="{{ route('docs.show', 'kebijakan-privasi') }}" class="underline">Kebijakan Privasi</a>.
                            </li>
                        </ul>

                        <p class="text-muted-foreground">
                            Ingin menyimpan riwayat percakapan Anda lebih dulu? Ada di
                            <a href="{{ route('settings') }}" class="underline">Pengaturan → Ekspor data</a>.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('profile.delete.request') }}" class="mt-5"
                          data-konfirmasi="Setelah masa tunggu lewat, ini tidak bisa dibatalkan oleh siapa pun. Lanjutkan?">
                        @csrf

                        @if (filled($user->password))
                            <label class="mb-1 block text-sm font-medium" for="hapus_password">
                                Masukkan kata sandi Anda untuk memastikan
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <input id="hapus_password" type="password" name="password" required autocomplete="current-password"
                                       class="min-w-56 flex-1 rounded-lg border-input bg-background text-sm focus:border-destructive focus:ring-destructive">
                                <button class="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90">
                                    Jadwalkan penghapusan
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        @else
                            {{-- Akun Google tidak punya kata sandi untuk dicocokkan. --}}
                            <label class="mb-1 block text-sm font-medium" for="confirm_email">
                                Ketik <code class="rounded bg-muted px-1">{{ $user->email }}</code> untuk memastikan
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <input id="confirm_email" name="confirm_email" required autocomplete="off"
                                       placeholder="{{ $user->email }}"
                                       class="min-w-56 flex-1 rounded-lg border-input bg-background text-sm focus:border-destructive focus:ring-destructive">
                                <button class="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90">
                                    Jadwalkan penghapusan
                                </button>
                            </div>
                            @error('confirm_email')
                                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        @endif
                    </form>
                @endif
            </div>
        @endif
    </div>
@endsection
