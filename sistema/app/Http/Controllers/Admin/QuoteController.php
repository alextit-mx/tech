<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuoteRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Support\ProductSearch;
use App\Support\QuotePdf;
use App\Support\Sat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $quotes) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        // El vendedor ve por defecto solo las suyas; el dueño, todas.
        $mine = $request->has('mine') ? $request->boolean('mine') : ! $user->isOwner();

        $quotes = Quote::with('customer:id,name,phone', 'user:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$s}%")
                ->orWhere('build_name', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->when($request->status === 'expired', fn ($q) => $q->where('status', 'sent')->whereDate('valid_until', '<', today()))
            ->when($request->status && $request->status !== 'expired', fn ($q) => $q->where('status', $request->status))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->boolean('new'), fn ($q) => $q->whereNull('user_id'))
            ->when($mine && ! $request->boolean('new'), fn ($q) => $q->where('user_id', $user->id))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('admin/Quotes', [
            'quotes' => $quotes,
            'filters' => [...$request->only('search', 'status', 'source', 'new'), 'mine' => $mine],
            'statuses' => Quote::STATUSES + ['expired' => 'Vencida'],
            'sources' => Quote::SOURCES,
            'newCount' => Quote::whereNull('user_id')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->editor(null, $request);
    }

    public function store(QuoteRequest $request): RedirectResponse
    {
        $quote = $this->quotes->saveFromAdmin(null, $request->validated(), $request->user());

        return to_route('admin.quotes.edit', $quote)->with('success', "Cotización {$quote->folio} guardada.");
    }

    public function edit(Request $request, Quote $quote): Response
    {
        return $this->editor($quote, $request);
    }

    public function update(QuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->quotes->saveFromAdmin($quote, $request->validated(), $request->user());

        return back()->with('success', 'Cotización guardada.');
    }

    public function status(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Quote::STATUSES))]]);
        $quote->update($data);

        return back()->with('success', 'Estado actualizado.');
    }

    /** Un vendedor toma una cotización que llegó de la web o del configurador. */
    public function take(Request $request, Quote $quote): RedirectResponse
    {
        if ($quote->user_id === null) {
            $quote->update(['user_id' => $request->user()->id]);
        }

        return to_route('admin.quotes.edit', $quote);
    }

    public function duplicate(Request $request, Quote $quote): RedirectResponse
    {
        $copy = $this->quotes->duplicate($quote->load('items', 'customer'), $request->user());

        return to_route('admin.quotes.edit', $copy)->with('success', "Copia creada: {$copy->folio}.");
    }

    public function pdf(Quote $quote): HttpResponse
    {
        return QuotePdf::download($quote);
    }

    public function products(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        $products = ProductSearch::apply(Product::query(), $term)
            ->with(['brand:id,name', 'images'])->orderBy('name')->limit(20)->get()
            ->map(fn (Product $p) => [...($request->user()->can('view-margins') ? $this->quotes->lineFromProduct($p) : Arr::except($this->quotes->lineFromProduct($p), ['unit_cost'])), 'id' => $p->id, 'name' => $p->name, 'image' => $p->imageOrPlaceholder(), 'price' => $p->publicPrice(), 'stock' => $p->stock, 'stock_24h' => $p->stock_24h, 'price_mode' => $p->price_mode]);

        return response()->json($products);
    }

    private function editor(?Quote $quote, Request $request): Response
    {
        $quote?->load('items', 'customer', 'user:id,name');

        return Inertia::render('admin/QuoteEditor', [
            'quote' => $quote,
            'sat' => Sat::catalogs(),
            'customer' => $quote?->customer ?? ($request->integer('customer_id') ? Customer::find($request->integer('customer_id')) : null),
            'defaults' => ['valid_until' => now()->addDays((int) setting('quote_validity_days', 3))->toDateString()],
            'statuses' => Quote::STATUSES,
            'sources' => Quote::SOURCES,
            'links' => $quote ? ['public' => $quote->publicUrl(), 'whatsapp' => $quote->whatsappUrl()] : null,
            'margin' => $quote && $request->user()->can('view-margins') ? $quote->margin() : null,
        ]);
    }
}
