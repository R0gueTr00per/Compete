<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Filament\Portal\Pages\CartPage;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.portal.pages.dashboard';

    protected function getHeaderActions(): array
    {
        $count = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first()
            ?->draftEnrolments()
            ->count() ?? 0;

        return [
            Action::make('cart')
                ->label('Cart')
                ->icon('heroicon-o-shopping-cart')
                ->url(CartPage::getUrl())
                ->badge($count > 0 ? (string) $count : null)
                ->color('gray'),
        ];
    }

    public function getProfiles()
    {
        return auth()->user()->ownedProfiles()
            ->orderByRaw("CASE WHEN profile_type = 'self' THEN 0 ELSE 1 END")
            ->orderBy('date_of_birth', 'desc')
            ->get();
    }

    public function getActiveCompetitions()
    {
        return Competition::whereIn('status', ['open', 'enrolments_closed', 'check_in', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->where('is_template', false)
            ->with('portalMessages')
            ->orderBy('competition_date')
            ->get();
    }

    public function getDraftCartSummary(): array
    {
        $cart = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->with([
                'enrolments' => fn ($q) => $q
                    ->where('status', 'draft')
                    ->with(['competitor', 'competition']),
            ])
            ->first();

        if (! $cart || $cart->enrolments->isEmpty()) {
            return ['count' => 0, 'items' => [], 'cartUrl' => CartPage::getUrl()];
        }

        return [
            'count'   => $cart->enrolments->count(),
            'items'   => $cart->enrolments->map(fn ($e) => [
                'profile_name'    => $e->competitor->full_name,
                'competition_name' => $e->competition->name,
            ])->toArray(),
            'cartUrl' => CartPage::getUrl(),
        ];
    }

    public function getEnrolmentsForProfile(\App\Models\CompetitorProfile $profile)
    {
        return $profile->enrolments()
            ->whereNotIn('status', ['draft'])
            ->with([
                'competition',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->get()
            ->keyBy('competition_id');
    }

}
