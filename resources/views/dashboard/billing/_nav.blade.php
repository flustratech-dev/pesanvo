{{-- Navigasi antar halaman langganan.

     Satu berkas dipakai ketiga halaman. Dua salinan markup yang isinya harus
     sama persis adalah cara tercepat membuat keduanya berbeda diam-diam —
     tab yang hilang di satu halaman berarti jalan buntu bagi yang membukanya
     dari sana. --}}
<nav class="mb-6 flex gap-1 border-b border-border text-sm">
    @foreach ([
        'billing.index' => 'Ringkasan',
        'billing.plans' => 'Paket',
        'billing.history' => 'Riwayat tagihan',
    ] as $route => $label)
        <a href="{{ route($route) }}"
           @class([
               '-mb-px border-b-2 px-4 py-2.5 font-medium transition',
               'border-primary text-primary' => request()->routeIs($route),
               'border-transparent text-muted-foreground hover:border-border hover:text-foreground' => ! request()->routeIs($route),
           ])>
            {{ $label }}
        </a>
    @endforeach
</nav>
