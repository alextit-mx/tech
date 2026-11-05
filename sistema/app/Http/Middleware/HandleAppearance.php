<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->user()?->appearance ?? $request->cookie('appearance') ?? 'system';
        $appearance = in_array($appearance, ['light', 'dark', 'system'], true) ? $appearance : 'system';
        View::share('appearance', $appearance);
        View::share('appearanceForcedDark', $request->routeIs('login', 'password.request', 'password.reset', 'two-factor.login', 'password.confirm'));

        return $next($request);
    }
}
