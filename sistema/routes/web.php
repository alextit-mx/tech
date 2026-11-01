<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'construction')->name('home');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::get('/products/create', [AdminController::class, 'productForm'])->name('products.create');
    Route::get('/products/{product}/edit', [AdminController::class, 'productForm'])->name('products.edit');
    Route::post('/products', [AdminController::class, 'saveProduct'])->name('products.store');
    Route::patch('/products/{product}', [AdminController::class, 'saveProduct'])->name('products.update');
    Route::patch('/products/{product}/quick', [AdminController::class, 'quick'])->name('products.quick');
    Route::post('/products/bulk-pricing', [AdminController::class, 'bulk'])->name('products.bulk');
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
});
require __DIR__.'/settings.php';
