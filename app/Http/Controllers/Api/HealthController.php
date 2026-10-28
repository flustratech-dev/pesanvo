<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $usage = $workspace->currentUsage();

        return $this->ok([
            'workspace' => $workspace->name,
            'status' => $workspace->status,
            'sessions' => [
                'total' => $workspace->sessions()->count(),
                'connected' => $workspace->sessions()->where('status', 'connected')->count(),
                'limit' => $workspace->max_sessions,
            ],
            'usage' => [
                'period' => $usage->period,
                'messages_sent' => $usage->messages_sent,
                'messages_received' => $usage->messages_received,
                'messages_failed' => $usage->messages_failed,
                'quota' => $workspace->isExempt() ? null : $workspace->monthly_message_quota,
            ],
        ]);
    }
}
