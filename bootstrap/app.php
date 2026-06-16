<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: [__DIR__ . '/../app/Listeners'])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ResolveTenant::class,
        ]);
        $middleware->alias([
            'profile.complete' => \App\Http\Middleware\RequireCompleteProfile::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('compete:send-reminders')->dailyAt('07:00');
        $schedule->command('compete:generate-annual-fee-reminders')->dailyAt('06:00');
        $schedule->command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // When the CSRF token has expired (session timeout), redirect to the appropriate
        // login page instead of showing a 419 error page. Livewire AJAX requests are
        // excluded — those get the 419 so the livewire:page-expired JS event can fire.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                return null;
            }
            return redirect(route('filament.portal.auth.login', ['reason' => 'session_expired']));
        });
    })->create();
