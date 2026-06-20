<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Filament\OrgAdmin\Concerns\HasScoringLock;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ScoringBracketEntry extends Page
{
    use HasScoringLock;

    protected static bool   $shouldRegisterNavigation = false;
    protected static string $view                     = 'filament.org-admin.pages.scoring-bracket-entry';

    #[Url]
    public ?int $division_id = null;

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public ?int $competition_day_id = null;

    #[Url]
    public ?string $filter_location = null;

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user->isOrgAdmin($tenant)) return true;
        return $user->getActiveOfficialRoleFor($tenant)?->can_access_scoring ?? false;
    }

    public function mount(): void
    {
        if (! $this->division_id) {
            $this->redirect(Scoring::getUrl(), navigate: true);
            return;
        }

        $division = Division::with(['scoringLockedBy', 'competitionEvent'])->find($this->division_id);

        if (! $division) {
            $this->redirect(Scoring::getUrl(), navigate: true);
            return;
        }

        $lockerName = $this->lockedByOtherName($division);
        if ($lockerName) {
            Notification::make()
                ->title('Division in use')
                ->body("{$lockerName} is currently scoring this division.")
                ->warning()
                ->send();
            $this->redirect(
                Scoring::getUrl(array_filter(['competition_id' => $this->competition_id, 'competition_day_id' => $this->competition_day_id, 'highlight_division' => $this->division_id, 'filter_location' => $this->filter_location])),
                navigate: true
            );
            return;
        }

        $this->acquireLock($this->division_id);

        // Bulk-insert missing results at mount
        if ($division->status !== 'complete') {
            $dayId = $division->competition_day_id;
            $eeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->when(
                    $dayId,
                    fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                    fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
                )
                ->pluck('id');

            if ($eeIds->isNotEmpty()) {
                Result::insertOrIgnore($eeIds->map(fn ($eeId) => [
                    'enrolment_event_id' => $eeId,
                    'division_id'        => $this->division_id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ])->values()->all());
            }
        }
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $div = Division::with('competitionEvent')->find($this->division_id);
        if (! $div) return 'Scoring';
        $main = e(trim($div->code . ' — ' . $div->competitionEvent?->name));
        $label = $div->label ? e($div->label) : null;
        return new \Illuminate\Support\HtmlString(
            $main . ($label ? ' <span style="opacity:0.45;font-weight:400"> — ' . $label . '</span>' : '')
        );
    }

    public function leavePage(): void
    {
        $this->releaseLock($this->division_id);
        $this->redirect(
            Scoring::getUrl(array_filter(['competition_id' => $this->competition_id, 'competition_day_id' => $this->competition_day_id, 'highlight_division' => $this->division_id, 'filter_location' => $this->filter_location])),
            navigate: true
        );
    }

    #[On('scoring-panel-closed')]
    public function onPanelClosed(): void
    {
        $this->releaseLock($this->division_id);
        $this->redirect(
            Scoring::getUrl(array_filter(['competition_id' => $this->competition_id, 'competition_day_id' => $this->competition_day_id, 'highlight_division' => $this->division_id, 'filter_location' => $this->filter_location])),
            navigate: true
        );
    }
}
