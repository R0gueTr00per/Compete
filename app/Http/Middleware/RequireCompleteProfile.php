<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompleteProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->hasRole(['admin', 'system_admin', 'contributor'])
            && ! $user->competitorProfile?->profile_complete
        ) {
            if (! $request->routeIs('profile.complete', 'profile.complete.store', 'logout')) {
                return redirect()->route('profile.complete');
            }
        }

        return $next($request);
    }
}
