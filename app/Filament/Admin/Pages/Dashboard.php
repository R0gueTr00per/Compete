<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ActiveUsersChart;
use App\Filament\Admin\Widgets\BillingOverview;
use App\Models\Competition;
use App\Models\Organisation;
use App\Models\OrganisationAnnualFeeReminder;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.admin.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            BillingOverview::class,
            ActiveUsersChart::class,
        ];
    }

    public function getRecentOrgs()
    {
        return Organisation::withCount([
                'memberships'        => fn ($q) => $q->where('role', 'administrator'),
                'users'              => fn ($q) => $q->where('users.status', 'active'),
                'competitions' => fn ($q) => $q->where('is_template', false),
                'competitorProfiles',
            ])
            ->with(['nextCompetition' => fn ($q) => $q->withCount('enrolments')])
            ->latest()
            ->limit(10)
            ->get()
            ->each(function (Organisation $org) {
                $org->unpaid_platform_fee_total = Competition::where('organisation_id', $org->id)
                    ->where('is_template', false)
                    ->billable()
                    ->whereNull('platform_fee_settled_at')
                    ->whereHas('carts', fn (Builder $q) => $q->where('status', 'submitted'))
                    ->get()
                    ->sum(fn (Competition $c) => $c->unpaidPlatformFeeTotal());
                $reminder = OrganisationAnnualFeeReminder::active()
                    ->where('organisation_id', $org->id)
                    ->orderBy('due_date')
                    ->first();
                $org->annual_fee_due      = (bool) $reminder;
                $org->annual_fee_due_date = $reminder?->due_date;
            });
    }
}
