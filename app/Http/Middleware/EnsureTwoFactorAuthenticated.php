<?php

namespace App\Http\Middleware;

use App\Filament\Admin\Pages\TwoFactorChallenge;
use App\Filament\Admin\Pages\TwoFactorSetup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('system_admin')) {
            return $next($request);
        }

        // Exempt the 2FA pages themselves to avoid redirect loops
        if ($request->routeIs(
            TwoFactorChallenge::getRouteName('admin'),
            TwoFactorSetup::getRouteName('admin'),
        )) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->to(TwoFactorSetup::getUrl(panel: 'admin'));
        }

        if (! $request->session()->get('2fa_authenticated')) {
            return redirect()->to(TwoFactorChallenge::getUrl(panel: 'admin'));
        }

        return $next($request);
    }
}
