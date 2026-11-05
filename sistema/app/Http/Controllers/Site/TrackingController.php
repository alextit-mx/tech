<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consultas públicas por folio. Nunca muestran notas internas, costos ni el teléfono completo. */
class TrackingController extends Controller
{
    public function status(Request $request): View
    {
        $order = null;
        if ($request->isMethod('post')) {
            $data = $request->validate(['folio' => ['required', 'string', 'max:30'], 'digits' => ['required', 'digits:4']], [], ['digits' => 'últimos 4 dígitos']);
            $order = ServiceOrder::with(['customer:id,name,phone', 'events' => fn ($q) => $q->where('is_public', true)])
                ->where('folio', strtoupper(trim($data['folio'])))->first();
            if ($order && ! str_ends_with((string) $order->customer->phone, $data['digits'])) {
                $order = null;
            }
        }

        return view('site.tracking.status', ['order' => $order, 'searched' => $request->isMethod('post'), 'statuses' => ServiceOrder::STATUSES]);
    }

    public function warranty(Request $request): View
    {
        $warranty = null;
        if ($request->isMethod('post')) {
            $data = $request->validate(['folio' => ['required', 'string', 'max:30'], 'proof' => ['required', 'string', 'max:80']], [], ['proof' => 'número de serie o últimos 4 dígitos']);
            $warranty = Warranty::with('customer:id,name,phone', 'items')->where('folio', strtoupper(trim($data['folio'])))->first();
            $proof = trim($data['proof']);
            $matches = $warranty && (
                (preg_match('/^\d{4}$/', $proof) && str_ends_with((string) $warranty->customer->phone, $proof))
                || $warranty->items->contains(fn ($i) => $i->serial && strcasecmp($i->serial, $proof) === 0)
            );
            $warranty = $matches ? $warranty : null;
        }

        return view('site.tracking.warranty', ['warranty' => $warranty, 'searched' => $request->isMethod('post')]);
    }
}
