<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $panelId = filament()->getCurrentPanel()->getId();

        if ($request->routeIs(
            "filament.{$panelId}.pages.two-factor-challenge",
            "filament.{$panelId}.pages.two-factor-setup",
        )) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('2fa_authenticated')) {
            return redirect()->route("filament.{$panelId}.pages.two-factor-challenge");
        }

        return $next($request);
    }
}
