<?php

namespace App\Http\Controllers\Api;

use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok(
            $this->workspace($request)->webhooks()->get()->map(fn (Webhook $w) => $this->present($w))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:https,http', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'max:60'],
        ]);

        $webhook = $this->workspace($request)->webhooks()->create([
            'url' => $data['url'],
            'events' => $data['events'] ?? null,
            'secret' => Str::random(48),
        ]);

        // Secret hanya dikembalikan penuh saat dibuat; setelah ini disamarkan.
        return $this->ok($this->present($webhook) + ['secret' => $webhook->secret], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->workspace($request)->webhooks()->findOrFail($id)->delete();

        return $this->ok(null, 204);
    }

    private function present(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
            'consecutive_failures' => $webhook->consecutive_failures,
            'last_success_at' => $webhook->last_success_at?->toIso8601String(),
            'last_failure_at' => $webhook->last_failure_at?->toIso8601String(),
        ];
    }
}
