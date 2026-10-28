<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lalu lintas pesan seluruh platform.
 *
 * Satu-satunya tempat yang bisa menjawab "apakah masalahnya di satu pelanggan
 * atau di gateway kami" — pertanyaan pertama yang muncul setiap kali ada
 * keluhan, dan yang paling lama dijawab kalau harus dibuka satu per satu lewat
 * dashboard tiap workspace.
 *
 * Isi pesan sengaja TIDAK ditampilkan di daftar ini. Yang dibutuhkan untuk
 * menelusuri gangguan adalah status, tujuan, dan galatnya; isi percakapan
 * pelanggan bukan urusan panel operasional, dan menampilkannya membuat setiap
 * orang yang membuka halaman ini ikut membacanya tanpa alasan.
 */
class MessageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $arah = (string) $request->query('arah', '');
        $workspaceId = (string) $request->query('workspace', '');
        $cari = trim((string) $request->query('cari'));

        return view('admin.messages', [
            'messages' => Message::query()
                ->with(['workspace:id,name', 'session:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($arah !== '', fn ($q) => $q->where('direction', $arah))
                ->when($workspaceId !== '', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('to_number', 'like', "%{$cari}%")
                        ->orWhere('from_number', 'like', "%{$cari}%")
                        ->orWhere('error', 'like', "%{$cari}%");
                }))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),

            // Ringkasan 24 jam terakhir: yang menentukan ada gangguan atau tidak
            // bukan jumlah kumulatif, melainkan berapa yang gagal belakangan ini.
            'ringkasan' => Message::query()
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('status, count(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status')
                ->all(),

            'workspaces' => Workspace::orderBy('name')->pluck('name', 'id'),
            'status' => $status,
            'arah' => $arah,
            'workspaceId' => $workspaceId,
            'cari' => $cari,
        ]);
    }
}
