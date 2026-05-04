<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BulkOrderController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CreatePageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SocialMediaController;
use App\Http\Controllers\Admin\TrackingPixelController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BrandController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Common Routes: Admin + Employee
    |--------------------------------------------------------------------------
    */
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/profile', [ProfileController::class, 'profile'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');

    Route::get('/password', [ProfileController::class, 'password'])->name('change-password');
    Route::post('/password', [ProfileController::class, 'updatePassword'])->name('change-password.update');

    /*
|--------------------------------------------------------------------------
| Orders: Admin + Employee
|--------------------------------------------------------------------------
*/
    Route::middleware(['role:admin,employee'])
        ->prefix('orders')
        ->as('orders.')
        ->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');

            Route::get('/pending', [OrderController::class, 'pending'])->name('pending');
            Route::get('/confirmed', [OrderController::class, 'confirmed'])->name('confirmed');
            Route::get('/processing', [OrderController::class, 'processing'])->name('processing');
            Route::get('/shipped', [OrderController::class, 'shipped'])->name('shipped');
            Route::get('/delivered', [OrderController::class, 'delivered'])->name('delivered');
            Route::get('/cancelled', [OrderController::class, 'cancelled'])->name('cancelled');
            Route::get('/fake', [OrderController::class, 'fake'])->name('fake');

            /*
        |--------------------------------------------------------------------------
        | Admin Only Order Bulk / Trash Routes
        |--------------------------------------------------------------------------
        */
            Route::middleware(['role:admin'])->group(function () {
                Route::get('/trash', [OrderController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [OrderController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [OrderController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [OrderController::class, 'multipleAction'])->name('multiple_action');
                Route::post('/assign-unassigned', [OrderController::class, 'assignUnassignedOrders'])->name('assign_unassigned');
            });

            /*
        |--------------------------------------------------------------------------
        | Single Order Routes
        |--------------------------------------------------------------------------
        */
            Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            Route::get('/{order}/invoice', [OrderController::class, 'invoice'])->name('invoice');

            Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->name('update_status');
            Route::patch('/{order}/payment-status', [OrderController::class, 'updatePaymentStatus'])->name('update_payment_status');

            Route::patch('/{order}/mark-as-fake', [OrderController::class, 'markAsFake'])->name('mark_as_fake');
            Route::patch('/{order}/restore-fake', [OrderController::class, 'restoreFake'])->name('restore_fake');

            Route::delete('/{order}', [OrderController::class, 'destroy'])
                ->middleware('role:admin')
                ->name('destroy');
        });

    /*
|--------------------------------------------------------------------------
| Products: Admin full access, Employee view only
|--------------------------------------------------------------------------
*/
    Route::middleware(['role:admin,employee'])
        ->prefix('products')
        ->as('products.')
        ->group(function () {

            /*
        |--------------------------------------------------------------------------
        | Admin Only Static Routes
        |--------------------------------------------------------------------------
        */
            Route::middleware(['role:admin'])->group(function () {
                Route::get('/create', [ProductController::class, 'create'])->name('create');
                Route::post('/', [ProductController::class, 'store'])->name('store');

                Route::get('/trash/list', [ProductController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [ProductController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [ProductController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [ProductController::class, 'multipleAction'])->name('multiple_action');
                Route::delete('/media/{id}', [ProductController::class, 'deleteMedia'])->name('delete_media');
            });

            /*
        |--------------------------------------------------------------------------
        | Product List
        |--------------------------------------------------------------------------
        */
            Route::get('/', [ProductController::class, 'index'])->name('index');

            /*
        |--------------------------------------------------------------------------
        | Admin Only Dynamic Edit/Update/Delete Routes
        |--------------------------------------------------------------------------
        */
            Route::middleware(['role:admin'])->group(function () {
                Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
                Route::put('/{product}', [ProductController::class, 'update'])->name('update');
                Route::patch('/{product}', [ProductController::class, 'update']);
                Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
            });

            /*
        |--------------------------------------------------------------------------
        | Product Show Route Must Be Last
        |--------------------------------------------------------------------------
        */
            Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        });

/*
|--------------------------------------------------------------------------
| Admin Only Routes
|--------------------------------------------------------------------------
*/
    Route::middleware(['role:admin'])->group(function () {

        /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
        Route::prefix('users')
            ->as('users.')
            ->group(function () {
                Route::patch('/{user}/status', [UserController::class, 'updateStatus'])->name('update_status');

                Route::get('/', [UserController::class, 'index'])->name('index');
                Route::get('/create', [UserController::class, 'create'])->name('create');
                Route::post('/', [UserController::class, 'store'])->name('store');

                Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
                Route::put('/{user}', [UserController::class, 'update'])->name('update');
                Route::patch('/{user}', [UserController::class, 'update']);
                Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

                Route::get('/{user}', [UserController::class, 'show'])->name('show');
            });

        /*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/
        Route::prefix('categories')
            ->as('categories.')
            ->group(function () {
                Route::get('/create', [CategoryController::class, 'create'])->name('create');
                Route::post('/', [CategoryController::class, 'store'])->name('store');

                Route::get('/trash', [CategoryController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [CategoryController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [CategoryController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [CategoryController::class, 'multipleAction'])->name('multiple_action');
                Route::delete('/media/{id}', [CategoryController::class, 'deleteMedia'])->name('delete_media');

                Route::get('/', [CategoryController::class, 'index'])->name('index');

                Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
                Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
                Route::patch('/{category}', [CategoryController::class, 'update']);
                Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');

                Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
            });

/*
        |--------------------------------------------------------------------------
        | Brands
        |--------------------------------------------------------------------------
        */
        Route::prefix('brands')
            ->as('brands.')
            ->group(function () {
                Route::get('/create', [BrandController::class, 'create'])->name('create');
                Route::post('/', [BrandController::class, 'store'])->name('store');

                Route::get('/trash/list', [BrandController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [BrandController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [BrandController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [BrandController::class, 'multipleAction'])->name('multiple_action');

                Route::get('/', [BrandController::class, 'index'])->name('index');

                Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
                Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
                Route::patch('/{brand}', [BrandController::class, 'update']);
                Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy');

                Route::get('/{brand}', [BrandController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */
        Route::prefix('pages')
            ->as('pages.')
            ->group(function () {
                Route::get('/create', [CreatePageController::class, 'create'])->name('create');
                Route::post('/', [CreatePageController::class, 'store'])->name('store');

                Route::get('/trash/list', [CreatePageController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [CreatePageController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [CreatePageController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [CreatePageController::class, 'multipleAction'])->name('multiple_action');
                Route::delete('/media/{id}', [CreatePageController::class, 'deleteMedia'])->name('delete_media');

                Route::get('/', [CreatePageController::class, 'index'])->name('index');

                Route::get('/{page}/edit', [CreatePageController::class, 'edit'])->name('edit');
                Route::put('/{page}', [CreatePageController::class, 'update'])->name('update');
                Route::patch('/{page}', [CreatePageController::class, 'update']);
                Route::delete('/{page}', [CreatePageController::class, 'destroy'])->name('destroy');

                Route::get('/{page}', [CreatePageController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Campaigns
    |--------------------------------------------------------------------------
    */
        Route::prefix('campaigns')
            ->as('campaigns.')
            ->group(function () {
                Route::get('/create', [CampaignController::class, 'create'])->name('create');
                Route::post('/', [CampaignController::class, 'store'])->name('store');

                Route::get('/trash/list', [CampaignController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [CampaignController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [CampaignController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [CampaignController::class, 'multipleAction'])->name('multiple_action');

                Route::delete('/media/{id}', [CampaignController::class, 'deleteMedia'])->name('delete_media');

                Route::get('/', [CampaignController::class, 'index'])->name('index');

                Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])->name('edit');
                Route::put('/{campaign}', [CampaignController::class, 'update'])->name('update');
                Route::patch('/{campaign}', [CampaignController::class, 'update']);
                Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy');

                Route::post('/{campaign}/products', [CampaignController::class, 'attachProducts'])->name('attach_products');
                Route::delete('/{campaign}/products/{product}', [CampaignController::class, 'detachProduct'])->name('detach_product');

                Route::get('/{campaign}', [CampaignController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Banners
    |--------------------------------------------------------------------------
    */
        Route::prefix('banners')
            ->as('banners.')
            ->group(function () {
                Route::get('/create', [BannerController::class, 'create'])->name('create');
                Route::post('/', [BannerController::class, 'store'])->name('store');

                Route::get('/trash/list', [BannerController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [BannerController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [BannerController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [BannerController::class, 'multipleAction'])->name('multiple_action');
                Route::delete('/media/{id}', [BannerController::class, 'deleteMedia'])->name('delete_media');

                Route::get('/', [BannerController::class, 'index'])->name('index');

                Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
                Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
                Route::patch('/{banner}', [BannerController::class, 'update']);
                Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');

                Route::get('/{banner}', [BannerController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Tracking Pixels
    |--------------------------------------------------------------------------
    */
        Route::prefix('tracking-pixels')
            ->as('tracking-pixels.')
            ->group(function () {
                Route::post('/multiple-action', [TrackingPixelController::class, 'multipleAction'])->name('multiple_action');

                Route::get('/', [TrackingPixelController::class, 'index'])->name('index');
                Route::get('/create', [TrackingPixelController::class, 'create'])->name('create');
                Route::post('/', [TrackingPixelController::class, 'store'])->name('store');

                Route::patch('/{trackingPixel}/status', [TrackingPixelController::class, 'updateStatus'])->name('update_status');
                Route::get('/{trackingPixel}/edit', [TrackingPixelController::class, 'edit'])->name('edit');
                Route::put('/{trackingPixel}', [TrackingPixelController::class, 'update'])->name('update');
                Route::patch('/{trackingPixel}', [TrackingPixelController::class, 'update']);
                Route::delete('/{trackingPixel}', [TrackingPixelController::class, 'destroy'])->name('destroy');

                Route::get('/{trackingPixel}', [TrackingPixelController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Site Settings
    |--------------------------------------------------------------------------
    */
        Route::prefix('site-settings')
            ->as('site-settings.')
            ->group(function () {
                Route::get('/', [SiteSettingController::class, 'index'])->name('index');
                Route::post('/', [SiteSettingController::class, 'update'])->name('update');
                Route::delete('/media/{id}', [SiteSettingController::class, 'deleteMedia'])->name('delete_media');
            });

        /*
    |--------------------------------------------------------------------------
    | Social Media
    |--------------------------------------------------------------------------
    */
        Route::prefix('social-media')
            ->as('social-media.')
            ->group(function () {
                Route::post('/multiple-action', [SocialMediaController::class, 'multipleAction'])->name('multiple_action');

                Route::get('/', [SocialMediaController::class, 'index'])->name('index');
                Route::get('/create', [SocialMediaController::class, 'create'])->name('create');
                Route::post('/', [SocialMediaController::class, 'store'])->name('store');

                Route::patch('/{socialMedia}/status', [SocialMediaController::class, 'updateStatus'])->name('update_status');
                Route::get('/{socialMedia}/edit', [SocialMediaController::class, 'edit'])->name('edit');
                Route::put('/{socialMedia}', [SocialMediaController::class, 'update'])->name('update');
                Route::patch('/{socialMedia}', [SocialMediaController::class, 'update']);
                Route::delete('/{socialMedia}', [SocialMediaController::class, 'destroy'])->name('destroy');

                Route::get('/{socialMedia}', [SocialMediaController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */
        Route::prefix('reviews')
            ->as('reviews.')
            ->group(function () {
                Route::get('/create', [ReviewController::class, 'create'])->name('create');
                Route::post('/', [ReviewController::class, 'store'])->name('store');

                Route::get('/trash/list', [ReviewController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [ReviewController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [ReviewController::class, 'forceDelete'])->name('force_delete');
                Route::delete('/media/{id}', [ReviewController::class, 'deleteMedia'])->name('delete_media');

                Route::get('/', [ReviewController::class, 'index'])->name('index');

                Route::get('/{review}/edit', [ReviewController::class, 'edit'])->name('edit');
                Route::put('/{review}', [ReviewController::class, 'update'])->name('update');
                Route::patch('/{review}', [ReviewController::class, 'update']);
                Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');

                Route::get('/{review}', [ReviewController::class, 'show'])->name('show');
            });

        /*
    |--------------------------------------------------------------------------
    | FAQ
    |--------------------------------------------------------------------------
    */
        Route::prefix('faqs')
            ->as('faqs.')
            ->group(function () {
                Route::post('/multiple-action', [FaqController::class, 'multipleAction'])->name('multiple_action');

                Route::get('/', [FaqController::class, 'index'])->name('index');
                Route::get('/create', [FaqController::class, 'create'])->name('create');
                Route::post('/', [FaqController::class, 'store'])->name('store');

                Route::patch('/{faq}/status', [FaqController::class, 'updateStatus'])->name('update_status');
                Route::get('/{faq}/edit', [FaqController::class, 'edit'])->name('edit');
                Route::put('/{faq}', [FaqController::class, 'update'])->name('update');
                Route::patch('/{faq}', [FaqController::class, 'update']);
                Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy');

                Route::get('/{faq}', [FaqController::class, 'show'])->name('show');
            });

        /*
        |--------------------------------------------------------------------------
        | Bulk Orders
        |--------------------------------------------------------------------------
        */
        Route::prefix('bulk-orders')
            ->as('bulk-orders.')
            ->group(function () {
                Route::get('/', [BulkOrderController::class, 'index'])->name('index');
                Route::get('/trash', [BulkOrderController::class, 'trash'])->name('trashed');
                Route::post('/restore/{id}', [BulkOrderController::class, 'restore'])->name('restore');
                Route::delete('/force-delete/{id}', [BulkOrderController::class, 'forceDelete'])->name('force_delete');
                Route::post('/multiple-action', [BulkOrderController::class, 'multipleAction'])->name('multiple_action');

                Route::get('/new', [BulkOrderController::class, 'newRequests'])->name('new');
                Route::get('/contacted', [BulkOrderController::class, 'contacted'])->name('contacted');
                Route::get('/quoted', [BulkOrderController::class, 'quoted'])->name('quoted');
                Route::get('/confirmed', [BulkOrderController::class, 'confirmed'])->name('confirmed');
                Route::get('/cancelled', [BulkOrderController::class, 'cancelled'])->name('cancelled');

                Route::get('/{bulkOrder}', [BulkOrderController::class, 'show'])->name('show');
                Route::patch('/{bulkOrder}/status', [BulkOrderController::class, 'updateStatus'])->name('update_status');
                Route::delete('/{bulkOrder}', [BulkOrderController::class, 'destroy'])->name('destroy');
            });

        /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
        Route::prefix('reports')
            ->as('reports.')
            ->group(function () {
                Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
                Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
                Route::get('/campaigns', [ReportController::class, 'campaigns'])->name('campaigns');
                Route::get('/bulk-orders', [ReportController::class, 'bulkOrders'])->name('bulk_orders');
                Route::get('/fake-orders', [ReportController::class, 'fakeOrders'])->name('fake_orders');
            });
    });
});
