<?php

use App\Http\Controllers\Admin\CampaignOrderController;
use App\Http\Controllers\Admin\CampaignPageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

// Admin Auth routes under /admin prefix
Route::prefix('admin')
    ->name('admin.')
    ->middleware('lte_context:admin')
    ->group(function () {
        Auth::routes(['register' => false]);
        require __DIR__ . '/admin.php';
    });