<?php

declare(strict_types=1);

use App\Http\Controllers\PosSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KasirKu POS API Routes (Laravel 11)
|--------------------------------------------------------------------------
|
| Offline-first PWA clients batch-sync queued IndexedDB transactions to
| /api/v1/pos/sync-batch and retrieve ESC/POS 58mm payloads + Shift Z-Reports.
|
*/

Route::prefix('v1/pos')->group(function (): void {
    Route::post('/sync-batch', [PosSyncController::class, 'syncOfflineBatch'])
        ->name('pos.sync-batch');
});
