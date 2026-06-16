<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationActive
{
    /**
     * @param string $panel 'portal' enforces the competitor-login lock, 'manage' does not.
     */
    public function handle(Request $request, Closure $next, string $panel = 'portal'): Response
    {
        $tenant = app('tenant');

        if (! $tenant) {
            return $next($request);
        }

        if (! $tenant->isActive()) {
            return redirect()->route('organisation.disabled');
        }

        if ($panel === 'portal' && $tenant->competitor_logins_locked) {
            return redirect()->route('competitor.access.disabled');
        }

        return $next($request);
    }
}
