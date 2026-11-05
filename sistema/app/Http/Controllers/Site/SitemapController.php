<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect(['/', '/tienda', '/arma-tu-pc', '/servicio-tecnico', '/asesoria', '/contacto'])->map(fn ($path) => ['loc' => url($path), 'lastmod' => null]);
        $urls = $urls->merge(Category::where('is_visible', true)->get()->map(fn ($c) => ['loc' => route('shop.category', $c), 'lastmod' => $c->updated_at]));
        $urls = $urls->merge(Product::where('is_published', true)->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => route('shop.product', $p), 'lastmod' => $p->updated_at]));
        $urls = $urls->merge(Build::where('is_published', true)->get(['slug', 'updated_at'])->map(fn ($b) => ['loc' => route('shop.build', $b), 'lastmod' => $b->updated_at]));

        // La declaración XML va aquí y no en la vista: dentro de Blade cerraría el bloque PHP.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('site.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /cotizacion/\nDisallow: /carrito\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
