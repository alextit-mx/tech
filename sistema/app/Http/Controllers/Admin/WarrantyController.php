<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\ServiceOrder;
use App\Models\Warranty;
use App\Support\Folio;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WarrantyController extends Controller
{
    public function index(Request $request): Response
    {
        $warranties = Warranty::with('customer:id,name,phone', 'items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$s}%")
                ->orWhereHas('items', fn ($i) => $i->where('serial', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('admin/Warranties', ['warranties' => $warranties, 'filters' => $request->only('search')]);
    }

    /** Formulario prellenado desde una cotización aceptada (?quote=) o una orden entregada (?order=). */
    public function create(Request $request): Response
    {
        $customer = null;
        $items = [];
        $source = [];
        if ($quote = Quote::with('items.product', 'customer')->find($request->integer('quote'))) {
            $customer = $quote->customer;
            $items = $quote->items->map(fn ($i) => ['description' => $i->description, 'serial' => '', 'months' => $i->product?->warranty_months ?: 12])->all();
            $source = ['quote_id' => $quote->id, 'label' => "Cotización {$quote->folio}"];
        } elseif ($order = ServiceOrder::with('customer')->find($request->integer('order'))) {
            $customer = $order->customer;
            $items = [['description' => 'Servicio: '.($order->work_done ?: $order->device()), 'serial' => $order->serial ?? '', 'months' => 3]];
            $source = ['service_order_id' => $order->id, 'label' => "Orden {$order->folio}"];
        }

        return Inertia::render('admin/WarrantyForm', [
            'customer' => $customer,
            'items' => $items ?: [['description' => '', 'serial' => '', 'months' => 12]],
            'source' => $source,
            'terms' => setting('warranty_terms', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'quote_id' => ['nullable', 'exists:quotes,id'],
            'service_order_id' => ['nullable', 'exists:service_orders,id'],
            'starts_at' => ['required', 'date'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.serial' => ['nullable', 'string', 'max:80'],
            'items.*.months' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], ['customer_id' => 'cliente', 'items.*.description' => 'descripción', 'items.*.months' => 'meses']);

        $warranty = DB::transaction(function () use ($data, $request) {
            $warranty = Warranty::create([...collect($data)->except('items')->all(), 'folio' => Folio::next('GAR'), 'user_id' => $request->user()->id]);
            $warranty->items()->createMany($data['items']);

            return $warranty;
        });

        return to_route('admin.warranties')->with('success', "Garantía {$warranty->folio} emitida.");
    }

    public function void(Request $request, Warranty $warranty): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']], [], ['void_reason' => 'motivo']);
        $warranty->update(['voided_at' => now(), 'void_reason' => $data['void_reason']]);

        return back()->with('success', 'Garantía anulada.');
    }

    public function pdf(Warranty $warranty): HttpResponse
    {
        return Pdf::stream('pdf.warranty', ['warranty' => $warranty->load('customer', 'items')], $warranty->folio.'.pdf');
    }
}
