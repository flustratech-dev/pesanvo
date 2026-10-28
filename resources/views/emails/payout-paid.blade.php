<x-mail::message>
# Dana Komisi Kemitraan Berhasil Ditransfer!

Halo **{{ $user->name }}**,

Kabar gembira! Permohonan pencairan komisi kemitraan Anda dengan nomor dokumen **{{ $payout->payout_number }}** telah berhasil ditransfer oleh tim Finance ke rekening Anda.

### Rincian Pelunasan Komisi:
- **No. Invoice**: `{{ $payout->payout_number }}`
- **Waktu Transfer**: {{ $payout->paid_at ? $payout->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }} WIB
- **Komisi Bruto**: Rp {{ number_format($payout->amount, 0, ',', '.') }}
- **Potongan Administrasi ({{ $payout->fee_percent }}%)**: - Rp {{ number_format($payout->fee_amount, 0, ',', '.') }}
- **Nominal Bersih yang Ditransfer**: **Rp {{ number_format($payout->net_amount, 0, ',', '.') }}**

### Rekening Tujuan:
- **Bank / E-Wallet**: **{{ $payout->bank_name }}**
- **Nomor Rekening**: **{{ $payout->bank_account_number }}**
- **Nama Pemilik Rekening**: **{{ $payout->bank_account_name }}**

@if ($payout->admin_notes)
### Catatan Finance:
> {{ $payout->admin_notes }}
@endif

> Terlampir berkas dokumen invoice PDF resmi berstatus **DITRANSFER / LUNAS** (`Invoice-Lunas-{{ $payout->payout_number }}.pdf`) sebagai bukti pembayaran sah.

<x-mail::button :url="$mitraUrl">
Lihat Bukti Invoice Online
</x-mail::button>

Terima kasih atas dedikasi dan kerja sama Anda sebagai mitra resmi Pesanvo. Terus tingkatkan promosi dan dapatkan komisi tanpa batas!

Salam hangat,<br>
Tim Finance {{ config('app.name') }}
</x-mail::message>
