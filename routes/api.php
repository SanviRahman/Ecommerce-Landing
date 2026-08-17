<?php

use App\Http\Controllers\Api\ExternalOrderController;
use App\Http\Middleware\AuthenticateExternalWebsiteToken;
use Illuminate\Support\Facades\Route;

// Receiver-initiated recovery endpoint uses a separate URI prefix so it can
// never be captured by the dynamic external-orders/{externalWebsite:slug} route.
Route::post('/external-order-sync/manual', [ExternalOrderController::class, 'manualSync'])
    ->middleware('throttle:60,1')
    ->name('api.external-orders.manual-sync');

Route::prefix('external-orders/{externalWebsite:slug}')
    ->middleware([
        'throttle:120,1',
        AuthenticateExternalWebsiteToken::class,
    ])
    ->group(function (): void {
        Route::post('/connection-request', [ExternalOrderController::class, 'connectionRequest'])
            ->name('api.external-orders.connection-request');

        Route::get('/status', [ExternalOrderController::class, 'status'])
            ->name('api.external-orders.status');

        Route::post('/', [ExternalOrderController::class, 'store'])
            ->name('api.external-orders.store');
    });
