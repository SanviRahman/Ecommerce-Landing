<?php

use App\Http\Controllers\Admin\CampaignOrderController;
use App\Http\Controllers\Admin\CampaignPageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User / Frontend Routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/user.php';

/*
|--------------------------------------------------------------------------
| Admin Auth routes under /admin prefix
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware('lte_context:admin')
    ->group(function () {
        Auth::routes(['register' => false]);
        require __DIR__ . '/admin.php';
    });

/*
|--------------------------------------------------------------------------
| Admin Artisan Command Routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/command.php';

/*
|--------------------------------------------------------------------------
| Custom Root-Level Campaign Routes
|--------------------------------------------------------------------------
|
| These routes must remain after all fixed frontend/admin routes. This keeps
| application paths such as /admin, /track-order and /command protected from
| the dynamic one-segment campaign route.
|
*/
$reservedCampaignRoutes = collect(config('campaign-routes.reserved', []))
    ->map(fn ($route) => preg_quote(strtolower((string) $route), '#'))
    ->filter()
    ->implode('|');

$campaignRoutePattern = config(
    'campaign-routes.pattern',
    '[a-z0-9][a-z0-9_-]*'
);

if ($reservedCampaignRoutes !== '') {
    $campaignRoutePattern = '(?!(?:' . $reservedCampaignRoutes . ')(?:/|$))'
        . $campaignRoutePattern;
}

Route::get('/{customRoute}', [CampaignPageController::class, 'showByCustomRoute'])
    ->where('customRoute', $campaignRoutePattern)
    ->name('campaign.custom.show');

Route::post('/{customRoute}/order', [CampaignOrderController::class, 'storeByCustomRoute'])
    ->where('customRoute', $campaignRoutePattern)
    ->name('campaign.custom.order.store');
