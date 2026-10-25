<?php

use App\Http\Controllers\Frontend\StorefrontCatalogController;
use Illuminate\Support\Facades\Route;

// All P07 paths have at least TWO segments: root-level custom campaign slugs
// are never shadowed. Every action also checks the independent local/test DB gate.
Route::prefix('storefront')->name('storefront.')->group(function (): void {
    Route::get('/home', [StorefrontCatalogController::class, 'home'])->name('home');
    Route::get('/shop', [StorefrontCatalogController::class, 'shop'])->name('shop');
    Route::get('/category/{slug}', [StorefrontCatalogController::class, 'category'])
        ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('category');
    Route::get('/product/{slug}', [StorefrontCatalogController::class, 'product'])
        ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('product');
});
