<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use App\Support\Sat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Customers', [
            'customers' => $this->search($request->search)->when(in_array($request->kind, ['mostrador', 'registrado'], true), fn ($q) => $request->kind === 'mostrador' ? $q->where(fn ($w) => $w->where('kind', 'mostrador')->orWhereNull('kind')) : $q->where('kind', 'registrado'))->withCount('quotes')->orderBy('name')->paginate(25)->withQueryString(),
            'filters' => $request->only('search', 'kind'),
            'sat' => Sat::catalogs(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($request->validated());

        // El editor de cotizaciones da de alta clientes sin salir de la pantalla.
        return $request->wantsJson() ? response()->json($customer) : back()->with('success', 'Cliente guardado.');
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', 'Cliente guardado.');
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request->query('q'))->orderBy('name')->limit(10)->get());
    }

    private function search(?string $term)
    {
        return Customer::query()->when($term, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', '%'.(Customer::normalizePhone($term) ?? $term).'%')
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('company', 'like', "%{$term}%")
            ->orWhere('legal_name', 'like', "%{$term}%")
            ->orWhere('rfc', 'like', "%{$term}%")));
    }
}
