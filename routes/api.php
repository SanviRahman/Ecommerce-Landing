<?php

use App\Http\Controllers\Api\ExternalOrderController;
use App\Http\Middleware\AuthenticateExternalWebsiteToken;
use Illuminate\Support\Facades\Route;

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
