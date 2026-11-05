<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Warranty;
use App\Support\Folio;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'open');
        $orders = ServiceOrder::with('customer:id,name,phone', 'user:id,name')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ServiceOrder::OPEN))
            ->when($status && $status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$s}%")->orWhere('serial', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->latest('received_at')->paginate(25)->withQueryString();

        return Inertia::render('admin/ServiceOrders', [
            'orders' => $orders,
            'filters' => ['status' => $status, 'search' => $request->search],
            'statuses' => ServiceOrder::STATUSES,
            'devices' => ServiceOrder::DEVICES,
            'counts' => ServiceOrder::whereIn('status', ServiceOrder::OPEN)->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /** Alta rápida en mostrador: cliente existente o nombre y WhatsApp. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'required_without:customer_name', 'exists:customers,id'],
            'customer_name' => ['nullable', 'required_without:customer_id', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'required_with:customer_name', 'string', 'max:20'],
            ...$this->rules(),
        ], [], ['customer_name' => 'nombre del cliente', 'customer_phone' => 'WhatsApp', 'reported_issue' => 'falla reportada', 'device_type' => 'tipo de equipo']);

        $order = DB::transaction(function () use ($data, $request) {
            $customer = isset($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : Customer::fromContact($data['customer_name'], $data['customer_phone']);
            $order = ServiceOrder::create([
                ...collect($data)->except('customer_id', 'customer_name', 'customer_phone')->all(),
                'folio' => Folio::next('OS'),
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'received_at' => now(),
            ]);
            $order->log('received', 'Recibimos tu equipo.', true, $request->user());

            return $order;
        });

        return to_route('admin.service.show', $order)->with('success', "Orden {$order->folio} registrada.");
    }

    public function show(ServiceOrder $order): Response
    {
        $order->load('customer', 'user:id,name', 'events.user:id,name');

        return Inertia::render('admin/ServiceOrder', [
            'order' => $order,
            'statuses' => ServiceOrder::STATUSES,
            'devices' => ServiceOrder::DEVICES,
            'links' => ['tracking' => $order->trackingUrl(), 'whatsapp' => $order->whatsappUrl()],
            'warranty' => Warranty::where('service_order_id', $order->id)->value('id'),
        ]);
    }

    public function update(Request $request, ServiceOrder $order): RedirectResponse
    {
        $order->update($request->validate($this->rules()));

        return back()->with('success', 'Orden guardada.');
    }

    /** Cambio de estado y/o nota (pública o interna). */
    public function event(Request $request, ServiceOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ServiceOrder::STATUSES))],
            'note' => ['nullable', 'required_without:status', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);
        $order->log($data['status'] ?? null, $data['note'] ?? null, $request->boolean('is_public', true), $request->user());

        return back()->with('success', 'Historial actualizado.');
    }

    public function pdf(ServiceOrder $order): HttpResponse
    {
        return Pdf::stream('pdf.service-order', ['order' => $order->load('customer', 'user')], $order->folio.'.pdf');
    }

    private function rules(): array
    {
        return [
            'device_type' => ['required', Rule::in(array_keys(ServiceOrder::DEVICES))],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'serial' => ['nullable', 'string', 'max:80'],
            'accessories' => ['nullable', 'string', 'max:500'],
            'reported_issue' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:3000'],
            'work_done' => ['nullable', 'string', 'max:3000'],
            'estimate' => ['nullable', 'numeric', 'min:0'],
            'final_cost' => ['nullable', 'numeric', 'min:0'],
            'promised_at' => ['nullable', 'date'],
        ];
    }
}
