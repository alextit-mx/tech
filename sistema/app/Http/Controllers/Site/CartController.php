<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(): View
    {
        return view('site.shop.cart');
    }

    /** Datos actuales de los productos del carrito (el navegador solo guarda ids y cantidades). */
    public function products(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->take(60);

        $products = Product::with('images', 'brand:id,name')->whereIn('id', $ids)->where('is_published', true)->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => trim(($p->brand?->name ? $p->brand->name.' ' : '').$p->name),
                'sku' => $p->sku,
                'url' => route('shop.product', $p),
                'image' => $p->imageUrl(),
                'price' => $p->publicPrice(),
                'stock' => $p->stockLabel(),
            ]);

        return response()->json($products);
    }

    public function submit(Request $request, QuoteService $quotes): RedirectResponse
    {
        // Honeypot: los bots llenan "website"; se responde como si nada para no darles pistas.
        if ($request->filled('website')) {
            return redirect('/carrito');
        }

        $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(52)?\d{10}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ], ['phone.regex' => 'Escribe tu WhatsApp a 10 dígitos.', 'items.required' => 'Tu cotización está vacía.'], ['name' => 'nombre', 'phone' => 'WhatsApp', 'email' => 'correo']);

        $quote = $quotes->createFromCart($data, $data['items'], 'web', null, $data['notes'] ?? null);
        if ($quote->items->isEmpty()) {
            $quote->delete();

            return back()->withErrors(['items' => 'Los productos de tu lista ya no están disponibles.'])->withInput();
        }

        return redirect()->route('quotes.public', $quote->public_token)->with('created', true);
    }
}
