<?php

use App\Http\Controllers\Site\BuilderController;
use App\Http\Controllers\Site\CartController;
use App\Http\Controllers\Site\ShopController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'home'])->name('home');
Route::view('/servicio-tecnico', 'site.pages.servicio-tecnico')->name('site.service');
Route::view('/asesoria', 'site.pages.asesoria')->name('site.advice');
Route::view('/contacto', 'site.pages.contacto')->name('site.contact');

Route::get('/tienda', [ShopController::class, 'index'])->name('shop');
Route::get('/tienda/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/producto/{product:slug}', [ShopController::class, 'product'])->name('shop.product');
Route::get('/buscar', [ShopController::class, 'search'])->name('shop.search');

Route::get('/carrito', [CartController::class, 'show'])->name('cart');
Route::get('/carrito/productos', [CartController::class, 'products'])->name('cart.products');
Route::post('/cotizar', [CartController::class, 'submit'])->middleware('throttle:6,1')->name('cart.submit');

Route::get('/arma-tu-pc', [BuilderController::class, 'show'])->name('builder');
Route::post('/arma-tu-pc/cotizar', [BuilderController::class, 'submit'])->middleware('throttle:6,1')->name('builder.submit');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots']);
