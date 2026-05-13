<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('portal')
            ->path('portal')
            ->login(\App\Filament\Portal\Pages\Auth\Login::class)
            ->registration(\App\Filament\Portal\Pages\Auth\Register::class)
            ->passwordReset()
            ->emailVerification()
            ->brandName('Compete')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Admin Panel')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url('/admin')
                    ->visible(fn () => auth()->user()?->hasRole(['competition_administrator', 'system_admin', 'competition_official'])),

                MenuItem::make()
                    ->label('My Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => \App\Filament\Portal\Pages\ProfilePage::getUrl()),
            ])
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\\Filament\\Portal\\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\\Filament\\Portal\\Pages')
            ->pages([
                \App\Filament\Portal\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\\Filament\\Portal\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->authGuard('web')
            ->renderHook(
                'panels::sidebar.footer',
                function () {
                    if (! auth()->check() || ! auth()->user()->hasAnyRole(['competition_administrator', 'system_admin', 'competition_official'])) {
                        return '';
                    }
                    return new \Illuminate\Support\HtmlString('
                        <div class="px-3 pb-4 pt-2 border-t border-gray-200 dark:border-white/10">
                            <a href="/admin" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                                <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                Admin Panel
                            </a>
                        </div>
                    ');
                }
            )
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('<style>
                    .fi-page-header > div { flex-direction: column !important; align-items: flex-start !important; gap: 0.75rem !important; }
                    .fi-page-header > div > div:last-child { margin-left: 0 !important; flex-wrap: wrap; }
                </style>')
            )
            ->renderHook(
                'panels::body.end',
                function () {
                    $timeoutMinutes = config('compete.inactivity_timeout', 30);
                    $logoutUrl      = route('filament.portal.auth.logout');
                    $loginUrl       = '/portal/login?reason=session_expired';

                    return new \Illuminate\Support\HtmlString('<script>
                        document.addEventListener("alpine:initialized", function () {
                            // Sidebar auto-collapse on resize
                            var BREAKPOINT = 1280;
                            function syncSidebar() {
                                if (window.innerWidth < BREAKPOINT) {
                                    Alpine.store("sidebar").close();
                                } else {
                                    Alpine.store("sidebar").open();
                                }
                            }
                            window.addEventListener("resize", syncSidebar);
                            syncSidebar();
                        });

                        // Redirect to login with session-expired notice when Livewire detects page expiry.
                        window.addEventListener("livewire:page-expired", function () {
                            window.location.href = ' . json_encode($loginUrl) . ';
                        });

                        // Inactivity session timeout
                        (function () {
                            var TIMEOUT_MS = ' . ($timeoutMinutes * 60 * 1000) . ';
                            var WARN_MS    = TIMEOUT_MS - 120000;
                            var LOGOUT_URL = ' . json_encode($logoutUrl) . ';
                            var LOGIN_URL  = ' . json_encode($loginUrl) . ';
                            if (TIMEOUT_MS <= 0) return;

                            var lastActivity = Date.now();
                            var warned       = false;

                            var events = ["mousemove","mousedown","keydown","touchstart","scroll","click"];
                            events.forEach(function (e) {
                                document.addEventListener(e, function () {
                                    lastActivity = Date.now();
                                    warned       = false;
                                }, { passive: true });
                            });

                            setInterval(function () {
                                var idle = Date.now() - lastActivity;

                                if (idle >= TIMEOUT_MS) {
                                    // POST to the logout endpoint, then redirect to login with a session-expired notice.
                                    var csrf = document.querySelector("meta[name=\'csrf-token\']");
                                    fetch(LOGOUT_URL, {
                                        method: "POST",
                                        headers: { "X-CSRF-TOKEN": csrf ? csrf.getAttribute("content") : "" },
                                        credentials: "same-origin"
                                    }).catch(function () {}).finally(function () {
                                        window.location.href = LOGIN_URL;
                                    });
                                    return;
                                }

                                if (idle >= WARN_MS && ! warned) {
                                    warned = true;
                                    var remaining = Math.ceil((TIMEOUT_MS - idle) / 60000);
                                    if (window.Filament && Filament.notifications) {
                                        window.dispatchEvent(new CustomEvent("filament-notifications.notify", {
                                            detail: { type: "warning", title: "Session expiring soon", body: "You will be logged out in " + remaining + " minute(s) due to inactivity." }
                                        }));
                                    }
                                }
                            }, 15000);
                        })();
                    </script>');
                }
            );
    }
}
