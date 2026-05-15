<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
            ->brandLogo(asset('images/logo-light.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Orange,
            ])
            ->navigationGroups([
                'Competitions',
                'Competitors',
                'System',
                'Competitor Portal',
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
            ->navigationItems([
                NavigationItem::make('My Dashboard')
                    ->url('/portal')
                    ->icon('heroicon-o-home')
                    ->group('Competitor Portal')
                    ->sort(1),
                NavigationItem::make('My Profile')
                    ->url('/portal/profile')
                    ->icon('heroicon-o-user-circle')
                    ->group('Competitor Portal')
                    ->sort(2),
            ])
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('<style>
                    .fi-page-header > div { flex-direction: column !important; align-items: flex-start !important; gap: 0.75rem !important; }
                    .fi-page-header > div > div:last-child { margin-left: 0 !important; flex-wrap: wrap; }
                    @media (min-width: 640px) { .sm\:table-cell { display: table-cell; } }
                    @media (min-width: 768px) { .md\:table-cell { display: table-cell; } }
                    @media (min-width: 1024px) { .lg\:table-cell { display: table-cell; } }
                </style>')
            )
            ->renderHook(
                'panels::body.end',
                function () {
                    $timeoutMinutes = config('compete.inactivity_timeout', 30);
                    $logoutUrl      = route('filament.admin.auth.logout');
                    $loginUrl       = '/admin/login?reason=session_expired';

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
                            var WARN_MS    = TIMEOUT_MS - 120000; // warn 2 min before
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
            )
            ->renderHook(
                'panels::body.end',
                fn () => new \Illuminate\Support\HtmlString('
                    <div id="nav-confirm-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;" onclick="if(event.target===this)navConfirmStay()">
                        <div style="background:#fff;border-radius:0.75rem;padding:1.5rem;max-width:28rem;width:calc(100% - 2rem);box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                                <div style="width:2.5rem;height:2.5rem;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <svg style="width:1.25rem;height:1.25rem;color:#dc2626;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/></svg>
                                </div>
                                <h2 style="font-size:1rem;font-weight:600;color:#111827;margin:0;">Unsaved changes</h2>
                            </div>
                            <p style="font-size:0.875rem;color:#6b7280;margin:0 0 1.5rem;">You have unsaved changes that will be lost if you leave this page.</p>
                            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                                <button onclick="navConfirmStay()" style="padding:0.5rem 1rem;border:1px solid #d1d5db;border-radius:0.5rem;font-size:0.875rem;font-weight:500;color:#374151;background:#fff;cursor:pointer;">Stay on page</button>
                                <button onclick="navConfirmLeave()" style="padding:0.5rem 1rem;border:none;border-radius:0.5rem;font-size:0.875rem;font-weight:500;color:#fff;background:#dc2626;cursor:pointer;">Leave page</button>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var formDirty      = false;
                        var onEditPage     = false;
                        var pendingNavHref = null;

                        function checkEditPage() {
                            onEditPage = /\/(edit|create)(\?|\/|$)/.test(window.location.href);
                            if (!onEditPage) formDirty = false;
                        }

                        function showNavConfirm(href) {
                            pendingNavHref = href;
                            var m = document.getElementById("nav-confirm-modal");
                            if (m) m.style.display = "flex";
                        }

                        window.navConfirmStay = function () {
                            pendingNavHref = null;
                            var m = document.getElementById("nav-confirm-modal");
                            if (m) m.style.display = "none";
                        };

                        window.navConfirmLeave = function () {
                            var href = pendingNavHref;
                            pendingNavHref = null;
                            formDirty = false;
                            var m = document.getElementById("nav-confirm-modal");
                            if (m) m.style.display = "none";
                            if (typeof Livewire !== "undefined" && typeof Livewire.navigate === "function") {
                                Livewire.navigate(href);
                            } else {
                                window.location.href = href;
                            }
                        };

                        document.addEventListener("input",  function () { if (onEditPage) formDirty = true; });
                        document.addEventListener("change", function () { if (onEditPage) formDirty = true; });

                        // Capture phase — fires before Livewire\'s wire:navigate bubble listener.
                        document.addEventListener("click", function (e) {
                            var btn = e.target.closest("button, a");
                            if (!btn) return;
                            var text = btn.textContent.trim();
                            // Save / Cancel actions: clear dirty and allow normally.
                            if (/^(cancel|save|create|update)/i.test(text)) {
                                formDirty = false;
                                return;
                            }
                            // Any other link while dirty: intercept and show custom dialog.
                            if (formDirty && btn.tagName === "A") {
                                var href = btn.getAttribute("href");
                                if (href && href !== "#" && !href.startsWith("javascript:") && !href.startsWith("mailto:")) {
                                    e.preventDefault();
                                    e.stopImmediatePropagation();
                                    showNavConfirm(href);
                                }
                            }
                        }, true);

                        // Full-page unloads (browser close, address bar, refresh).
                        window.addEventListener("beforeunload", function (e) {
                            if (formDirty) { e.preventDefault(); e.returnValue = ""; }
                        });

                        document.addEventListener("livewire:navigated", function () { checkEditPage(); });

                        checkEditPage();
                    })();
                    </script>')
            );
    }
}
