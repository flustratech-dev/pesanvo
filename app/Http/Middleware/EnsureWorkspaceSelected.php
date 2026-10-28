<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan workspace yang sedang dibuka di dashboard dan menaruhnya di request.
 * Semua controller dashboard mengambilnya dari sini, jadi tidak ada satu pun
 * query yang perlu memilih workspace sendiri — sekaligus mencegah workspace A
 * membaca data workspace B.
 */
class EnsureWorkspaceSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $workspaces = $user->workspaces()->orderBy('name')->get();

        if ($workspaces->isEmpty()) {
            return redirect()->route('onboarding.create');
        }

        $selectedId = session('current_workspace_id');
        $workspace = $workspaces->firstWhere('id', $selectedId) ?? $workspaces->first();

        session(['current_workspace_id' => $workspace->id]);

        $request->attributes->set('workspace', $workspace);
        $request->attributes->set('workspaces', $workspaces);

        view()->share('currentWorkspace', $workspace);
        view()->share('availableWorkspaces', $workspaces);

        return $next($request);
    }

    public static function from(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }
}
