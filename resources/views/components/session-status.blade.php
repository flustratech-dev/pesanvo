@props(['status'])

@php
    // Warna dipilih supaya "butuh tindakan" (qr, failed) langsung menonjol
    // dibanding keadaan normal.
    //
    // Amber dan biru sengaja dipertahankan di tengah tema serba hijau: status
    // harus bisa dibedakan sekilas, dan hijau sudah dipakai untuk "terhubung".
    // Lima keadaan berbeda dengan lima rona hijau tidak akan terbaca.
    $styles = [
        'connected' => ['bg-primary/10 text-primary border border-primary/20', 'Terhubung'],
        'qr' => ['bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20', 'Menunggu scan QR'],
        'connecting' => ['bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/20', 'Menghubungkan'],
        'disconnected' => ['bg-muted text-muted-foreground border border-border', 'Terputus'],
        'failed' => ['bg-destructive/10 text-destructive dark:text-red-400 border border-destructive/20', 'Gagal'],
        'pending' => ['bg-muted text-muted-foreground border border-border', 'Belum dijalankan'],
    ];
    [$class, $label] = $styles[$status] ?? $styles['pending'];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $class }}">{{ $label }}</span>
