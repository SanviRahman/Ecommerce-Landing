<?php

use App\Http\Controllers\Frontend\StorefrontCatalogController;
use App\Http\Controllers\Frontend\StorefrontCartController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\StorefrontAccountController;

// All P07 paths have at least TWO segments: root-level custom campaign slugs
// are never shadowed. Every action also checks the independent local/test DB gate.
Route::prefix('storefront')->name('storefront.')->group(function (): void {
    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('/login', [StorefrontAccountController::class, 'loginForm'])->name('login');
        Route::post('/login', [StorefrontAccountController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
        Route::get('/register', [StorefrontAccountController::class, 'registerForm'])->name('register');
        Route::post('/register', [StorefrontAccountController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
        Route::get('/', [StorefrontAccountController::class, 'index'])->name('index');
        Route::patch('/profile', [StorefrontAccountController::class, 'updateProfile'])->middleware('throttle:20,1')->name('profile.update');
        Route::post('/logout', [StorefrontAccountController::class, 'logout'])->name('logout');
    });
    Route::get('/cart/summary', [StorefrontCartController::class, 'summary'])->name('cart.summary');
    Route::get('/cart', [StorefrontCartController::class, 'index'])->name('cart');
    Route::post('/cart/items', [StorefrontCartController::class, 'add'])->middleware('throttle:30,1')->name('cart.add');
    Route::patch('/cart/items/{item}', [StorefrontCartController::class, 'change'])->whereNumber('item')->middleware('throttle:30,1')->name('cart.change');
    Route::get('/checkout', [StorefrontCartController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [StorefrontCartController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
    Route::get('/home', [StorefrontCatalogController::class, 'home'])->name('home');
    Route::get('/shop', [StorefrontCatalogController::class, 'shop'])->name('shop');
    Route::get('/help', [StorefrontCatalogController::class, 'help'])->name('help');
    Route::get('/contact', [StorefrontCatalogController::class, 'contact'])->name('contact');
    Route::get('/category/{slug}', [StorefrontCatalogController::class, 'category'])
        ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('category');
    Route::get('/product/{slug}', [StorefrontCatalogController::class, 'product'])
        ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('product');
});
