<?php

namespace App\Http\Controllers;

use App\Models\UserGuideProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mencatat bahwa seseorang sudah selesai — atau melewati — sebuah tur.
 *
 * Dicatat lewat permintaan tersendiri, bukan menumpang halaman berikutnya yang
 * dibuka: tur bisa ditutup tanpa berpindah halaman sama sekali, dan yang tidak
 * tercatat akan muncul lagi di kunjungan berikutnya — persis kesan "rusak" yang
 * membuat orang berhenti mempercayai antarmuka.
 */
class OnboardingGuideController extends Controller
{
    public function ack(Request $request, string $guideKey): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'skipped'])],
            'version' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        UserGuideProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'guide_key' => $guideKey],
            ['guide_version' => $data['version'] ?? 1, 'status' => $data['status']],
        );

        return response()->json(['ok' => true]);
    }
}
