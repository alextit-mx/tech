<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePlan;
use App\Support\Folio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServicePlanController extends Controller
{
    public function index(): Response
    {
        $plans = ServicePlan::with('customer:id,name,phone', 'visits.user:id,name')->withCount('visits')->orderByDesc('ends_at')->paginate(25);
        $plans->getCollection()->each(fn (ServicePlan $p) => $p->setAttribute('remaining', $p->remainingVisits())->setAttribute('active', $p->isActive()));

        return Inertia::render('admin/ServicePlans', ['plans' => $plans]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'visits_included' => ['required', 'integer', 'min:1', 'max:365'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['customer_id' => 'cliente', 'name' => 'nombre del plan', 'visits_included' => 'visitas', 'ends_at' => 'fin de vigencia']);

        $plan = DB::transaction(fn () => ServicePlan::create([...$data, 'folio' => Folio::next('POL')]));

        return back()->with('success', "Póliza {$plan->folio} creada.");
    }

    public function visit(Request $request, ServicePlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'visited_at' => ['required', 'date'],
            'device' => ['nullable', 'string', 'max:120'],
            'work' => ['required', 'string', 'max:2000'],
        ], [], ['work' => 'trabajo realizado']);

        if ($plan->remainingVisits() === 0) {
            return back()->withErrors(['work' => 'La póliza ya no tiene visitas disponibles.']);
        }
        $plan->visits()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Visita registrada.');
    }
}
