<?php

use App\Http\Controllers\Internal\BootstrapController;
use App\Http\Controllers\Internal\EngineEventController;
use App\Http\Controllers\Internal\SessionBackupController;
use Illuminate\Support\Facades\Route;

/*
| Endpoint yang hanya dipanggil engine Node. Bukan bagian dari API publik dan
| tidak boleh dibuka ke internet — di Coolify engine berjalan tanpa domain dan
| menghubungi Laravel lewat jaringan internal.
|
| Seluruh grup ini dijaga VerifyEngineSignature (HMAC + timestamp).
*/

Route::prefix('engine')->group(function (): void {
    Route::get('bootstrap', BootstrapController::class);
    Route::post('events', EngineEventController::class);

    Route::prefix('session-backup/{sessionId}')->group(function (): void {
        Route::get('/', [SessionBackupController::class, 'show']);
        Route::post('/', [SessionBackupController::class, 'store']);
        Route::get('exists', [SessionBackupController::class, 'exists']);
        Route::delete('/', [SessionBackupController::class, 'destroy']);
    });
});
