@props(['kunci', 'versi' => 1, 'langkah' => []])

{{--
    Tur pengenalan, dirender hanya untuk yang belum pernah melihatnya.

    Kalau tidak berhak, TIDAK ADA satu byte pun yang dikirim — bukan sekadar
    disembunyikan CSS. Langkah tur memuat kalimat yang menjelaskan seluruh
    produk, dan mengirimkannya ke setiap halaman untuk semua orang adalah beban
    yang dibayar selamanya demi sesuatu yang dipakai sekali.

    Datanya dititipkan sebagai <script type="application/json">, bukan di-echo
    sebagai kode JS — pola yang sama dengan partials/pesan-server, dan dengan
    alasan yang sama: teks yang berasal dari data tidak boleh bisa merusak atau
    menyisipkan sesuatu ke halaman.
--}}
@php
    $pengguna = auth()->user();
    $tampilkan = $pengguna && count($langkah) > 0 && ! $pengguna->hasSeenGuide($kunci, $versi);
@endphp

@if ($tampilkan)
    <script type="application/json" data-tur-pengenalan>{!! json_encode([
        'ackUrl' => route('onboarding.guides.ack', ['guideKey' => $kunci]),
        'versi' => $versi,
        'langkah' => $langkah,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
@endif
