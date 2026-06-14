<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $rootDomain = config('app.domain');

        // Not an org subdomain — root domain or local dev
        if (! $rootDomain || $host === $rootDomain || $host === 'www.' . $rootDomain) {
            view()->share('currentOrg', null);
            return $next($request);
        }

        // Extract subdomain: "orgslug.kompetic.com" → "orgslug"
        if (! str_ends_with($host, '.' . $rootDomain)) {
            view()->share('currentOrg', null);
            return $next($request);
        }

        $slug = substr($host, 0, strlen($host) - strlen('.' . $rootDomain));

        // Reject nested subdomains (e.g. "foo.bar.kompetic.com")
        if (str_contains($slug, '.')) {
            abort(404);
        }

        $org = Cache::remember("org:slug:{$slug}", 3600, fn () =>
            Organisation::where('slug', $slug)->where('status', 'active')->first()
        );

        if (! $org) {
            abort(404);
        }

        app()->instance('tenant', $org);
        view()->share('currentOrg', $org);

        return $next($request);
    }
}
