<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
            ->brandLogo(asset('images/logo-light.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2.5rem')
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
            ->navigationGroups(['Admin'])
            ->navigationItems([
                NavigationItem::make('Admin Panel')
                    ->url('/admin')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->group('Admin')
                    ->sort(1)
                    ->visible(fn () => auth()->user()?->hasAnyRole(['competition_administrator', 'system_admin', 'competition_official'])),
            ])
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString(
                    '<link rel="stylesheet" href="' . \Illuminate\Support\Facades\Vite::asset('resources/css/filament-app.css') . '">' .
                    '<style>
                    /* =====================================================
                       COMPETE THEME — PORTAL PANEL
                       To retheme: edit ONLY the values in :root and .dark
                       ===================================================== */
                    :root {
                        --app-topbar:         #1e3a6e;
                        --app-accent:         #3b82f6;
                        --app-sidebar:        #f8fafc;
                        --app-sidebar-border: #e2e8f0;
                        --app-bg:             #f1f5f9;
                        --app-card:           #ffffff;
                        --app-card-header:    #f8fafc;
                        --app-card-border:    #e5e7eb;
                        --app-nav-active-bg:  #eff6ff;
                        --app-nav-active-fg:  #1e3a6e;
                        --app-nav-fg:         #64748b;
                    }
                    .dark {
                        --app-sidebar:        #0f172a;
                        --app-sidebar-border: rgba(255,255,255,0.06);
                        --app-bg:             #1e293b;
                        --app-card:           #0f172a;
                        --app-card-header:    #0a1020;
                        --app-card-border:    rgba(255,255,255,0.08);
                        --app-nav-active-bg:  rgba(59,130,246,0.12);
                        --app-nav-active-fg:  #3b82f6;
                        --app-nav-fg:         #94a3b8;
                    }

                    /* Page header layout */
                    .fi-page-header > div { flex-direction: column !important; align-items: flex-start !important; gap: 0.75rem !important; }
                    .fi-page-header > div > div:last-child { margin-left: 0 !important; flex-wrap: wrap; }

                    /* Topbar */
                    .fi-topbar { background-color: var(--app-topbar) !important; position: relative; box-shadow: 0 1px 4px rgba(0,0,0,0.25) !important; color: rgba(255,255,255,0.85) !important; }
                    .fi-topbar nav { background-color: var(--app-topbar) !important; }
                    .fi-topbar::after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: var(--app-accent); }
                    .fi-topbar * { color: rgba(255,255,255,0.85) !important; }
                    .fi-topbar *:hover { color: #ffffff !important; }
                    .fi-topbar svg, .fi-topbar svg * { color: rgba(255,255,255,0.85) !important; fill: currentColor; }
                    .fi-topbar .fi-breadcrumbs-item-separator { opacity: 0.4; }

                    /* All dropdown/filter panels (topbar + page filters) */
                    .fi-dropdown-panel { background-color: var(--app-card) !important; border-color: var(--app-card-border) !important; }
                    .fi-dropdown-panel * { color: #374151 !important; }
                    .fi-dropdown-panel svg, .fi-dropdown-panel svg * { color: #374151 !important; fill: currentColor !important; }
                    .dark .fi-dropdown-panel { background-color: var(--app-card) !important; border-color: var(--app-card-border) !important; }
                    .dark .fi-dropdown-panel * { color: #e2e8f0 !important; }
                    .dark .fi-dropdown-panel svg, .dark .fi-dropdown-panel svg * { color: #e2e8f0 !important; fill: currentColor !important; }
                    .fi-dropdown-panel *:hover { background-color: rgba(0,0,0,0.04) !important; color: #111827 !important; }
                    .dark .fi-dropdown-panel *:hover { background-color: rgba(255,255,255,0.06) !important; color: #ffffff !important; }

                    /* Sidebar */
                    .fi-sidebar { background-color: var(--app-sidebar) !important; border-right-color: var(--app-sidebar-border) !important; }
                    .fi-sidebar-header { background-color: var(--app-sidebar) !important; border-bottom-color: var(--app-sidebar-border) !important; }
                    .fi-sidebar-group-label { color: #94a3b8 !important; }
                    .dark .fi-sidebar-group-label { color: #475569 !important; }
                    .fi-sidebar-item-button { color: var(--app-nav-fg) !important; border-right: 3px solid transparent !important; border-radius: 0 !important; }
                    .fi-sidebar-item-button:hover { background-color: rgba(30,58,110,0.06) !important; }
                    .dark .fi-sidebar-item-button:hover { background-color: rgba(255,255,255,0.05) !important; }
                    .fi-sidebar-item-button.fi-active { background-color: var(--app-nav-active-bg) !important; color: var(--app-nav-active-fg) !important; font-weight: 600 !important; border-right-color: var(--app-accent) !important; }

                    /* Main content + cards */
                    .fi-main, body.fi-body { background-color: var(--app-bg) !important; }
                    .fi-section, .fi-wi-stats-overview-stat, .fi-ta-ctn { background-color: var(--app-card) !important; border-color: var(--app-card-border) !important; box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important; }
                    .dark .fi-section, .dark .fi-wi-stats-overview-stat, .dark .fi-ta-ctn { box-shadow: none !important; }
                    .fi-section-header, .fi-ta-header-cell { background-color: var(--app-card-header) !important; border-bottom-color: var(--app-card-border) !important; }
                    .fi-modal-window { background-color: var(--app-card) !important; }
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
