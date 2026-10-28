@php
    $siteName = config('app.name', 'Pesanvo Gateway');
    $defaultTitle = 'Pesanvo Gateway — Layanan WhatsApp API Terpercaya untuk Aplikasi & Bisnis';
    $defaultDescription = 'Gateway WhatsApp terpusat untuk aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi handal tanpa putus.';

    $finalTitle = $seoTitle ?? ($title ?? $defaultTitle);
    $finalDescription = $seoDescription ?? ($description ?? $defaultDescription);
    $finalRobots = $seoRobots ?? ($robots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    $finalImage = $seoImage ?? ($image ?? asset('images/pesanvo.png'));
    $canonicalUrl = $canonicalUrl ?? url()->current();
    $googleSiteVerification = config('services.google.site_verification');

    // Ukuran og:image dibaca dari berkasnya sendiri. Yang dideklarasikan mati
    // pernah menyebut 1200x630 untuk berkas 549x303, dan pratinjau tautan di
    // WhatsApp dirender terpotong — justru di kanal yang produk ini jual.
    $ukuranGambar = null;
    $berkasGambar = public_path(ltrim((string) parse_url($finalImage, PHP_URL_PATH), '/'));
    if (is_file($berkasGambar)) {
        $ukuranGambar = @getimagesize($berkasGambar) ?: null;
    }

    // Alamat aplikasi induk dibaca dari sumber yang sama dengan tautan di footer,
    // supaya keduanya tidak bisa menunjuk alamat yang berbeda.
    $pesanvoUrl = rtrim((string) (config('pesanvo.produk')['Pesanvo Cloud'] ?? 'https://pesanvo.id'), '/');

    // Harga dibaca dari config/plans.php lewat Plan::all(), yang sudah menyisakan
    // persis paket berlangganan bulanan yang benar-benar dijual. Menulis angka
    // mati di sini berarti schema dan halaman harga bisa menjanjikan hal berbeda.
    $hargaPaket = collect(\App\Support\Plan::all())
        ->map(fn (\App\Support\Plan $paket) => $paket->monthlyPrice())
        ->filter(fn (int $harga) => $harga > 0)
        ->values();

    $nomorKontak = \App\Support\KontakWhatsApp::nomor();

    // Entitas Pesanvo Gateway.
    //
    // `sameAs` sengaja belum ada: menautkan profil yang belum dibuat tidak
    // menambah sinyal apa pun, dan tautan mati justru melemahkannya.
    //
    // `parentOrganization` menunjuk aplikasi induk, BUKAN vendor pengembang.
    // Tanpa relasi ini subdomain terbaca sebagai situs asing yang kebetulan
    // bernama mirip; dengan vendor di dalamnya, identitas mereknya justru kabur.
    // `@id`-nya wajib sama persis dengan yang ditulis repo pesanvo — beda satu
    // garis miring, relasinya tidak terbaca.
    $schemaData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            array_filter([
                '@type' => 'Organization',
                '@id' => url('/').'#organization',
                'name' => 'Pesanvo Gateway',
                'alternateName' => [
                    'Pesanvo WhatsApp API',
                    'Pesanvo API',
                    'WA Gateway Pesanvo',
                ],
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/android-chrome-512x512.png'),
                    'width' => 512,
                    'height' => 512,
                    'caption' => 'Pesanvo Gateway',
                ],
                'image' => asset('images/android-chrome-512x512.png'),
                'description' => 'Penyedia infrastruktur WhatsApp Gateway API berkinerja tinggi di Indonesia untuk integrasi aplikasi, OTP, dan pesan transaksi.',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressCountry' => 'ID',
                ],
                'areaServed' => [
                    '@type' => 'Country',
                    'name' => 'Indonesia',
                ],
                'contactPoint' => $nomorKontak ? [
                    '@type' => 'ContactPoint',
                    'telephone' => '+'.$nomorKontak,
                    'contactType' => 'customer support',
                    'areaServed' => 'ID',
                    'availableLanguage' => ['Indonesian', 'English'],
                ] : null,
                'parentOrganization' => [
                    '@type' => 'Organization',
                    '@id' => $pesanvoUrl.'#organization',
                    'name' => 'Pesanvo',
                    'url' => $pesanvoUrl,
                ],
            ]),
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'url' => url('/'),
                'name' => 'Pesanvo Gateway',
                'alternateName' => [
                    'Pesanvo WhatsApp API',
                    'WA Gateway Pesanvo',
                ],
                'description' => 'Gateway WhatsApp untuk Aplikasi Anda — REST API, Webhook, dan Multi-Session',
                'publisher' => [
                    '@id' => url('/').'#organization',
                ],
                'inLanguage' => 'id-ID',
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'Pesanvo Gateway REST API',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'All',
                'image' => asset('images/android-chrome-512x512.png'),
                'description' => 'Layanan REST API WhatsApp Gateway multi-perangkat untuk pengiriman notifikasi, tagihan, dan pesan pelanggan otomatis.',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'Pesanvo',
                ],
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'priceCurrency' => 'IDR',
                    'lowPrice' => (string) ($hargaPaket->min() ?: 149000),
                    'highPrice' => (string) ($hargaPaket->max() ?: 449000),
                    'offerCount' => (string) max(1, $hargaPaket->count()),
                    'priceValidUntil' => '2028-12-31',
                    'availability' => 'https://schema.org/InStock',
                    'url' => url('/#harga'),
                ],
            ],
        ],
    ];

    // Remah jejak. Halaman yang mengirim $breadcrumbs mendapat jalur navigasi di
    // hasil pencarian menggantikan URL mentah — untuk dokumentasi yang bercabang,
    // itu satu-satunya cara pembaca tahu di bagian mana ia mendarat. Butir
    // terakhir sengaja tanpa 'item' karena itu halaman yang sedang dibuka.
    if (! empty($breadcrumbs ?? [])) {
        $schemaData['@graph'][] = [
            '@type' => 'BreadcrumbList',
            '@id' => $canonicalUrl.'#breadcrumb',
            'itemListElement' => collect($breadcrumbs)->values()
                ->map(fn (array $remah, int $i) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $remah['name'],
                    'item' => $remah['url'] ?? null,
                ]))->all(),
        ];
    }
@endphp

<title>{{ $finalTitle }}</title>
<meta name="title" content="{{ $finalTitle }}">
<meta name="description" content="{{ $finalDescription }}">
<meta name="robots" content="{{ $finalRobots }}">
<meta name="author" content="Pesanvo">
<link rel="canonical" href="{{ $canonicalUrl }}">

@if(!empty($googleSiteVerification))
<meta name="google-site-verification" content="{{ $googleSiteVerification }}">
@endif

<!-- Geo Targeting (Dominasi Pencarian Lokal Indonesia) -->
<meta name="geo.region" content="ID-JK">
<meta name="geo.placename" content="Jakarta, Indonesia">
<meta name="geo.position" content="-6.2088;106.8456">
<meta name="ICBM" content="-6.2088, 106.8456">

<!-- Open Graph / Facebook / WhatsApp Preview -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $finalTitle }}">
<meta property="og:description" content="{{ $finalDescription }}">
<meta property="og:image" content="{{ $finalImage }}">
<meta property="og:image:alt" content="{{ $siteName }}">
@if($ukuranGambar)
<meta property="og:image:width" content="{{ $ukuranGambar[0] }}">
<meta property="og:image:height" content="{{ $ukuranGambar[1] }}">
@endif
<meta property="og:locale" content="id_ID">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $canonicalUrl }}">
<meta name="twitter:title" content="{{ $finalTitle }}">
<meta name="twitter:description" content="{{ $finalDescription }}">
<meta name="twitter:image" content="{{ $finalImage }}">

<!-- Schema.org JSON-LD Structured Data -->
<script type="application/ld+json">
{!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
