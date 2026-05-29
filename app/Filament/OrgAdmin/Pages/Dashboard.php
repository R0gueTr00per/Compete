<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Jobs\GenerateCompetitionInsightsJob;
use App\Models\Competition;
use App\Models\CompetitionInsight;
use App\Models\CompetitionTask;
use Filament\Actions\Action;
use App\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.org-admin.pages.dashboard';

    public static function canAccess(): bool
    {
        return true;
    }

    public function isOrgAdmin(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getOfficialRole(): ?object
    {
        $tenant = app('tenant');
        if (! $tenant) return null;
        return auth()->user()?->getActiveOfficialRoleFor($tenant);
    }

    public function getActiveCompetitions()
    {
        $days = (int) (app('tenant')?->dashboard_closed_days ?? 7);
        $cutoff = now()->subDays($days)->toDateString();

        return Competition::where('organisation_id', app('tenant')?->id)
            ->where(function ($q) use ($cutoff) {
                $q->where('competitions.status', '!=', 'complete')
                  ->orWhere('competitions.competition_date', '>=', $cutoff);
            })
            ->withCount('enrolments')
            ->withCount(['enrolments as checkins_count' => fn ($q) => $q->where('enrolments.status', 'checked_in')])
            ->withCount('competitionEvents as events_count')
            ->withCount('allDivisions as total_divisions_count')
            ->withCount(['allDivisions as completed_divisions_count' => fn ($q) => $q->where('divisions.status', 'complete')])
            ->withCount(['allDivisions as scheduled_divisions_count' => fn ($q) => $q->whereNotNull('divisions.location_label')->where('divisions.status', '!=', 'combined')])
            ->withCount(['allDivisions as schedulable_divisions_count' => fn ($q) => $q->where('divisions.status', '!=', 'combined')])
            ->withCount(['tasks as pending_tasks_count' => fn ($q) => $q->where('completed', false)])
            ->with(['tasks' => fn ($q) => $q->where('completed', false)->orderBy('sort_order')])
            ->orderBy('competition_date')
            ->get();
    }

    public function markTaskComplete(int $taskId): void
    {
        $task = CompetitionTask::find($taskId);
        if (! $task) return;

        $competition = Competition::where('organisation_id', app('tenant')?->id)
            ->find($task->competition_id);
        if (! $competition) return;

        $task->update(['completed' => true, 'completed_at' => now()]);
    }

    public function getInsightsForCompetition(int $competitionId): ?CompetitionInsight
    {
        return CompetitionInsight::where('competition_id', $competitionId)->first();
    }

    public function setStatusAction(): Action
    {
        $statusLabels = [
            'planning'          => 'Planning',
            'open'              => 'Open',
            'enrolments_closed' => 'Enrolments Closed',
            'check_in'          => 'Check-in',
            'running'           => 'Running',
            'complete'          => 'Complete',
        ];

        return Action::make('setStatus')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) =>
                'Set to ' . ($statusLabels[$arguments['targetStatus'] ?? ''] ?? 'Unknown'))
            ->modalDescription(function (array $arguments) use ($statusLabels) {
                $competition = Competition::find($arguments['competitionId'] ?? null);
                $target = $arguments['targetStatus'] ?? null;
                if (! $competition || ! $target) return '';

                return match ([$competition->status, $target]) {
                    ['planning', 'open'] => (function () use ($competition) {
                        $n = $competition->allDivisions()
                            ->whereNull('divisions.location_label')
                            ->whereNotIn('divisions.status', ['combined'])
                            ->count();
                        return "{$n} division(s) have not been assigned to a location. Open for enrolment anyway?";
                    })(),
                    ['open',              'enrolments_closed'] => 'Close enrolments for this competition?',
                    ['enrolments_closed', 'check_in']         => 'This will begin the check-in phase. Scoring will not be active until the competition starts.',
                    ['check_in', 'running']  => (function () use ($competition) {
                        $done = $competition->allDivisions()->where('divisions.status', 'complete')->count();
                        $msg = 'This will start the competition. Undo check-in will be disabled and scoring will become active.';
                        return $done > 0 ? $msg . " Warning: {$done} division(s) are already marked as complete." : $msg;
                    })(),
                    ['running',  'complete'] => (function () use ($competition) {
                        $n = $competition->allDivisions()->whereNotIn('divisions.status', ['complete', 'combined'])->count();
                        $msg = 'Conclude this competition?';
                        return $n > 0 ? "Warning: {$n} division(s) have not been completed. " . $msg : $msg;
                    })(),
                    default => 'Move competition to ' . ($statusLabels[$target] ?? $target) . '?',
                };
            })
            ->modalSubmitActionLabel(fn (array $arguments) =>
                'Set to ' . ($statusLabels[$arguments['targetStatus'] ?? ''] ?? 'Unknown'))
            ->action(function (array $arguments) {
                $competition = Competition::find($arguments['competitionId'] ?? null);
                $target = $arguments['targetStatus'] ?? null;
                if (! $competition || ! $target || $competition->status === $target) return;
                $competition->update(['status' => $target]);
                Notification::make()->title('Competition status updated.')->success()->send();
                $this->dispatch('competition-status-changed', competitionId: $competition->id, newStatus: $target);
                if ($competition->organisation->insights_auto_refresh ?? true) {
                    try {
                        GenerateCompetitionInsightsJob::dispatchFor($competition->fresh());
                        Notification::make()
                            ->success()
                            ->title('AI insights refreshed')
                            ->send();
                    } catch (\Throwable) {
                        Notification::make()
                            ->warning()
                            ->title('AI insights could not be generated')
                            ->body('You can refresh them manually from the Insights page.')
                            ->send();
                    }
                }
            });
    }
}
