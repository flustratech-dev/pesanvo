<?php

namespace App\Http\Controllers\Api;

use App\Models\WaSession;
use App\Services\SessionService;
use App\Support\EngineError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SessionController extends ApiController
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->workspace($request)->sessions()->orderBy('name')->get();

        return $this->ok($sessions->map(fn (WaSession $s) => $this->present($s)));
    }

    public function store(Request $request): JsonResponse
    {
        // `driver` sengaja tidak lagi diterima. Hanya ada satu cara mengirim di
        // produk ini, jadi menerimanya berarti menjanjikan pilihan yang tidak
        // ada — dan integrasi yang terlanjur mengirimkannya akan menyangka
        // pilihannya dihormati.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $session = $this->sessions->create($workspace, $data['name']);
        } catch (\Throwable $e) {
            return $this->fail(EngineError::pesan($e), 422);
        }

        return $this->ok($this->present($session), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->ok($this->present($this->find($request, $id)));
    }

    public function connect(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        // Permintaan manusia mengembalikan jatah percobaan otomatis ke penuh.
        // Orang yang menekan tombol ini biasanya baru memperbaiki sesuatu —
        // memberinya sisa jatah dari rentetan kegagalan sebelumnya berarti
        // penjadwal menyerah lagi setelah satu percobaan, tanpa alasan yang
        // bisa dilihat siapa pun.
        $session->resetPercobaanSambung();

        try {
            $session = $this->sessions->connect($session);
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan sesi lewat API', [
                'session_id' => $session->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->fail(EngineError::pesan($e), 502);
        }

        return $this->ok($this->present($session));
    }

    public function disconnect(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        try {
            $session = $this->sessions->disconnect($session);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghentikan sesi lewat API', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            // Sama dengan dashboard: engine yang tidak menjawab berarti ia
            // tidak sedang menjalankan sesi ini juga.
            $session->update(['status' => 'disconnected', 'qr_payload' => null]);
        }

        return $this->ok($this->present($session));
    }

    public function logout(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        try {
            $session = $this->sessions->logout($session);
        } catch (\Throwable $e) {
            Log::warning('Gagal memutus tautan nomor lewat API', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            // Keadaan lokal tidak diubah — kegagalan tersering di sini adalah
            // habis waktu, bukan bukti bahwa perintahnya tidak sampai.
            // 502, bukan 500: yang gagal layanan di belakang kami, dan klien
            // yang membaca kode ini tahu permintaannya sendiri tidak salah.
            return $this->fail(EngineError::pesan($e), 502);
        }

        return $this->ok($this->present($session));
    }

    /**
     * QR sudah disimpan sebagai data URI PNG (dirender engine dengan paket
     * `qrcode`), jadi klien bisa langsung menaruhnya di <img src>.
     */
    public function qr(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        if ($session->isConnected()) {
            return $this->ok(['status' => 'connected', 'qr' => null]);
        }

        if (! $session->hasFreshQr()) {
            return $this->ok(['status' => $session->status, 'qr' => null]);
        }

        return $this->ok([
            'status' => 'qr',
            'qr' => $session->qr_payload,
            'expires_at' => $session->qr_expires_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        // Selalu putuskan tautan dulu: menghapus baris tanpa logout meninggalkan
        // perangkat tertaut di HP pemilik nomor tanpa cara mencabutnya dari sini.
        try {
            $this->sessions->logout($session);
        } catch (\Throwable) {
            // Engine mati bukan alasan untuk menolak penghapusan.
        }

        $session->delete();

        return $this->ok(null, 204);
    }

    private function find(Request $request, string $id): WaSession
    {
        return $this->workspace($request)->sessions()->findOrFail($id);
    }

    private function present(WaSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'driver' => $session->driver,
            'status' => $session->status,
            'phone_number' => $session->phone_number,
            'push_name' => $session->push_name,
            'auto_reconnect' => $session->auto_reconnect,
            'connected_at' => $session->connected_at?->toIso8601String(),
            'last_seen_at' => $session->last_seen_at?->toIso8601String(),
            'last_error' => $session->last_error,
        ];
    }
}
