<?php

namespace App\Filament\Portal\Pages;

use App\Jobs\GenerateEnrolmentSummariesJob;
use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Filament\Portal\Pages\CartPage;
use App\Filament\Portal\Pages\MyEnrolmentsPage as AccountPage;
use App\Notifications\Notification;
use App\Services\EnrolmentService;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Cache;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.portal.pages.dashboard';

    public ?int    $withdrawingId    = null;
    public ?string $withdrawalReason = '';

    public function canWithdraw(Enrolment $enrolment): bool
    {
        return $enrolment->canWithdraw();
    }

    public function startWithdraw(int $enrolmentId): void
    {
        $enrolment = Enrolment::findOrFail($enrolmentId);
        if (! auth()->user()->ownedProfiles()->pluck('id')->contains($enrolment->competitor_profile_id)) {
            abort(403);
        }
        $this->withdrawingId    = $enrolmentId;
        $this->withdrawalReason = '';
    }

    public function cancelWithdraw(): void
    {
        $this->withdrawingId    = null;
        $this->withdrawalReason = '';
    }

    public function confirmWithdraw(): void
    {
        $enrolment = Enrolment::with(['competition.organisation'])->findOrFail($this->withdrawingId);
        if (! auth()->user()->ownedProfiles()->pluck('id')->contains($enrolment->competitor_profile_id)) {
            abort(403);
        }

        try {
            app(EnrolmentService::class)->withdraw($enrolment, $this->withdrawalReason ?? '');
            Notification::make()->title('Registration withdrawn.')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->withdrawingId    = null;
        $this->withdrawalReason = '';
    }

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
        $tenantId = app('tenant')?->id;

        $count = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->withCount(['enrolments as draft_count' => fn ($q) => $q->where('status', 'draft')])
            ->first()
            ?->draft_count ?? 0;

        $platformFee = (float) (app('tenant')?->platform_fee ?? 0);
        $outstanding = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'submitted')
            ->where('payment_status', '!=', 'received')
            ->whereHas('enrolments', fn ($q) => $q->withTrashed()
                ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId)))
            ->with(['enrolments' => fn ($q) => $q->withoutTrashed()->where('status', '!=', 'withdrawn')])
            ->get()
            ->sum(fn ($cart) => $cart->outstandingAmount($platformFee));

        $actions = [
            Action::make('cart')
                ->label('Cart')
                ->icon('heroicon-o-shopping-cart')
                ->url(CartPage::getUrl())
                ->badge($count > 0 ? (string) $count : null)
                ->color('gray'),
        ];

        if ($outstanding > 0) {
            $actions[] = Action::make('account_balance')
                ->label(tenant_money($outstanding) . ' outstanding')
                ->icon('heroicon-o-banknotes')
                ->url(AccountPage::getUrl())
                ->color('warning');
        } else {
            $actions[] = Action::make('account_balance')
                ->label('Account')
                ->icon('heroicon-o-banknotes')
                ->url(AccountPage::getUrl())
                ->color('gray');
        }

        return $actions;
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
                $q->whereIn('status', ['advertise', 'open', 'enrolments_closed', 'running'])
                  ->orWhere(fn ($q2) => $q2->where('status', 'complete')
                      ->where('competition_date', '>=', $cutoff));
            })
            ->with(['portalMessages', 'competitionDays'])
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

    public function showCartConflict(int $cartCompetitionId): void
    {
        $compName = Competition::find($cartCompetitionId)?->name ?? 'another competition';
        Notification::make()
            ->title('Complete your current cart first')
            ->body("Your cart has entries for {$compName}. Checkout or clear your cart before registering for a different competition.")
            ->warning()
            ->send();
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
                'checkIns',
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
