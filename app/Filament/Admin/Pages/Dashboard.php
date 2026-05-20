<?php

namespace App\Filament\Admin\Pages;

use App\Models\Organisation;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getStats(): array
    {
        return [
            'total'    => Organisation::count(),
            'active'   => Organisation::where('status', 'active')->count(),
            'inactive' => Organisation::where('status', 'inactive')->count(),
        ];
    }

    public function getRecentOrgs()
    {
        return Organisation::withCount([
                'memberships' => fn ($q) => $q->where('role', 'administrator'),
                'users'       => fn ($q) => $q->where('users.status', 'active'),
                'competitions',
            ])
            ->latest()
            ->limit(10)
            ->get();
    }
}
