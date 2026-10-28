<x-mail::message>
# {{ $isForAdmin ? 'Permintaan Pencairan Dana Komisi Mitra' : 'Bukti Pengajuan Pencairan Komisi Mitra' }}

@if ($isForAdmin)
Halo Admin **{{ config('app.name') }}**,

Ada permintaan pencairan dana komisi baru dari mitra/reseller yang membutuhkan verifikasi dan transfer manual Anda:
@else
Halo **{{ $user->name }}**,

Permohonan pencairan dana komisi kemitraan Anda telah berhasil kami terima dan masuk ke dalam antrean pemrosesan tim Finance kami:
@endif

### Rincian Dokumen & Keuangan:
- **No. Invoice**: `{{ $payout->payout_number }}`
- **Nama Mitra**: {{ $user->name }} ({{ $user->email }})
- **Nomor WhatsApp**: {{ $payout->referralCode?->whatsapp_number ?? ($user->phone ?? '—') }}
- **Kode Referal**: `{{ $payout->referralCode?->code }}`
- **Komisi Bruto**: Rp {{ number_format($payout->amount, 0, ',', '.') }}
- **Potongan Administrasi ({{ $payout->fee_percent }}%)**: - Rp {{ number_format($payout->fee_amount, 0, ',', '.') }}
- **Nominal Transfer Bersih**: **Rp {{ number_format($payout->net_amount, 0, ',', '.') }}**

### Informasi Rekening Tujuan:
- **Bank / E-Wallet**: **{{ $payout->bank_name }}**
- **Nomor Rekening**: **{{ $payout->bank_account_number }}**
- **Nama Pemilik Rekening**: **{{ $payout->bank_account_name }}**

> Terlampir berkas dokumen invoice PDF resmi (`Invoice-{{ $payout->payout_number }}.pdf`) sebagai bukti transaksi yang sah.

@if ($isForAdmin)
<x-mail::button :url="$adminUrl">
Buka Panel Admin Reseller
</x-mail::button>

Silakan lakukan transfer manual sesuai informasi rekening di atas, kemudian tandai status pencairan menjadi "Sudah Ditransfer" di panel admin.
@else
<x-mail::button :url="$mitraUrl">
Lihat Invoice Pencairan Online
</x-mail::button>

Dana akan ditransfer manual ke rekening Anda dalam waktu 1x24 jam kerja. Setelah transfer selesai, sistem akan mengirimkan konfirmasi pelunasan otomatis.
@endif

Salam,<br>
Tim Finance {{ config('app.name') }}
</x-mail::message>
