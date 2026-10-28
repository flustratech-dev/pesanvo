{{-- Pesan hasil tindakan, dititipkan ke peramban sebagai JSON.

     Ditulis sebagai <script type="application/json">, bukan sebagai kode JS
     yang di-echo: teks pesan bisa memuat kutip, tanda kurung, atau nama
     workspace milik orang lain, dan satu di antaranya cepat atau lambat akan
     merusak halaman — atau lebih buruk, dieksekusi.

     `swal` diisi sendiri oleh controller untuk momen yang menentukan. Kalau
     tidak ada, spanduk `status`/`errors` di layout tetap muncul seperti biasa
     dan blok ini tidak menghasilkan apa pun. --}}

@php
    $pesanServer = session('swal');

    if (! $pesanServer && $errors->any()) {
        $pesanServer = [
            'icon' => 'error',
            'title' => 'Ada yang perlu diperbaiki',
            'html' => collect($errors->all())->map(fn ($p) => e($p))->implode('<br>'),
        ];
    }
@endphp

@if ($pesanServer)
    <script type="application/json" id="pesan-server">@json($pesanServer)</script>
@endif
