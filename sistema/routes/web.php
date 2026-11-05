<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BuildController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PendingSpecsController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ServiceOrderController;
use App\Http\Controllers\Admin\ServicePlanController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Controllers\PublicQuoteController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

require __DIR__.'/site.php';

Route::get('/cotizacion/{token}', [PublicQuoteController::class, 'show'])->name('quotes.public');
Route::get('/cotizacion/{token}/pdf', [PublicQuoteController::class, 'pdf'])->name('quotes.public.pdf');

Route::middleware(['auth', EnsureUserIsActive::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('can:edit-products')->group(function () {
        Route::get('/fichas', [PendingSpecsController::class, 'index'])->name('specs.pending');
        Route::patch('/fichas/{product}', [PendingSpecsController::class, 'update'])->name('specs.update');
        Route::post('/fichas/revisar-armados', [PendingSpecsController::class, 'review'])->name('specs.review');
    });

    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::get('/products/export', [AdminController::class, 'exportProducts'])->name('products.export');
    Route::get('/products/create', [AdminController::class, 'productForm'])->name('products.create');
    Route::get('/products/{product}/edit', [AdminController::class, 'productForm'])->name('products.edit');
    Route::post('/products', [AdminController::class, 'saveProduct'])->name('products.store');
    Route::post('/products/visibility', [AdminController::class, 'visibility'])->name('products.visibility');
    Route::post('/products/bulk-update', [AdminController::class, 'bulkUpdate'])->name('products.bulk-update');
    Route::post('/products/bulk-pricing', [AdminController::class, 'bulk'])->name('products.bulk');
    Route::patch('/products/{product}', [AdminController::class, 'saveProduct'])->name('products.update');
    Route::patch('/products/{product}/quick', [AdminController::class, 'quick'])->name('products.quick');
    Route::post('/products/{product}/images', [AdminController::class, 'upload'])->name('products.images');
    Route::patch('/images/{image}', [AdminController::class, 'reorderImage'])->name('images.reorder');
    Route::delete('/images/{image}', [AdminController::class, 'deleteImage'])->name('images.delete');
    Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
    Route::post('/categories', [AdminController::class, 'saveCategory'])->name('categories.store');
    Route::patch('/categories/{category}', [AdminController::class, 'saveCategory'])->name('categories.update');
    Route::get('/brands', [AdminController::class, 'brands'])->name('brands');
    Route::post('/brands', [AdminController::class, 'saveBrand'])->name('brands.store');
    Route::patch('/brands/{brand}', [AdminController::class, 'saveBrand'])->name('brands.update');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'saveUser'])->name('users.store');
    Route::patch('/users/{user}', [AdminController::class, 'saveUser'])->name('users.update');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'saveSettings'])->name('settings.update');
    Route::post('/settings/logos/{type}', [AdminController::class, 'uploadLogo'])->whereIn('type', ['site', 'pdf', 'icon'])->name('settings.logos.store');
    Route::delete('/settings/logos/{type}', [AdminController::class, 'deleteLogo'])->whereIn('type', ['site', 'pdf', 'icon'])->name('settings.logos.destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/products', [QuoteController::class, 'products'])->name('quotes.products');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::patch('/quotes/{quote}/status', [QuoteController::class, 'status'])->name('quotes.status');
    Route::post('/quotes/{quote}/take', [QuoteController::class, 'take'])->name('quotes.take');
    Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');

    Route::get('/service', [ServiceOrderController::class, 'index'])->name('service');
    Route::post('/service', [ServiceOrderController::class, 'store'])->name('service.store');
    Route::get('/service/{order}', [ServiceOrderController::class, 'show'])->name('service.show');
    Route::put('/service/{order}', [ServiceOrderController::class, 'update'])->name('service.update');
    Route::post('/service/{order}/events', [ServiceOrderController::class, 'event'])->name('service.event');
    Route::get('/service/{order}/pdf', [ServiceOrderController::class, 'pdf'])->name('service.pdf');

    Route::get('/warranties', [WarrantyController::class, 'index'])->name('warranties');
    Route::get('/warranties/create', [WarrantyController::class, 'create'])->name('warranties.create');
    Route::post('/warranties', [WarrantyController::class, 'store'])->name('warranties.store');
    Route::post('/warranties/{warranty}/void', [WarrantyController::class, 'void'])->name('warranties.void');
    Route::get('/warranties/{warranty}/pdf', [WarrantyController::class, 'pdf'])->name('warranties.pdf');

    Route::get('/builds', [BuildController::class, 'index'])->name('builds');
    Route::get('/builds/create', [BuildController::class, 'form'])->name('builds.create');
    Route::get('/builds/parts', [BuildController::class, 'parts'])->name('builds.parts');
    Route::get('/builds/check', [BuildController::class, 'check'])->name('builds.check');
    Route::post('/builds', [BuildController::class, 'save'])->name('builds.store');
    Route::get('/builds/{build:id}/edit', [BuildController::class, 'form'])->name('builds.edit');
    Route::post('/builds/{build:id}', [BuildController::class, 'save'])->name('builds.update');
    Route::post('/builds/{build:id}/publish', [BuildController::class, 'publish'])->name('builds.publish');
    Route::post('/builds/{build:id}/move', [BuildController::class, 'move'])->name('builds.move');
    Route::post('/builds/{build:id}/duplicate', [BuildController::class, 'duplicate'])->name('builds.duplicate');
    Route::delete('/builds/{build:id}', [BuildController::class, 'destroy'])->name('builds.destroy');

    Route::get('/banners', [BannerController::class, 'index'])->name('banners');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::post('/banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
    Route::post('/banners/{banner}/move', [BannerController::class, 'move'])->name('banners.move');
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');

    Route::get('/plans', [ServicePlanController::class, 'index'])->name('plans');
    Route::post('/plans', [ServicePlanController::class, 'store'])->name('plans.store');
    Route::post('/plans/{plan}/visits', [ServicePlanController::class, 'visit'])->name('plans.visit');
});

require __DIR__.'/settings.php';
