<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getActiveCompetitions()
    {
        return Competition::whereNotIn('competitions.status', ['complete'])
            ->withCount('enrolments')
            ->withCount(['enrolments as checkins_count' => fn ($q) => $q->where('enrolments.status', 'checked_in')])
            ->withCount('competitionEvents as events_count')
            ->withCount(['allDivisions as completed_divisions_count' => fn ($q) => $q->where('divisions.status', 'complete')])
            ->orderBy('competition_date')
            ->get();
    }

    public function advanceStatusAction(): Action
    {
        return Action::make('advanceStatus')
            ->requiresConfirmation(function (array $arguments) {
                $competition = Competition::find($arguments['competitionId'] ?? null);
                if (! $competition || $competition->status !== 'draft') {
                    return true;
                }
                return $competition->allDivisions()
                    ->whereNull('divisions.location_label')
                    ->whereNotIn('divisions.status', ['combined'])
                    ->count() > 0;
            })
            ->modalHeading(fn (array $arguments) => match (Competition::find($arguments['competitionId'] ?? null)?->status) {
                'draft'    => 'Open Enrolments',
                'open'     => 'Close Enrolments',
                'closed'   => 'Begin Check-ins',
                'check_in' => 'Start Competition',
                'running'  => 'Conclude Competition',
                default    => 'Advance Status',
            })
            ->modalDescription(function (array $arguments) {
                $competition = Competition::find($arguments['competitionId'] ?? null);
                if (! $competition) {
                    return '';
                }
                return match ($competition->status) {
                    'draft' => (function () use ($competition) {
                        $unscheduled = $competition->allDivisions()
                            ->whereNull('divisions.location_label')
                            ->whereNotIn('divisions.status', ['combined'])
                            ->count();
                        return "{$unscheduled} division(s) have not been assigned to a location. Open for enrolment anyway?";
                    })(),
                    'open'     => 'Close enrolments for this competition?',
                    'closed'   => 'This will begin the check-in phase. Scoring will not be active until the competition starts.',
                    'check_in' => (function () use ($competition) {
                        $completedDivisions = $competition->allDivisions()
                            ->where('divisions.status', 'complete')
                            ->count();
                        $msg = 'This will start the competition. Undo check-in will be disabled and scoring will become active.';
                        if ($completedDivisions > 0) {
                            $msg .= " Warning: {$completedDivisions} division(s) are already marked as complete.";
                        }
                        return $msg;
                    })(),
                    'running' => 'Conclude this competition? Results will become visible to competitors.',
                    default   => 'Are you sure?',
                };
            })
            ->modalSubmitActionLabel(fn (array $arguments) => match (Competition::find($arguments['competitionId'] ?? null)?->status) {
                'draft'    => 'Open Enrolments',
                'open'     => 'Close Enrolments',
                'closed'   => 'Begin Check-ins',
                'check_in' => 'Start Competition',
                'running'  => 'Conclude Competition',
                default    => 'Confirm',
            })
            ->action(function (array $arguments) {
                $competition = Competition::find($arguments['competitionId'] ?? null);
                if (! $competition) {
                    return;
                }

                $next = match ($competition->status) {
                    'draft'    => 'open',
                    'open'     => 'closed',
                    'closed'   => 'check_in',
                    'check_in' => 'running',
                    'running'  => 'complete',
                    default    => null,
                };

                if ($next) {
                    $competition->update(['status' => $next]);
                    Notification::make()->title('Competition status updated.')->success()->send();
                }
            });
    }
}
