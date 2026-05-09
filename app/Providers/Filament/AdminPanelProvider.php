<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->brandName('Compete')
            ->brandLogo('https://www.lfp.com.au/img/logo.jpg')
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Red,
            ])
            ->navigationGroups([
                'Competitions',
                'Competitors',
                'System',
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('My Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url('/portal/profile'),

                MenuItem::make()
                    ->label('Competitor Portal')
                    ->icon('heroicon-o-globe-alt')
                    ->url('/portal'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
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
                    $logoutUrl      = route('filament.admin.auth.logout');

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

                        // Inactivity session timeout
                        (function () {
                            var TIMEOUT_MS = ' . ($timeoutMinutes * 60 * 1000) . ';
                            var WARN_MS    = TIMEOUT_MS - 120000; // warn 2 min before
                            var LOGOUT_URL = ' . json_encode($logoutUrl) . ';
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
                                    window.location.href = LOGOUT_URL;
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
