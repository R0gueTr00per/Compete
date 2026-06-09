<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ActiveUsersChart;
use App\Models\Organisation;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            ActiveUsersChart::class,
        ];
    }

    public function getRecentOrgs()
    {
        return Organisation::withCount([
                'memberships'        => fn ($q) => $q->where('role', 'administrator'),
                'users'              => fn ($q) => $q->where('users.status', 'active'),
                'competitions',
                'competitorProfiles',
            ])
            ->with(['nextCompetition' => fn ($q) => $q->withCount('enrolments')])
            ->latest()
            ->limit(10)
            ->get();
    }
}
