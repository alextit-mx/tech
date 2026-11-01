<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\PublicQuoteController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

require __DIR__.'/site.php';

Route::get('/cotizacion/{token}', [PublicQuoteController::class, 'show'])->name('quotes.public');
Route::get('/cotizacion/{token}/pdf', [PublicQuoteController::class, 'pdf'])->name('quotes.public.pdf');

Route::middleware(['auth', EnsureUserIsActive::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::get('/products/create', [AdminController::class, 'productForm'])->name('products.create');
    Route::get('/products/{product}/edit', [AdminController::class, 'productForm'])->name('products.edit');
    Route::post('/products', [AdminController::class, 'saveProduct'])->name('products.store');
    Route::post('/products/visibility', [AdminController::class, 'visibility'])->name('products.visibility');
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
});

require __DIR__.'/settings.php';
