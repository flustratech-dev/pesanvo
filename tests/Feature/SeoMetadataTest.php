<?php

namespace Tests\Feature;

use App\Support\DocsRepository;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_full_seo_metadata(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertStatus(200);

        // Standard Meta
        $response->assertSee('<title>Pesanvo Gateway — Kirim Notifikasi WhatsApp Lewat Satu REST API</title>', false);
        $response->assertSee('name="description"', false);
        // <meta name="keywords"> sengaja tidak ada lagi — tidak dipakai mesin pencari mana pun.
        $response->assertDontSee('name="keywords"', false);
        $response->assertSee('name="robots" content="index, follow', false);
        $response->assertSee('rel="canonical"', false);

        // Geo targeting
        $response->assertSee('name="geo.region" content="ID-JK"', false);
        $response->assertSee('name="geo.placename" content="Jakarta, Indonesia"', false);
        $response->assertSee('name="geo.position" content="-6.2088;106.8456"', false);
        $response->assertSee('name="ICBM" content="-6.2088, 106.8456"', false);

        // Open Graph
        $response->assertSee('property="og:type" content="website"', false);
        $response->assertSee('property="og:site_name" content="Pesanvo Gateway"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('property="og:locale" content="id_ID"', false);

        // Twitter Card
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:title"', false);
        $response->assertSee('name="twitter:description"', false);
        $response->assertSee('name="twitter:image"', false);
    }

    public function test_homepage_renders_google_site_verification_when_configured(): void
    {
        config(['services.google.site_verification' => 'test-google-wa-verification-token-98765']);

        $response = $this->get(route('welcome'));

        $response->assertStatus(200);
        $response->assertSee('name="google-site-verification" content="test-google-wa-verification-token-98765"', false);
    }

    public function test_homepage_renders_valid_json_ld_schema(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('application/ld+json', $content);

        // Extract JSON inside ld+json tag
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD schema tag found');

        $schema = json_decode(trim($matches[1]), true);
        $this->assertIsArray($schema, 'JSON-LD is valid JSON');
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertArrayHasKey('@graph', $schema);

        $types = array_column($schema['@graph'], '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('SoftwareApplication', $types);
    }

    public function test_sitemap_xml_endpoint_returns_valid_xml(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($content));
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('<loc>', $content);
        $this->assertStringContainsString('<lastmod>', $content);
        $this->assertStringContainsString('<changefreq>', $content);
        $this->assertStringContainsString('<priority>', $content);

        // SimpleXML validation
        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml, 'Sitemap is valid XML structure');
        $this->assertGreaterThanOrEqual(25, count($xml->url));
    }

    public function test_robots_txt_contains_sitemap_and_disallows(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $robotsContent = file_get_contents($robotsPath);
        $this->assertStringContainsString('User-agent: *', $robotsContent);
        $this->assertStringContainsString('Allow: /', $robotsContent);
        $this->assertStringContainsString('Disallow: /admin/', $robotsContent);
        $this->assertStringContainsString('Disallow: /dashboard/', $robotsContent);
        $this->assertStringContainsString('Disallow: /api/', $robotsContent);
        $this->assertStringContainsString('Sitemap: https://wa.pesanvo.id/sitemap.xml', $robotsContent);
    }

    public function test_public_subpages_render_seo_metadata(): void
    {
        $pages = [
            route('docs.index'),
            route('status'),
            route('mitra.landing'),
            route('ai.index'),
            route('enterprise'),
        ];

        foreach ($pages as $url) {
            $res = $this->get($url);
            $res->assertStatus(200);
            $res->assertSee('name="description"', false);
            $res->assertSee('rel="canonical"', false);
            $res->assertSee('property="og:title"', false);
            $res->assertSee('name="twitter:card"', false);
        }
    }

    public function test_organization_schema_points_back_at_the_parent_brand(): void
    {
        $organization = $this->findByType($this->schemaGraph(), 'Organization');

        $this->assertContains('Pesanvo WhatsApp API', $organization['alternateName']);

        // Tanpa relasi ini subdomain terbaca sebagai situs asing yang kebetulan
        // bernama mirip. `@id`-nya harus sama persis dengan yang ditulis repo pesanvo.
        $pesanvo = rtrim(config('pesanvo.produk')['Pesanvo Cloud'], '/');
        $this->assertSame($pesanvo.'#organization', $organization['parentOrganization']['@id']);

        // Vendor pengembang bukan bagian dari identitas entitas; kreditnya tetap
        // boleh tampil sebagai teks biasa, tapi tidak sebagai relasi.
        $this->assertStringNotContainsStringIgnoringCase(
            'Created by',
            json_encode($organization),
        );
    }

    public function test_offer_prices_follow_the_plan_config(): void
    {
        $offers = $this->findByType($this->schemaGraph(), 'SoftwareApplication')['offers'];

        $harga = collect(Plan::all())
            ->map(fn (Plan $paket) => $paket->monthlyPrice())
            ->filter(fn (int $h) => $h > 0);

        // Halaman harga dan schema tidak boleh menjanjikan angka yang berbeda.
        $this->assertSame((string) $harga->min(), $offers['lowPrice']);
        $this->assertSame((string) $harga->max(), $offers['highPrice']);
        $this->assertSame((string) $harga->count(), $offers['offerCount']);
    }

    public function test_documentation_pages_carry_a_breadcrumb_trail(): void
    {
        $slug = array_key_first(DocsRepository::flat());

        $breadcrumb = $this->findByType(
            $this->schemaGraph(route('docs.show', $slug)),
            'BreadcrumbList',
        );

        $names = array_column($breadcrumb['itemListElement'], 'name');
        $this->assertSame('Beranda', $names[0]);
        $this->assertSame('Dokumentasi', $names[1]);
        $this->assertCount(3, $names);

        // Butir terakhir adalah halaman yang sedang dibuka, jadi tanpa 'item'.
        $this->assertArrayNotHasKey('item', $breadcrumb['itemListElement'][2]);
    }

    public function test_sitemap_lastmod_is_not_the_request_time(): void
    {
        $xml = simplexml_load_string($this->get(route('sitemap'))->getContent());

        $waktuPermintaan = now()->startOfMinute();
        $sama = 0;

        foreach ($xml->url as $url) {
            if (isset($url->lastmod)
                && Carbon::parse((string) $url->lastmod)->startOfMinute()->eq($waktuPermintaan)) {
                $sama++;
            }
        }

        // Sitemap yang menyebut setiap URL "baru saja berubah" pada setiap
        // permintaan membuat <lastmod> berhenti berarti apa pun bagi Google.
        $this->assertSame(0, $sama, 'lastmod tidak boleh memakai waktu permintaan.');
    }

    public function test_login_page_is_not_indexed(): void
    {
        $this->get(route('login'))->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_favicon_files_are_square_multiples_of_48(): void
    {
        // Google Search mengabaikan favicon yang tidak persegi atau lebih kecil
        // dari 48px, lalu menggantinya dengan ikon bawaan di hasil pencarian.
        // pesanvo.png berukuran 549x303 dan tidak pernah memenuhi syarat itu.
        $this->assertFileExists(public_path('favicon.ico'));

        foreach (['images/favicon-48x48.png' => 48, 'images/favicon-96x96.png' => 96, 'images/android-chrome-192x192.png' => 192] as $file => $size) {
            [$width, $height] = getimagesize(public_path($file));
            $this->assertSame($size, $width, $file);
            $this->assertSame($size, $height, $file);
            $this->assertSame(0, $size % 48, "Ukuran {$size}px harus kelipatan 48.");
        }
    }

    public function test_open_graph_image_dimensions_match_the_actual_file(): void
    {
        $content = $this->get(route('welcome'))->getContent();

        preg_match('/property="og:image" content="([^"]*)"/', $content, $image);
        preg_match('/property="og:image:width" content="(\d+)"/', $content, $width);
        preg_match('/property="og:image:height" content="(\d+)"/', $content, $height);

        [$realWidth, $realHeight] = getimagesize(
            public_path(ltrim(parse_url($image[1], PHP_URL_PATH), '/')),
        );

        // Ukuran yang salah membuat pratinjau tautan dirender terpotong —
        // justru di WhatsApp, kanal yang produk ini jual.
        $this->assertSame($realWidth, (int) $width[1]);
        $this->assertSame($realHeight, (int) $height[1]);
    }

    /** Mengambil @graph JSON-LD dari sebuah halaman. */
    private function schemaGraph(?string $url = null): array
    {
        preg_match(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $this->get($url ?? route('welcome'))->getContent(),
            $m,
        );

        return json_decode(trim($m[1]), true)['@graph'];
    }

    /** Mencari satu simpul bertipe tertentu di dalam @graph. */
    private function findByType(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail("Tidak ada simpul bertipe {$type} di dalam @graph.");
    }
}
