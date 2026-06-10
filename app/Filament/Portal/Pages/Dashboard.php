<?php

namespace App\Filament\Portal\Pages;

use App\Jobs\GenerateEnrolmentSummariesJob;
use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Filament\Portal\Pages\CartPage;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Cache;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.portal.pages.dashboard';

    public function mount(): void
    {
        $tenant = app('tenant');
        if (! $tenant || ! config('services.google_ai.api_key')) return;
        if (! ($tenant->competitor_summaries_enabled ?? true)) return;

        $profileIds = $this->getProfiles()->pluck('id');
        if ($profileIds->isEmpty()) return;

        $days   = (int) ($tenant->dashboard_closed_days ?? 7);
        $cutoff = now()->subDays($days)->toDateString();

        // Find enrolments with no summary, all active events have results, competition is running or complete
        Enrolment::whereIn('competitor_profile_id', $profileIds)
            ->whereNull('ai_summary')
            ->whereNotIn('status', ['draft', 'withdrawn'])
            ->whereHas('competition', fn ($q) => $q
                ->where('organisation_id', $tenant->id)
                ->whereIn('status', ['running', 'complete'])
                ->where('competition_date', '>=', $cutoff)
            )
            ->whereHas('activeEvents', fn ($q) => $q->whereHas('division', fn ($d) => $d->whereNotNull('location_label')))
            ->whereDoesntHave('activeEvents', fn ($q) => $q
                ->whereHas('division', fn ($d) => $d->whereNotNull('location_label'))
                ->doesntHave('result')
            )
            ->with('competition')
            ->get()
            ->groupBy('competition_id')
            ->each(function ($group) {
                $competition = $group->first()->competition;
                $key = "summaries_generating_{$competition->id}";
                if (Cache::get($key)) return;
                try {
                    GenerateEnrolmentSummariesJob::dispatchFor($competition);
                    Cache::put($key, true, now()->addMinutes(10));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[EnrolmentSummary] dispatch failed', ['error' => $e->getMessage()]);
                }
            });
    }

    public function isSummaryGenerating(int $competitionId): bool
    {
        return (bool) Cache::get("summaries_generating_{$competitionId}");
    }

    protected function getHeaderActions(): array
    {
        $count = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->withCount(['enrolments as draft_count' => fn ($q) => $q->where('status', 'draft')])
            ->first()
            ?->draft_count ?? 0;

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
        $days   = (int) (app('tenant')?->dashboard_closed_days ?? 7);
        $cutoff = now()->subDays($days)->toDateString();

        return Competition::where('organisation_id', app('tenant')?->id)
            ->where('is_template', false)
            ->where(function ($q) use ($cutoff) {
                $q->whereIn('status', ['open', 'enrolments_closed', 'check_in', 'running'])
                  ->orWhere(fn ($q2) => $q2->where('status', 'complete')
                      ->where('competition_date', '>=', $cutoff));
            })
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

    public function getCartDraftKeys(): array
    {
        $cart = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first();

        if (! $cart) {
            return [];
        }

        return $cart->draftEnrolments()
            ->get(['competitor_profile_id', 'competition_id'])
            ->map(fn ($e) => "{$e->competitor_profile_id}:{$e->competition_id}")
            ->toArray();
    }

    public function getAllEnrolments(): \Illuminate\Support\Collection
    {
        $profileIds = $this->getProfiles()->pluck('id');
        return Enrolment::whereIn('competitor_profile_id', $profileIds)
            ->whereNotIn('status', ['draft'])
            ->with([
                'competition',
                'cart',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->get()
            ->groupBy('competitor_profile_id')
            ->map(fn ($group) => $group->keyBy('competition_id'));
    }

    public function getEnrolmentsForProfile(\App\Models\CompetitorProfile $profile)
    {
        return $profile->enrolments()
            ->whereNotIn('status', ['draft'])
            ->with([
                'competition',
                'cart',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->get()
            ->keyBy('competition_id');
    }

}
