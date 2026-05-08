<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getActiveCompetitions()
    {
        return Competition::whereIn('status', ['open', 'running'])
            ->withCount('enrolments')
            ->orderBy('competition_date')
            ->get();
    }
}
