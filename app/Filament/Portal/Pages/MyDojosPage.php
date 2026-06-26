<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use Filament\Pages\Page;

class MyDojosPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-building-storefront';

    public function getTitle(): string
    {
        return 'My ' . tenant_group_name_plural();
    }

    public static function getNavigationLabel(): string
    {
        return 'My ' . tenant_group_name_plural();
    }
    protected string $view            = 'filament.portal.pages.my-dojos-page';
    protected static ?string $slug            = 'my-dojos';
    protected static ?int    $navigationSort  = 10;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->instructorOf()->exists();
    }

    public function getDojos()
    {
        return auth()->user()->instructorOf()->orderBy('name')->get();
    }

    public function getCompetitions()
    {
        $dojos = $this->getDojos();
        if ($dojos->isEmpty()) return collect();

        $dojoNames = $dojos->pluck('name');

        return Competition::whereIn('status', ['open', 'enrolments_closed', 'running', 'complete'])
            ->where('organisation_id', app('tenant')?->id)
            ->whereHas('enrolments', fn ($q) => $q->whereIn('dojo_name', $dojoNames))
            ->with([
                'enrolments' => fn ($q) => $q->whereIn('dojo_name', $dojoNames)
                    ->with(['competitor', 'cart', 'activeEvents.competitionEvent', 'activeEvents.division', 'activeEvents.result']),
            ])
            ->orderBy('competition_date', 'desc')
            ->get();
    }
}
