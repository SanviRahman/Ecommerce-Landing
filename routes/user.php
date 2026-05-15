<?php

use App\Http\Controllers\Admin\CampaignOrderController;
use App\Http\Controllers\Admin\CampaignPageController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\OrderSuccessController;
use Illuminate\Support\Facades\Route;

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