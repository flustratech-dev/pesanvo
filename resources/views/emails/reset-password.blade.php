<x-mail::message>
# Permintaan Atur Ulang Kata Sandi

Halo, **{{ $name }}**!

Kami menerima permintaan untuk mengatur ulang kata sandi akun {{ config('app.name') }} Anda. Klik tombol di bawah ini untuk membuat kata sandi baru:

<x-mail::button :url="$url">
Atur Ulang Kata Sandi
</x-mail::button>

Tautan ini hanya berlaku selama **{{ $expiresIn }} menit**.

Jika Anda tidak pernah meminta pengaturan ulang kata sandi, abaikan email ini dan akun Anda tetap aman.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
