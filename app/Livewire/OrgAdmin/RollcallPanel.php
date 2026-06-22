<?php

namespace App\Livewire\OrgAdmin;

use App\Livewire\OrgAdmin\Concerns\HasDivisionScoring;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Models\ScoreEvent;
use App\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class RollcallPanel extends Component
{
    use HasDivisionScoring;

    #[Locked]
    public int $division_id = 0;

    #[Locked]
    public ?int $competition_id = null;

    public array $rollcallPresent           = [];
    public bool  $rollcallRequired          = true;
    public bool  $confirmLowCompetitorCount = false;
    public array $perfOrder                 = [];

    public function mount(int $divisionId, ?int $competitionId = null): void
    {
        $this->division_id    = $divisionId;
        $this->competition_id = $competitionId;

        $this->loadRollcallFromSession();
        $this->rollcallRequired = (bool) ($this->selectedDivision?->competitionEvent?->rollcall_required ?? true);
    }

    public function placeholder(): string
    {
        return '<div class="py-8 text-center text-sm text-gray-400">Loading rollcall…</div>';
    }

    public function render()
    {
        return view('livewire.org-admin.rollcall-panel');
    }

    // ─── Rollcall ────────────────────────────────────────────────────────────

    public function toggleRollcallPresent(int $eeId): void
    {
        if (in_array($eeId, $this->rollcallPresent)) {
            $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, [$eeId]));
        } else {
            $this->rollcallPresent[] = $eeId;
        }
        $this->saveRollcallToSession();
    }

    public function markAllPresent(): void
    {
        $dayId = $this->divisionDayId();
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_unique(array_merge($this->rollcallPresent, $ids)));
        $this->saveRollcallToSession();
    }

    public function unmarkAllPresent(): void
    {
        $dayId = $this->divisionDayId();
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, $ids));
        $this->saveRollcallToSession();
    }

    public function beginScoring(?array $presentIds = null): void
    {
        if ($presentIds !== null) {
            $this->rollcallPresent = array_values(array_map('intval', $presentIds));
        }

        $division = Division::with('competitionEvent')->find($this->division_id);
        $event    = $division?->competitionEvent;

        if ($this->rollcallRequired) {
            $dayId = $division?->competition_day_id;
            $activeEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->when(
                    $dayId,
                    fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                    fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
                )
                ->pluck('id');

            $activePresent = collect($this->rollcallPresent)->intersect($activeEeIds);
            if ($activePresent->count() === 0) {
                Notification::make()
                    ->title('0 competitor(s) marked present')
                    ->body('At least 2 competitors are needed to score. Click Begin Scoring again to proceed anyway.')
                    ->warning()
                    ->send();
                return;
            }
            if ($activePresent->count() < 2 && ! $this->confirmLowCompetitorCount) {
                $this->confirmLowCompetitorCount = true;
                Notification::make()
                    ->title($activePresent->count() . ' competitor(s) marked present')
                    ->body('At least 2 competitors are needed to score. Click Begin Scoring again to proceed anyway.')
                    ->warning()
                    ->send();
                return;
            }
            $this->confirmLowCompetitorCount = false;

            $absentIds = $activeEeIds->diff($this->rollcallPresent);
            if ($absentIds->isNotEmpty()) {
                EnrolmentEvent::whereIn('id', $absentIds)->update(['removed' => true]);
            }

            $presentCount = $activePresent->count();
        } else {
            $dayId = $division?->competition_day_id;
            $presentCount = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->when(
                    $dayId,
                    fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                    fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
                )
                ->count();
        }

        $awardedPlaces = match (true) {
            $presentCount <= 2  => $event?->awarded_places_2    ?? 2,
            $presentCount === 3 => $event?->awarded_places_3    ?? 3,
            default             => $event?->awarded_places_4plus ?? 3,
        };
        $statusUpdate = $event?->isTournament() ? [] : ['status' => 'running'];
        if (! empty($statusUpdate) && ! $division?->actual_start_at) {
            $statusUpdate['actual_start_at'] = now();
        }
        $division?->update(array_merge(['awarded_places' => $awardedPlaces], $statusUpdate));

        $this->snapshotCategories($division);

        $this->dispatch('rollcall-completed', divisionId: $this->division_id);
    }

    public function returnToRollcall(): void
    {
        RoundRobinMatch::where('division_id', $this->division_id)->delete();

        Division::find($this->division_id)?->update([
            'placement_override_mode' => false,
            'awarded_places'          => null,
            'status'                  => 'assigned',
            'category_config'         => null,
        ]);

        $eeIds     = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $resultIds = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id');
        JudgeScore::whereIn('result_id', $resultIds)->delete();
        ScoreEvent::whereIn('result_id', $resultIds)->delete();
        Result::whereIn('id', $resultIds)->update([
            'total_score'          => null,
            'tiebreaker_score'     => null,
            'placement'            => null,
            'placement_overridden' => false,
            'win_loss'             => null,
            'disqualified'         => false,
        ]);

        EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

        $this->dispatch('rollcall-cleared', divisionId: $this->division_id);
        $this->dispatch('scoring-cleared');
    }

    public function removeNoShow(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $ee->forceFill(['removed' => true])->save();
        Notification::make()->title('Marked as absent.')->warning()->send();
    }

    public function undoRollcallRemoval(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $eeIds     = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $resultIds = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id');
        JudgeScore::whereIn('result_id', $resultIds)->delete();
        Result::whereIn('id', $resultIds)->update([
            'total_score'          => null,
            'tiebreaker_score'     => null,
            'placement'            => null,
            'placement_overridden' => false,
            'win_loss'             => null,
            'disqualified'         => false,
        ]);

        $ee->forceFill(['removed' => false])->save();

        $this->dispatch('scoring-cleared');
        Notification::make()->title('Competitor added.')->success()->send();
    }

    #[Computed]
    public function getRollcallRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = Division::with('competitionEvent')->find($this->division_id);
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $dayId = $division?->competition_day_id;
        $rows = EnrolmentEvent::where('division_id', $this->division_id)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->with(['enrolment.competitor', 'enrolment.rank'])
            ->get()->toBase();

        [$active, $absent] = $rows->partition(fn ($ee) => ! $ee->removed);

        $map = fn ($ee, bool $isAbsent) => (object) [
            'ee_id'  => $ee->id,
            'name'   => $ee->enrolment->competitor?->full_name ?? '(unknown)',
            'info'   => $this->buildRollcallInfo($ee, $filter),
            'absent' => $isAbsent,
        ];

        return $active->map(fn ($ee) => $map($ee, false))->sortBy('name')
            ->merge($absent->map(fn ($ee) => $map($ee, true))->sortBy('name'));
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function rollcallSessionKey(): string
    {
        return 'scoring_rollcall_' . ($this->competition_id ?? 0);
    }

    private function saveRollcallToSession(): void
    {
        session([$this->rollcallSessionKey() => $this->rollcallPresent]);
    }

    private function loadRollcallFromSession(): void
    {
        $this->rollcallPresent = session($this->rollcallSessionKey(), []);
    }

    private function snapshotCategories(?Division $division): void
    {
        if (! $division) return;
        if (! empty($division->category_config)) return;

        $mode = $division->competitionEvent->score_category_mode ?? 'single';
        if ($mode === 'single') return;
        $categories = $division->competitionEvent->scoreCategories()->get();
        if ($categories->isNotEmpty()) {
            $division->update(['category_config' => $categories->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'weight'     => $c->weight,
                'sort_order' => $c->sort_order,
            ])->values()->all()]);
        }
    }
}
