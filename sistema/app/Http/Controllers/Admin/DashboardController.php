<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = Quote::where('created_at', '>=', now()->startOfMonth());

        $stats = [
            'count' => (clone $month)->count(),
            'amount' => (float) (clone $month)->sum('total'),
            'accepted' => (clone $month)->where('status', 'accepted')->count(),
            'acceptedAmount' => (float) (clone $month)->where('status', 'accepted')->sum('total'),
            'new' => Quote::whereNull('user_id')->count(),
        ];

        // El vendedor ve totales generales y solo sus propios números; el dueño ve a todos y el margen.
        $sellers = User::where('is_active', true)
            ->when(! $user->isOwner(), fn ($q) => $q->whereKey($user->id))
            ->withCount(['quotes as month_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())])
            ->withSum(['quotes as month_accepted' => fn ($q) => $q->where('status', 'accepted')->where('created_at', '>=', now()->startOfMonth())], 'total')
            ->orderBy('name')->get(['id', 'name']);

        $margin = null;
        if ($user->can('view-margins')) {
            $accepted = (clone $month)->where('status', 'accepted')->with('items')->get();
            $net = $accepted->sum(fn (Quote $q) => (float) $q->total / 1.16);
            $cost = $accepted->sum(fn (Quote $q) => $q->items->sum(fn ($i) => $i->unit_price === null ? 0 : (float) $i->unit_cost * $i->quantity));
            $margin = $net > 0 ? ['amount' => round($net - $cost, 2), 'percent' => ($net - $cost) / $net] : null;
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'sellers' => $sellers,
            'margin' => $margin,
            'recent' => Quote::with('customer:id,name')->whereNull('user_id')->latest()->limit(6)->get(['id', 'folio', 'customer_id', 'source', 'total', 'build_name', 'created_at', 'status', 'valid_until']),
        ]);
    }
}
