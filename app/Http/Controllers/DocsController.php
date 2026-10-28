<?php

namespace App\Http\Controllers;

use App\Support\DocsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Dokumentasi sebagai halaman web. Terbuka untuk publik — isinya penjelasan
 * cara kerja dan cara memakai, bukan data pelanggan.
 */
class DocsController extends Controller
{
    public function index(): View
    {
        return view('docs.index', [
            'catalogue' => DocsRepository::catalogue(),
        ]);
    }

    public function show(string $slug): View
    {
        return view('docs.show', [
            'catalogue' => DocsRepository::catalogue(),
            'page' => DocsRepository::page($slug),
            'activeSlug' => $slug,
        ]);
    }

    public function raw(string $slug): Response
    {
        $content = DocsRepository::raw($slug);

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$slug}.md\"",
        ]);
    }
}
