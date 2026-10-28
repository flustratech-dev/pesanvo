<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu panel admin.
 *
 * Menjawab 404, bukan 403. Halaman yang menjawab "dilarang" memberi tahu bahwa
 * di alamat itu ada sesuatu — dan alamatnya mudah ditebak. Bagi siapa pun yang
 * bukan super admin, panel ini sebaiknya tidak tampak pernah ada.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 404);

        return $next($request);
    }
}
