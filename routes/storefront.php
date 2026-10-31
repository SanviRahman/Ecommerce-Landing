<?php

use App\Http\Controllers\Frontend\StorefrontAccountController;
use App\Http\Controllers\Frontend\StorefrontCartController;
use App\Http\Controllers\Frontend\StorefrontWishlistController;
use App\Http\Controllers\Frontend\StorefrontCatalogController;
use App\Http\Controllers\Frontend\StorefrontContactController;
use App\Http\Middleware\P26IsolatedCommerceQaMiddleware;
use App\Http\Middleware\P32LocalStorefrontCommerceMiddleware;
use App\Http\Middleware\P58ProductionStorefrontCommerceMiddleware;
use Illuminate\Support\Facades\Route;

// Public browse routes work directly on the published catalog (including the main DB).
// The QA/local/production middlewares grant scoped cart/checkout capabilities
// independently; ordinary published catalog browsing stays public.
Route::prefix('storefront')->name('storefront.')->middleware([P26IsolatedCommerceQaMiddleware::class, P32LocalStorefrontCommerceMiddleware::class, P58ProductionStorefrontCommerceMiddleware::class])
    ->group(function (): void {
        Route::get('/home', [StorefrontCatalogController::class, 'home'])->name('home');
        Route::get('/shop', [StorefrontCatalogController::class, 'shop'])->name('shop');
        Route::get('/help', [StorefrontCatalogController::class, 'help'])->name('help');
        Route::get('/contact', [StorefrontCatalogController::class, 'contact'])->name('contact');
        Route::post('/contact', [StorefrontContactController::class, 'store'])
            ->middleware('throttle:5,1')->name('contact.submit');
        Route::get('/category/{slug}', [StorefrontCatalogController::class, 'category'])
            ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('category');
        Route::get('/product/{slug}', [StorefrontCatalogController::class, 'product'])
            ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9_-]*')->name('product');

        // A real session-backed wishlist is available without inventory writes.
        Route::get('/wishlist', [StorefrontWishlistController::class, 'index'])->name('wishlist');
        Route::post('/wishlist/toggle', [StorefrontWishlistController::class, 'toggle'])
            ->middleware('throttle:40,1')->name('wishlist.toggle');

        // P32 local main-DB commerce is guarded independently from P26 QA.
        // Checkout still depends on stock/shipping/homepage readiness.
        Route::prefix('account')->name('account.')->group(function (): void {
            Route::get('/login', [StorefrontAccountController::class, 'loginForm'])->name('login');
            Route::post('/login', [StorefrontAccountController::class, 'login'])
                ->middleware('throttle:10,1')->name('login.submit');
            Route::get('/register', [StorefrontAccountController::class, 'registerForm'])->name('register');
            Route::post('/register', [StorefrontAccountController::class, 'register'])
                ->middleware('throttle:5,1')->name('register.submit');
            Route::get('/', [StorefrontAccountController::class, 'index'])->name('index');
            Route::patch('/profile', [StorefrontAccountController::class, 'updateProfile'])
                ->middleware('throttle:20,1')->name('profile.update');
            Route::post('/logout', [StorefrontAccountController::class, 'logout'])->name('logout');
        });
        Route::get('/cart/summary', [StorefrontCartController::class, 'summary'])->name('cart.summary');
        Route::get('/cart', [StorefrontCartController::class, 'index'])->name('cart');
        Route::post('/cart/items', [StorefrontCartController::class, 'add'])
            ->middleware('throttle:30,1')->name('cart.add');
        Route::patch('/cart/items/{item}', [StorefrontCartController::class, 'change'])
            ->whereNumber('item')->middleware('throttle:30,1')->name('cart.change');
        Route::get('/checkout', [StorefrontCartController::class, 'checkout'])->name('checkout');
        Route::post('/checkout', [StorefrontCartController::class, 'place'])
            ->middleware('throttle:10,1')->name('checkout.place');
    });
