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
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'system_admin', 'contributor'])),

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
                'panels::body.end',
                fn () => new \Illuminate\Support\HtmlString('<script>
                    document.addEventListener("alpine:initialized", function () {
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
                </script>')
            );
    }
}
