<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Catatan audit.
 *
 * Sudah lama ditulis oleh hampir setiap tindakan penting di sistem ini, tapi
 * sampai sekarang tidak pernah bisa dibaca tanpa membuka database. Padahal
 * justru catatan inilah yang menjawab pertanyaan yang paling sulit dijawab
 * belakangan: siapa membatalkan tagihan itu, kapan bukti pembayarannya masuk,
 * siapa mengubah batas workspace ini.
 *
 * Satu kejadian nyata yang membuat halaman ini ada: sebuah tagihan berbukti
 * dibatalkan 24 detik setelah buktinya diunggah, dan satu-satunya cara
 * mengetahui urutannya adalah membaca tabel ini lewat tinker.
 */
class AuditController extends Controller
{
    /**
     * Tindakan yang dikelompokkan supaya saringannya bisa dibaca manusia,
     * bukan sebagai daftar panjang nama teknis.
     */
    private const KELOMPOK = [
        'Penagihan' => ['invoice.issued', 'invoice.paid', 'invoice.canceled', 'invoice.proof_uploaded'],
        'Langganan' => ['subscription.past_due', 'subscription.suspended', 'subscription.extended'],
        'Tindakan admin' => [
            'admin.plan_changed', 'admin.workspace_suspended', 'admin.workspace_restored',
            'admin.limits_overridden', 'admin.invoice_status_changed', 'admin.proof_rejected',
            'admin.session_disconnected', 'admin.super_admin_toggled', 'admin.password_reset',
        ],
        'Workspace & kunci' => ['workspace.renamed', 'workspace.deleted', 'api_key.created', 'api_key.revoked'],
    ];

    public function __invoke(Request $request): View
    {
        $kelompok = (string) $request->query('kelompok', '');
        $cari = trim((string) $request->query('cari'));

        return view('admin.audit', [
            'entri' => AuditLog::query()
                ->with(['user:id,name,email', 'workspace:id,name'])
                ->when(isset(self::KELOMPOK[$kelompok]), fn ($q) => $q->whereIn('action', self::KELOMPOK[$kelompok]))
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('action', 'like', "%{$cari}%")
                        ->orWhere('ip_address', 'like', "%{$cari}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$cari}%"))
                        ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$cari}%"));
                }))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),

            'kelompokTersedia' => array_keys(self::KELOMPOK),
            'kelompok' => $kelompok,
            'cari' => $cari,
        ]);
    }
}
