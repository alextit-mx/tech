<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\SuggestBuildRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\QuoteService;
use App\Support\BuildSuggester;
use App\Support\BuildValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuilderController extends Controller
{
    /** Periféricos del paso "Completa tu setup", por slug de categoría. */
    private const EXTRAS = ['monitores' => 'Monitor', 'teclados' => 'Teclado', 'mouse' => 'Mouse', 'audifonos' => 'Audífonos'];

    public function show(): View
    {
        $slots = Category::whereNotNull('builder_slot')->where('is_visible', true)->pluck('builder_slot', 'id');

        $parts = Product::with('images', 'brand:id,name', 'category:id,builder_slot')->where('is_published', true)->whereIn('category_id', $slots->keys())->get()
            ->map(fn (Product $p) => [...$this->card($p), 'slot' => $slots[$p->category_id], 'specs' => $p->specs ?? [], 'estimated' => $p->compatibilityPart()['estimated']]);

        $groups = Category::whereIn('slug', array_keys(self::EXTRAS))->pluck('slug', 'id');
        $extras = Product::with('images', 'brand:id,name', 'category:id,builder_slot')->where('is_published', true)->whereIn('category_id', $groups->keys())
            ->orderByDesc('is_featured')->orderByDesc('stock')->limit(40)->get()
            ->map(fn (Product $p) => [...$this->card($p), 'group' => self::EXTRAS[$groups[$p->category_id]]]);

        return view('site.builder', ['catalog' => ['parts' => $parts->values(), 'extras' => $extras->values()]]);
    }

    public function suggested(SuggestBuildRequest $request): JsonResponse
    {
        $data = $request->validated();
        $suggestion = BuildSuggester::suggest($data['uso'], $data['presupuesto']);
        $suggestion['parts'] = (object) array_map(fn (Product $p) => $p->id, $suggestion['parts']);

        return response()->json($suggestion);
    }

    public function submit(Request $request, QuoteService $quotes): JsonResponse
    {
        if ($request->filled('website')) {
            return response()->json(['message' => 'Listo.'], 202);
        }

        $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(52)?\d{10}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'build_name' => ['nullable', 'string', 'max:60'],
            'parts' => ['required', 'array', 'max:8'],
            'parts.*' => ['integer'],
            'extras' => ['nullable', 'array', 'max:8'],
            'extras.*' => ['integer'],
        ], ['phone.regex' => 'Escribe tu WhatsApp a 10 dígitos.'], ['name' => 'nombre', 'phone' => 'WhatsApp', 'email' => 'correo']);

        // Una pieza por slot, solo productos publicados de categorías del configurador.
        $parts = Product::with('category:id,builder_slot')->whereIn('id', $data['parts'])->where('is_published', true)->get()
            ->filter(fn (Product $p) => $p->category?->builder_slot)
            ->keyBy(fn (Product $p) => $p->category->builder_slot);

        $specs = $parts->map(fn (Product $p) => $p->compatibilityPart())->all();
        if ($parts->isEmpty()) {
            return response()->json(['message' => 'Elige al menos una pieza.', 'errors' => ['parts' => ['Elige al menos una pieza.']]], 422);
        }
        $errors = BuildValidator::errors($specs);
        if ($errors) {
            return response()->json(['message' => $errors[0], 'errors' => ['parts' => $errors]], 422);
        }

        $items = $parts->values()->map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => 1])
            ->merge(collect($data['extras'] ?? [])->map(fn ($id) => ['product_id' => $id, 'quantity' => 1]))
            ->all();
        // Lo que falte para una PC completa queda anotado para que el vendedor ofrezca opciones.
        $missing = BuildValidator::missing($specs);
        $notes = trim(($data['notes'] ?? '').($missing ? "\nSin ".implode(', ', $missing).': te recomendamos opciones.' : ''));
        $quote = $quotes->createFromCart($data, $items, 'builder', ($data['build_name'] ?? null) ?: 'Mi PC', $notes ?: null);

        return response()->json([
            'folio' => $quote->folio,
            'url' => $quote->publicUrl(),
            'pdf' => route('quotes.public.pdf', $quote->public_token),
            'whatsapp' => $quote->businessWhatsappUrl(),
            'total' => (float) $quote->total,
            'pending' => $quote->has_pending_prices,
        ]);
    }

    private function card(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'brand' => $p->brand?->name,
            'image' => $p->imageUrl(),
            'price' => $p->publicPrice(),
            'stock' => $p->stockLevel(),
            'stockLabel' => $p->stockLabel(),
            'url' => route('shop.product', $p),
        ];
    }
}
