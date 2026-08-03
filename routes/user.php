<?php

use App\Http\Controllers\Admin\CampaignOrderController;
use App\Http\Controllers\Admin\CampaignPageController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\OrderSuccessController;
use App\Http\Controllers\Frontend\OrderTrackingController;
use App\Http\Controllers\Webhook\PathaoWebhookController;
use App\Http\Controllers\Webhook\SteadfastWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| User / Frontend Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Public Campaign Landing Page
|--------------------------------------------------------------------------
*/
Route::get('/campaign/{campaign:slug}', [CampaignPageController::class, 'show'])
    ->name('campaign.show');

/*
|--------------------------------------------------------------------------
| Public Campaign Order Submit
|--------------------------------------------------------------------------
*/
Route::post('/campaign/{campaign:slug}/order', [CampaignOrderController::class, 'store'])
    ->name('campaign.order.store');

/*
|--------------------------------------------------------------------------
| Public Order Success Page
|--------------------------------------------------------------------------
*/
Route::get('/success/{token}', [OrderSuccessController::class, 'show'])
    ->name('order.success');

/*
|--------------------------------------------------------------------------
| Public Order Tracking
|--------------------------------------------------------------------------
*/
Route::get('/track-order', [OrderTrackingController::class, 'index'])
    ->name('order.track');

Route::post('/track-order', [OrderTrackingController::class, 'search'])
    ->middleware('throttle:20,1')
    ->name('order.track.search');

/*
|--------------------------------------------------------------------------
| SteadFast Courier Webhook
|--------------------------------------------------------------------------
| Each dynamic SteadFast courier account receives its own callback URL and
| bearer token from Courier API Accounts.
*/
Route::post('/webhooks/steadfast/{courierAccount}', SteadfastWebhookController::class)
    ->whereNumber('courierAccount')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('webhooks.steadfast');

/*
|--------------------------------------------------------------------------
| Pathao Courier Webhook
|--------------------------------------------------------------------------
| Each dynamic Pathao account receives its own callback URL and signature
| secret from Courier API Accounts. No .env credentials are required.
*/
Route::post('/webhooks/pathao/{courierAccount}', PathaoWebhookController::class)
    ->whereNumber('courierAccount')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('webhooks.pathao');
