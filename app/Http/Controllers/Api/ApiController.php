<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    protected function apiKey(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }

    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function fail(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $message] + $extra,
        ], $status);
    }
}
