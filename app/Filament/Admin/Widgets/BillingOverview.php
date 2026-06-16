<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Competition;
use App\Models\OrganisationAnnualFeeReminder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class BillingOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $unsettled = Competition::where('is_template', false)
            ->billable()
            ->whereNull('platform_fee_settled_at')
            ->whereHas('carts', fn (Builder $q) => $q->where('status', 'submitted')->where('payment_status', 'received'))
            ->get();

        $totalOutstanding = $unsettled->sum(fn (Competition $c) => $c->unpaidPlatformFeeTotal());

        $annualFeeDueCount = OrganisationAnnualFeeReminder::active()->count();

        return [
            Stat::make('Outstanding platform fees', 'AUD ' . number_format($totalOutstanding, 2))
                ->description($unsettled->count() . ' completed competition(s) unsettled')
                ->color($totalOutstanding > 0 ? 'warning' : 'success'),

            Stat::make('Orgs with annual fee due', (string) $annualFeeDueCount)
                ->color($annualFeeDueCount > 0 ? 'warning' : 'success'),
        ];
    }
}
