<x-mail::message>
# Tes email keluar

Kalau surat ini sampai, jalur email sudah benar: tagihan terbit, pembayaran diterima, dan
pengingat masa berlaku akan terkirim lewat pengirim yang sama.

<x-mail::table>
| | |
|:---|---:|
| Pengirim | {{ $pengirim }} |
| Mailer | {{ $mailer }} |
| Dikirim | {{ $dikirimPada }} |
</x-mail::table>

Surat ini dikirim dari halaman Pemberitahuan & Pengecualian di panel admin. Tidak ada
tindakan yang perlu diambil penerimanya.

{{ config('app.name') }}
</x-mail::message>
