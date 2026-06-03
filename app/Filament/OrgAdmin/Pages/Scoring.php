<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Services\BracketService;
use App\Services\EnrolmentService;
use App\Services\ScoringService;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Scoring extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationLabel = 'Scoring';
    protected static string  $view            = 'filament.admin.pages.scoring';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user->isOrgAdmin($tenant)) return true;
        return $user->getActiveOfficialRoleFor($tenant)?->can_access_scoring ?? false;
    }

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public ?string $filter_location = null;

    public ?int $division_id = null;

    public array $judgeScores            = [];
    public array $pointsInput            = [];
    public array $placementInput         = [];
    public array $tiebreakerJudgeInputs  = [];
    public array $rollcallPresent        = [];
    public array $bracketScoreInput      = [];
    public array $savedResultIds         = [];
    public array $completedRollcallDivisions = [];
    public bool  $rollcallMode           = false;
    public bool  $panelOpen             = false;
    public bool  $bracketExists          = false;
    public bool  $placementOverrideMode  = false;
    public bool  $confirmLowCompetitorCount = false;
    public bool  $manualPairingMode      = false;
    public array $manualPairings         = [];
    public array $pairingCompetitorList  = [];

    public bool   $penaltyModalOpen           = false;
    public ?int   $penaltyModalResultId       = null;
    public ?int   $penaltyModalMatchId        = null;
    public string $penaltyModalType           = '';
    public array  $penaltyModalReasons        = [];
    public string $penaltyModalSelectedReason = '';

    public ?int $pendingLockDivisionId = null;

    private const LOCK_MINUTES = 15;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $today = now()->toDateString();
            $orgId = app('tenant')?->id;
            $comp  = Competition::whereIn('status', ['running', 'open'])
                ->where('organisation_id', $orgId)
                ->where('competition_date', $today)->first()
                ?? Competition::whereIn('status', ['running', 'open'])
                    ->where('organisation_id', $orgId)
                    ->orderBy('competition_date')->first();

            if ($comp) {
                $this->competition_id = $comp->id;
            }
        }

        $this->loadRollcallFromSession();
        $this->completedRollcallDivisions = $this->loadCompletedRollcallDivisionsFromDb();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->filter_location ? 'Scoring — ' . $this->filter_location : 'Scoring';
    }

    public function markAllPresent(): void
    {
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_unique(array_merge($this->rollcallPresent, $ids)));
        $this->saveRollcallToSession();
    }

    public function unmarkAllPresent(): void
    {
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, $ids));
        $this->saveRollcallToSession();
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['open', 'running', 'enrolments_closed', 'complete'])
            ->where('organisation_id', app('tenant')?->id)
            ->orderBy('competition_date', 'desc')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getLocations(): array
    {
        if (! $this->competition_id) return [];

        $comp      = Competition::find($this->competition_id);
        $locations = collect($comp?->locations ?? [])->filter()->values()->toArray();

        return array_combine($locations, $locations);
    }

    public function getDivisionList(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) return collect();

        $comp = Competition::find($this->competition_id);
        if (! $comp || $comp->status !== 'running') return collect();

        $query = Division::whereHas('competitionEvent', fn ($q) =>
            $q->where('competition_id', $this->competition_id)
              ->whereIn('status', ['scheduled', 'running', 'complete'])
        )
        ->whereNotNull('location_label')
        ->with(['competitionEvent', 'completedBy.selfProfile'])
        ->withCount([
            'enrolmentEvents as checked_in_count' => fn ($q) => $q->whereHas(
                'enrolment', fn ($q2) => $q2->where('status', 'checked_in')
            ),
            'enrolmentEvents as competitors_count' => fn ($q) => $q->whereHas(
                'enrolment', fn ($q2) => $q2->where('status', 'checked_in')
            )->where('removed', false),
            'enrolmentEvents as absent_count' => fn ($q) => $q->where('removed', true),
            'enrolmentEvents as scoring_count' => fn ($q) => $q->whereHas('result', fn ($q2) =>
                $q2->where(fn ($q3) => $q3
                    ->whereNotNull('total_score')
                    ->orWhereNotNull('win_loss')
                    ->orWhereNotNull('placement')
                )
            ),
        ])
        ->when($this->filter_location, fn ($q) => $q->where('location_label', $this->filter_location))
        ->whereIn('status', ['pending', 'assigned', 'running', 'complete'])
        ->orderBy('code');

        return $query->with('scoringLockedBy')->get()->toBase()->map(fn (Division $div) => (object) [
            'division'          => $div,
            'checked_in_count'  => $div->checked_in_count,
            'competitors_count' => $div->competitors_count,
            'scoring_started'   => $div->absent_count > 0 || $div->scoring_count > 0 || $div->status === 'running',
            'locked_by_other'   => $this->lockedByOtherName($div),
        ]);
    }

    private function acquireLock(int $divisionId): void
    {
        Division::where('id', $divisionId)->update([
            'scoring_locked_by' => auth()->id(),
            'scoring_locked_at' => now(),
        ]);
    }

    private function releaseLock(?int $divisionId): void
    {
        if (! $divisionId) return;
        Division::where('id', $divisionId)
            ->where('scoring_locked_by', auth()->id())
            ->update(['scoring_locked_by' => null, 'scoring_locked_at' => null]);
    }

    private function lockedByOtherName(Division $division): ?string
    {
        if (! $division->scoring_locked_by) return null;
        if ($division->scoring_locked_by === auth()->id()) return null;
        if (! $division->scoring_locked_at || $division->scoring_locked_at->lt(now()->subMinutes(self::LOCK_MINUTES))) return null;
        return $division->scoringLockedBy?->name ?? 'Another user';
    }

    private function doOpenDivision(int $divisionId): void
    {
        $this->clearScoringMemory();
        $this->division_id   = $divisionId;
        $this->panelOpen     = true;
        $this->dispatch('scroll-to-division', divisionId: $divisionId);
        $this->bracketExists = RoundRobinMatch::where('division_id', $divisionId)->exists();

        $division = Division::find($divisionId);
        $this->placementOverrideMode = (bool) $division?->placement_override_mode;

        if ($division?->status === 'complete') {
            $this->rollcallMode = false;
            $eeIds = EnrolmentEvent::where('division_id', $divisionId)->pluck('id');
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->whereNotNull('total_score')
                ->pluck('id')
                ->toArray();
            return;
        }

        $eeIds     = EnrolmentEvent::where('division_id', $divisionId)->pluck('id');
        $hasAbsent = EnrolmentEvent::where('division_id', $divisionId)->where('removed', true)->exists();
        $hasScores = $this->bracketExists
            || Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhereNotNull('win_loss'))
                ->exists();

        if ($hasAbsent || $hasScores || $division?->status === 'running') {
            $this->rollcallMode = false;
            if (! in_array($divisionId, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $divisionId;
            }
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->whereNotNull('total_score')
                ->pluck('id')
                ->toArray();
        } else {
            $this->rollcallMode = true;
        }
    }

    private function loadCompletedRollcallDivisionsFromDb(): array
    {
        if (! $this->competition_id) return [];

        return Division::whereHas('competitionEvent', fn ($q) =>
            $q->where('competition_id', $this->competition_id)
        )
        ->where(fn ($q) => $q
            ->whereIn('status', ['running', 'complete'])
            ->orWhereHas('enrolmentEvents', fn ($q2) => $q2->where('removed', true))
            ->orWhereHas('enrolmentEvents.result', fn ($q2) =>
                $q2->where(fn ($q3) => $q3->whereNotNull('total_score')->orWhereNotNull('win_loss'))
            )
        )
        ->pluck('id')
        ->toArray();
    }

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

    private function clearRollcallFromSession(): void
    {
        session()->forget($this->rollcallSessionKey());
    }

    private function clearScoringMemory(): void
    {
        $this->judgeScores              = [];
        $this->pointsInput              = [];
        $this->placementInput           = [];
        $this->tiebreakerJudgeInputs    = [];
        $this->bracketScoreInput        = [];
        $this->savedResultIds           = [];
        $this->rollcallMode             = true;
        $this->placementOverrideMode    = false;
        $this->bracketExists            = false;
        $this->panelOpen                = false;
        $this->confirmLowCompetitorCount = false;
        $this->manualPairingMode        = false;
        $this->manualPairings           = [];
        $this->pairingCompetitorList    = [];
        $this->penaltyModalOpen         = false;
        $this->penaltyModalResultId     = null;
        $this->penaltyModalMatchId      = null;
        $this->penaltyModalType         = '';
        $this->penaltyModalReasons      = [];
        $this->penaltyModalSelectedReason = '';
        // rollcallPresent is intentionally NOT cleared here so ticks survive
        // division switches. Only cancelScoring() resets it explicitly.
    }

    public function selectDivision(int $divisionId): void
    {
        // Same division clicked — toggle panel and refresh the lock heartbeat when re-opening.
        if ($this->division_id === $divisionId) {
            $this->panelOpen = ! $this->panelOpen;
            if ($this->panelOpen) {
                $this->dispatch('scroll-to-division', divisionId: $divisionId);
                $this->acquireLock($divisionId);
            }
            return;
        }

        // Switching divisions — clear any pending lock confirmation for the previous attempt.
        $this->pendingLockDivisionId = null;

        // Release the lock on the division we're leaving.
        if ($this->division_id) {
            $this->releaseLock($this->division_id);
        }

        // Warn if another user is actively scoring this division.
        $division = Division::with('scoringLockedBy')->find($divisionId);
        $lockerName = $this->lockedByOtherName($division);
        if ($lockerName) {
            $this->pendingLockDivisionId = $divisionId;
            Notification::make()
                ->title('Division in use')
                ->body("{$lockerName} is currently scoring this division.")
                ->warning()
                ->send();
            return;
        }

        $this->acquireLock($divisionId);
        $this->doOpenDivision($divisionId);
    }

    public function proceedOpenLocked(): void
    {
        if (! $this->pendingLockDivisionId) return;
        $divisionId = $this->pendingLockDivisionId;
        $this->pendingLockDivisionId = null;
        $this->acquireLock($divisionId);
        $this->doOpenDivision($divisionId);
    }

    public function cancelOpenLocked(): void
    {
        $this->pendingLockDivisionId = null;
    }

    public function deselectDivision(): void
    {
        $this->releaseLock($this->division_id);
        $this->division_id = null;
        $this->clearScoringMemory();
    }

    public function jumpToNextIncomplete(): void
    {
        $incomplete = $this->getDivisionList()
            ->filter(fn ($item) => $item->division->status !== 'complete');

        if ($incomplete->isEmpty()) return;

        if ($this->division_id && $this->panelOpen) {
            $ids        = $incomplete->map(fn ($item) => $item->division->id)->values();
            $currentPos = $ids->search($this->division_id);

            $nextId = ($currentPos !== false && $currentPos < $ids->count() - 1)
                ? $ids->get($currentPos + 1)
                : $ids->first();
        } else {
            $nextId = $incomplete->first()->division->id;
        }

        if ($nextId === $this->division_id && $this->panelOpen) {
            $this->dispatch('scroll-to-division', divisionId: $nextId);
            return;
        }

        $this->selectDivision($nextId);
    }

    public function toggleRollcallPresent(int $eeId): void
    {
        if (in_array($eeId, $this->rollcallPresent)) {
            $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, [$eeId]));
        } else {
            $this->rollcallPresent[] = $eeId;
        }

        $this->saveRollcallToSession();
    }

    public function toggleRollcall(): void
    {
        if ($this->rollcallMode) {
            // Transitioning to scoring — mark anyone not confirmed as absent
            $activeEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
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
            $division     = Division::with('competitionEvent')->find($this->division_id);
            $event        = $division?->competitionEvent;
            $awardedPlaces = match (true) {
                $presentCount <= 2  => $event?->awarded_places_2    ?? 2,
                $presentCount === 3 => $event?->awarded_places_3    ?? 3,
                default             => $event?->awarded_places_4plus ?? 3,
            };
            $division?->update(['awarded_places' => $awardedPlaces, 'status' => 'running']);

            if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $this->division_id;
            }
            $this->rollcallMode = false;
        } else {
            // Going back to rollcall — clear scores and bracket, restore absent flags.
            // rollcallPresent is intentionally preserved so previous ticks are still shown.
            $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
            RoundRobinMatch::where('division_id', $this->division_id)->delete();

            Division::find($this->division_id)?->update(['placement_override_mode' => false, 'awarded_places' => null, 'status' => 'assigned']);

            $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
            Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
                $result->judgeScores()->delete();
                $result->scoreEvents()->delete();
                $result->forceFill([
                    'total_score'          => null,
                    'tiebreaker_score'     => null,
                    'placement'            => null,
                    'placement_overridden' => false,
                    'win_loss'             => null,
                    'disqualified'         => false,
                ])->save();
            });

            EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

            $this->judgeScores           = [];
            $this->savedResultIds        = [];
            $this->tiebreakerJudgeInputs = [];
            $this->pointsInput           = [];
            $this->placementInput        = [];
            $this->bracketScoreInput     = [];
            $this->bracketExists         = false;
            $this->rollcallMode          = true;
            $this->dispatch('scoring-cleared');
        }
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

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->forceFill([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'win_loss'             => null,
                'disqualified'         => false,
            ])->save();
        });
        $this->judgeScores          = [];
        $this->savedResultIds       = [];
        $this->tiebreakerJudgeInputs = [];

        $ee->forceFill(['removed' => false])->save();
        Notification::make()->title('Competitor added.')->success()->send();
    }

    public function getRollcallRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = Division::with('competitionEvent')->find($this->division_id);
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $rows = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
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

    private function buildRollcallInfo(EnrolmentEvent $ee, string $filter): string
    {
        $parts = [];

        if (str_contains($filter, 'age')) {
            $age = $ee->enrolment->competitor?->age;
            if ($age !== null) $parts[] = $age . 'yo';
        }

        if (str_contains($filter, 'weight')) {
            $kg = $ee->enrolment->weight_kg;
            if ($kg) $parts[] = $kg . 'kg';
        }

        if (str_contains($filter, 'rank')) {
            $rank = $ee->enrolment->rank?->name;
            if ($rank) $parts[] = $rank;
        }

        if (str_contains($filter, 'sex')) {
            $gender = $ee->enrolment->competitor?->gender;
            if ($gender) $parts[] = match ($gender) {
                'M' => 'Male',
                'F' => 'Female',
                default => $gender,
            };
        }

        return $parts ? implode(', ', $parts) : '';
    }

    public function getSelectedDivision(): ?Division
    {
        if (! $this->division_id) return null;

        return Division::with('competitionEvent')->find($this->division_id);
    }

    #[Computed]
    public function getCompetitorRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = $this->getSelectedDivision();

        $filter = $division?->competitionEvent?->division_filter ?? '';

        return EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with([
                'enrolment.competitor',
                'enrolment.rank',
                'result.judgeScores',
            ])
            ->get()->toBase()
            ->map(function (EnrolmentEvent $ee) use ($division, $filter) {
                $result = $ee->result
                    ?? app(ScoringService::class)->getOrCreateResult($ee);

                if (! isset($this->judgeScores[$result->id])) {
                    $scores = [];
                    foreach ($result->judgeScores->where('is_tiebreaker', false) as $js) {
                        $scores[$js->judge_number] = number_format((float) $js->score, 1);
                    }
                    if (empty($scores) && $division?->competitionEvent?->default_score !== null) {
                        $judgeCount = $division->competitionEvent->effectiveJudgeCount();
                        for ($i = 1; $i <= $judgeCount; $i++) {
                            $scores[$i] = number_format((float) $division->competitionEvent->default_score, 1);
                        }
                    }
                    $this->judgeScores[$result->id] = $scores;
                }

                if (! isset($this->tiebreakerJudgeInputs[$result->id])) {
                    $tbScores = $result->judgeScores->where('is_tiebreaker', true);
                    if ($tbScores->isNotEmpty()) {
                        foreach ($tbScores as $js) {
                            $this->tiebreakerJudgeInputs[$result->id][$js->judge_number] = (float) $js->score;
                        }
                    }
                }

                if (! isset($this->pointsInput[$result->id]) && $result->total_score !== null) {
                    $this->pointsInput[$result->id] = (int) $result->total_score;
                }

                if (! isset($this->placementInput[$result->id]) && $result->placement_overridden && $result->placement !== null) {
                    $this->placementInput[$result->id] = $result->placement;
                }

                return (object) [
                    'ee'     => $ee,
                    'result' => $result,
                    'name'   => $this->resolveEeName($ee),
                    'info'   => $this->buildRollcallInfo($ee, $filter),
                ];
            })
            ->when(
                $division?->status === 'complete',
                fn ($c) => $c->sortBy(fn ($row) => [$row->result->placement ?? 999, $row->name]),
                fn ($c) => $c->sortBy('name'),
            );
    }

    public function isTournament(): bool
    {
        return in_array($this->getTournamentFormat(), ['round_robin', 'single_elimination', 'double_elimination', 'repechage', 'se_3rd_place']);
    }

    public function isRoundRobin(): bool
    {
        return $this->getTournamentFormat() === 'round_robin';
    }

    public function isScoringComplete(): bool
    {
        if (! $this->division_id || $this->rollcallMode) return false;

        if ($this->isTournament()) {
            if (! $this->bracketExists) return false;
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();
            return $pending === 0
                && RoundRobinMatch::where('division_id', $this->division_id)
                    ->whereNotNull('home_result')
                    ->exists();
        }

        $method = $this->getScoringMethod();
        $rows   = $this->getCompetitorRows();

        if ($rows->isEmpty()) return false;

        if (in_array($method, ['judges_total', 'judges_average'])) {
            $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
            if (! $allSaved) {
                return false;
            }
            if (! $rows->every(fn ($row) => $row->result->disqualified || $row->result->total_score !== null)) {
                return false;
            }
            $stillTied = $this->getStillTiedAfterTiebreaker();
            if ($stillTied->isNotEmpty()) {
                return $stillTied->every(fn ($group) => $group->every(fn ($r) => $r->result->placement_overridden));
            }
            return true;
        }

        return $rows->every(fn ($row) => $row->result->disqualified || match ($method) {
            'win_loss'                    => $row->result->win_loss !== null,
            'first_to_n', 'timed_points' => $row->result->total_score !== null,
            default                       => true,
        });
    }

    public function getTournamentFormat(): ?string
    {
        $div = $this->getSelectedDivision();
        return $div?->competitionEvent->effectiveTournamentFormat();
    }

    public function generateBracket(): void
    {
        if (! $this->division_id) return;

        if (RoundRobinMatch::where('division_id', $this->division_id)->exists()) {
            Notification::make()->title('Bracket already generated.')->warning()->send();
            return;
        }

        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with('enrolment.competitor', 'enrolment.rank')
            ->get()->toBase();

        $n = $competitors->count();
        if ($n < 2) {
            Notification::make()->title('Need at least 2 checked-in competitors.')->warning()->send();
            return;
        }

        $division = $this->getSelectedDivision();
        $event    = $division?->competitionEvent;

        if ($event?->manual_pairing) {
            $ordered = $this->buildBracketOrder($competitors, $event);
            $filter  = $event?->division_filter ?? '';
            $this->pairingCompetitorList = $ordered
                ->map(function ($ee) use ($filter) {
                    $name = $this->resolveEeName($ee);
                    $info = $this->buildRollcallInfo($ee, $filter);
                    return ['ee_id' => $ee->id, 'name' => $name, 'info' => $info];
                })
                ->values()
                ->toArray();
            $this->manualPairings = array_fill(0, (int) ceil($n / 2), ['home' => '', 'away' => '']);
            $this->manualPairingMode = true;
            return;
        }

        $ordered = $this->buildBracketOrder($competitors, $event);
        app(BracketService::class)->generate($division, $ordered);

        $this->bracketExists = true;
        Notification::make()->success()->title("Bracket generated for {$n} competitors.")->send();
    }

    public function closePairingWizard(): void
    {
        $this->manualPairingMode     = false;
        $this->manualPairings        = [];
        $this->pairingCompetitorList = [];
    }

    public function isPairingComplete(): bool
    {
        if (empty($this->manualPairings) || empty($this->pairingCompetitorList)) return false;

        $n      = count($this->pairingCompetitorList);
        $isOdd  = ($n % 2 !== 0);
        $byeCount   = 0;
        $assignedIds = [];

        foreach ($this->manualPairings as $pair) {
            $homeId = isset($pair['home']) && $pair['home'] !== '' ? (int) $pair['home'] : null;
            $awayVal = $pair['away'] ?? '';
            $isBye  = $awayVal === 'bye';
            $awayId = (!$isBye && $awayVal !== '') ? (int) $awayVal : null;

            if (! $homeId) return false;

            if ($isBye) {
                $byeCount++;
            } elseif (! $awayId) {
                return false;
            }

            $assignedIds[] = $homeId;
            if ($awayId) $assignedIds[] = $awayId;
        }

        if ($isOdd && $byeCount !== 1) return false;
        if (! $isOdd && $byeCount !== 0) return false;
        if (count($assignedIds) !== count(array_unique($assignedIds))) return false;

        return true;
    }

    public function confirmManualPairings(): void
    {
        if (! $this->division_id) return;

        if (RoundRobinMatch::where('division_id', $this->division_id)->exists()) {
            Notification::make()->title('Bracket already generated.')->warning()->send();
            $this->manualPairingMode = false;
            $this->bracketExists     = true;
            return;
        }

        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with('enrolment.competitor')
            ->get()->toBase()
            ->keyBy('id');

        $n     = $competitors->count();
        $isOdd = ($n % 2 !== 0);

        if ($n < 2) {
            Notification::make()->title('Need at least 2 checked-in competitors.')->warning()->send();
            return;
        }

        $byeCount    = 0;
        $assignedIds = [];
        $errors      = [];

        foreach ($this->manualPairings as $i => $pair) {
            $homeId  = isset($pair['home']) && $pair['home'] !== '' ? (int) $pair['home'] : null;
            $awayVal = $pair['away'] ?? '';
            $isBye   = $awayVal === 'bye';
            $awayId  = (!$isBye && $awayVal !== '') ? (int) $awayVal : null;

            if (! $homeId) {
                $errors[] = 'Match ' . ($i + 1) . ': no home competitor selected.';
                continue;
            }
            if (! $competitors->has($homeId)) {
                $errors[] = 'Match ' . ($i + 1) . ': competitor is no longer valid.';
                continue;
            }

            if ($isBye) {
                $byeCount++;
            } elseif (! $awayId) {
                $errors[] = 'Match ' . ($i + 1) . ': no away competitor selected.';
            } elseif (! $competitors->has($awayId)) {
                $errors[] = 'Match ' . ($i + 1) . ': competitor is no longer valid.';
            }

            $assignedIds[] = $homeId;
            if ($awayId) $assignedIds[] = $awayId;
        }

        if (count($assignedIds) !== count(array_unique($assignedIds))) {
            $errors[] = 'Each competitor may only appear once.';
        }

        if ($isOdd && $byeCount !== 1) {
            $errors[] = 'With an odd number of competitors, exactly one must receive a bye.';
        }
        if (! $isOdd && $byeCount > 0) {
            $errors[] = 'Byes are only allowed when the competitor count is odd.';
        }

        if (count(array_unique($assignedIds)) < $n) {
            $errors[] = 'Not all competitors have been assigned to a match.';
        }

        if (! empty($errors)) {
            foreach ($errors as $msg) {
                Notification::make()->title($msg)->warning()->send();
            }
            return;
        }

        // Build ordered collection: real pairs first (home, away interleaved), bye competitor last.
        $ordered         = collect();
        $byeCompetitor   = null;

        foreach ($this->manualPairings as $pair) {
            $homeId  = (int) $pair['home'];
            $awayVal = $pair['away'] ?? '';
            $isBye   = $awayVal === 'bye';
            $awayId  = $isBye ? null : (int) $awayVal;

            if ($isBye) {
                $byeCompetitor = $competitors->get($homeId);
            } else {
                $ordered->push($competitors->get($homeId));
                $ordered->push($competitors->get($awayId));
            }
        }

        if ($byeCompetitor) {
            $ordered->push($byeCompetitor);
        }

        app(BracketService::class)->generate($this->getSelectedDivision(), $ordered);

        $this->bracketExists     = true;
        $this->manualPairingMode = false;
        $this->manualPairings    = [];
        $this->pairingCompetitorList = [];

        Notification::make()->success()->title("Bracket generated for {$n} competitors.")->send();
    }

    public function recordBracketWinner(int $matchId, int $winnerEeId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        $homeWins = $match->home_enrolment_event_id === $winnerEeId;
        $match->update(['home_result' => $homeWins ? 'win' : 'loss']);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();

        Notification::make()->success()->title('Result recorded.')->send();
    }

    public function recordBracketScore(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;
        if (! $match->isPending()) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home']
            : null;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away']
            : null;

        if ($homeScore === null || $awayScore === null) {
            Notification::make()->title('Enter scores for both competitors.')->warning()->send();
            return;
        }

        $scoringMethod = $this->getScoringMethod();
        if (in_array($scoringMethod, ['first_to_n', 'timed_points'])) {
            $target = $this->getTargetScore();
            if ($target !== null) {
                if ($homeScore > $target || $awayScore > $target) {
                    Notification::make()->title("Score cannot exceed {$target}.")->warning()->send();
                    return;
                }
                if ($homeScore == $target && $awayScore == $target) {
                    Notification::make()->title("Both competitors cannot have {$target} points.")->warning()->send();
                    return;
                }
                // Neither reaching target is valid — timed rounds may end before target is reached.
            }
        }

        if ($homeScore === $awayScore) {
            if ($this->getTiebreakerMode() === 'overtime') {
                Notification::make()->title('Scores are tied — continue overtime or use head judge override.')->warning()->send();
                $this->dispatch('overtime-tied', matchId: $matchId);
            } else {
                Notification::make()->title('Scores are tied — use sudden death or head judge override.')->warning()->send();
                $this->dispatch('timer-tied', matchId: $matchId);
            }
            return;
        }

        $match->update(['home_score' => $homeScore, 'away_score' => $awayScore]);

        $homeResult = Result::where('enrolment_event_id', $match->home_enrolment_event_id)->first();
        $awayResult = Result::where('enrolment_event_id', $match->away_enrolment_event_id)->first();
        $homeDq     = $homeResult?->disqualified ?? false;
        $awayDq     = $awayResult?->disqualified ?? false;

        if ($homeDq && ! $awayDq) {
            $homeWins = false;
        } elseif ($awayDq && ! $homeDq) {
            $homeWins = true;
        } else {
            $homeWins = $homeScore > $awayScore;
        }

        $match->update(['home_result' => $homeWins ? 'win' : 'loss']);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);

        Notification::make()->success()->title('Score recorded.')->send();
    }

    public function onTimerExpired(int $matchId): void
    {
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home'] : 0.0;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away'] : 0.0;

        if ($homeScore === $awayScore) {
            $this->dispatch('timer-tied', matchId: $matchId);
        }
    }

    public function onOvertimeExpired(int $matchId): void
    {
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home'] : 0.0;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away'] : 0.0;

        if ($homeScore === $awayScore) {
            $this->dispatch('overtime-tied', matchId: $matchId);
        }
        // Not tied: scorer saves normally, no action needed
    }

    public function declareBracketWinner(int $matchId, string $side): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;
        if (! $match->isPending()) return;
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;
        if (! in_array($side, ['home', 'away'])) return;

        $homeWins = $side === 'home';

        $match->update([
            'home_score'  => $homeWins ? 1 : 0,
            'away_score'  => $homeWins ? 0 : 1,
            'home_result' => $homeWins ? 'win' : 'loss',
        ]);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);

        Notification::make()->success()->title('Winner declared by head judge.')->send();
    }

    public function clearBracketResult(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        $format = $this->getTournamentFormat();
        $winner = $match->winnerId();
        $loser  = $match->loserId();

        if ($match->bracket === 'winners') {
            // Clearing any WB result invalidates the repechage / 3rd-place bracket
            if (in_array($format, ['repechage', 'se_3rd_place'])) {
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'repechage')
                    ->delete();
            }

            if ($winner) {
                // Remove winner from next WB match
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'winners', (int) ceil($match->bracket_slot / 2));

                // DE: remove the loser from the LB slot they were sent to
                if ($format === 'double_elimination' && $loser) {
                    [$lbRound, $lbSlot] = $match->round === 1
                        ? [1, (int) ceil($match->bracket_slot / 2)]
                        : [2 * ($match->round - 1), $match->bracket_slot];
                    $this->clearCompetitorFromSlot($loser, $lbRound, 'losers', $lbSlot);
                }

                // Delete any grand final referencing this winner
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)
                        ->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'losers') {
            if ($winner) {
                // LB slot formula: odd rounds keep slot, even rounds merge (ceil)
                $nextSlot = ($match->round % 2 === 1)
                    ? $match->bracket_slot
                    : (int) ceil($match->bracket_slot / 2);
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'losers', $nextSlot);

                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)
                        ->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'repechage') {
            if ($winner) {
                // Repechage is a mini SE bracket
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'repechage', (int) ceil($match->bracket_slot / 2));
            }
        }
        // grand_final: no downstream — just clear the result below

        // Un-DQ competitors whose DQ was tied specifically to this match (e.g. auto-DQ from warnings).
        // Do NOT clear a general forfeit/DQ that was applied outside of this match context.
        $eeIds = array_filter([$match->home_enrolment_event_id, $match->away_enrolment_event_id]);
        foreach ($eeIds as $eeId) {
            $result = Result::where('enrolment_event_id', $eeId)->where('disqualified', true)->first();
            if (! $result) continue;
            $hasExternalDq = \App\Models\MatchPenalty::where('result_id', $result->id)
                ->whereIn('type', ['dq', 'forfeit'])
                ->where(fn ($q) => $q->whereNull('round_robin_match_id')
                    ->orWhere('round_robin_match_id', '!=', $match->id))
                ->exists();
            if (! $hasExternalDq) {
                $result->update(['disqualified' => false]);
            }
        }

        $match->update(['home_result' => null, 'home_score' => null, 'away_score' => null]);
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);
        Notification::make()->success()->title('Result cleared.')->send();
    }

    private function clearCompetitorFromSlot(int $eeId, int $round, string $bracket, int $slot): void
    {
        $match = RoundRobinMatch::where('division_id', $this->division_id)
            ->where('round', $round)
            ->where('bracket', $bracket)
            ->where('bracket_slot', $slot)
            ->first();

        if (! $match) return;

        if ($match->home_enrolment_event_id === $eeId) {
            if ($match->away_enrolment_event_id !== null) {
                // home_enrolment_event_id is NOT NULL in the DB — promote away to home and clear away
                $match->update([
                    'home_enrolment_event_id' => $match->away_enrolment_event_id,
                    'away_enrolment_event_id' => null,
                ]);
            } else {
                $match->delete();
            }
        } elseif ($match->away_enrolment_event_id === $eeId) {
            $match->update(['away_enrolment_event_id' => null]);

            $match->refresh();
            if ($match->home_enrolment_event_id === null) {
                $match->delete();
            }
        }
    }

    public function resetBracket(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();
        $this->bracketExists = false;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)
            ->where('disqualified', true)
            ->update(['disqualified' => false, 'placement' => null]);

        Notification::make()->success()->title('Bracket cleared.')->send();
    }

    private function applyBracketPlacements(): void
    {
        $service         = app(ScoringService::class);
        $division        = Division::with('competitionEvent')->find($this->division_id);
        $competitorCount = EnrolmentEvent::where('division_id', $this->division_id)->where('removed', false)->count();
        $event           = $division?->competitionEvent;
        $cap             = match (true) {
            $competitorCount <= 2  => $event?->awarded_places_2    ?? 2,
            $competitorCount === 3 => $event?->awarded_places_3    ?? 3,
            default               => $event?->awarded_places_4plus ?? 3,
        };
        $matches = RoundRobinMatch::where('division_id', $this->division_id)
            ->whereNotNull('home_result')
            ->get();

        // Reset all non-overridden placements before recomputing from scratch.
        // Without this, stale placements from earlier intermediate states (e.g. R1 losers
        // being briefly treated as semi-finalists) persist in the DB and corrupt the Results page.
        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)
            ->where('placement_overridden', false)
            ->whereNotNull('placement')
            ->update(['placement' => null]);

        $format = $this->getTournamentFormat();

        if ($format === 'round_robin') {
            $allEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->pluck('id');

            if ($matches->isEmpty()) return;

            $winCounts = $allEeIds->mapWithKeys(fn ($id) => [$id => 0])->toArray();
            foreach ($matches as $m) {
                $winnerId = $m->winnerId();
                if ($winnerId && isset($winCounts[$winnerId])) {
                    $winCounts[$winnerId]++;
                }
            }

            arsort($winCounts);

            $rank        = 1;
            $prevWins    = null;
            $countAtRank = 0;
            foreach ($winCounts as $eeId => $wins) {
                if ($prevWins !== null && $wins < $prevWins) {
                    $rank += $countAtRank;
                    $countAtRank = 0;
                }
                $this->setBracketPlacement((int) $eeId, $rank, $service, $cap);
                $prevWins = $wins;
                $countAtRank++;
            }
            return;
        } elseif ($format === 'se_3rd_place') {
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }
            // 3rd: winner of 3rd-place match; fall back to lone semi-final loser if no match was created
            $repFinal = $matches->where('bracket', 'repechage')->sortByDesc('round')->first();
            if ($repFinal?->winnerId()) {
                $this->setBracketPlacement($repFinal->winnerId(), 3, $service, $cap);
                if ($repFinal->loserId()) {
                    $this->setBracketPlacement($repFinal->loserId(), 4, $service, $cap);
                }
            } elseif ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service, $cap);
                }
            }
            return;
        } elseif ($format === 'double_elimination') {
            $gf = $matches->firstWhere('bracket', 'grand_final');
            if ($gf?->winnerId()) {
                $this->setBracketPlacement($gf->winnerId(), 1, $service, $cap);
                if ($gf->loserId()) $this->setBracketPlacement($gf->loserId(), 2, $service, $cap);
            }
        } elseif ($format === 'repechage') {
            // WB final determines 1st/2nd; repechage bracket winner gets 3rd.
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }
            $repMatches   = $matches->where('bracket', 'repechage');
            $maxRepRound  = $repMatches->max('round');
            $repFinal     = $repMatches->where('round', $maxRepRound)->first();
            if ($repFinal?->winnerId()) {
                $this->setBracketPlacement($repFinal->winnerId(), 3, $service, $cap);
                if ($repFinal->loserId()) {
                    $this->setBracketPlacement($repFinal->loserId(), 4, $service, $cap);
                }
            }
        } else {
            // Single elimination — highest WB round determines 1st/2nd; both semi-final losers share 3rd.
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }

            if ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service, $cap);
                }
            }
        }
    }

    private function setBracketPlacement(int $eeId, int $placement, ScoringService $service, int $cap = 3): void
    {
        if ($placement > $cap) return;
        $ee = EnrolmentEvent::with('result')->find($eeId);
        if (! $ee) return;
        $result = $ee->result ?? $service->getOrCreateResult($ee);
        if (! $result->placement_overridden && ! $result->disqualified) {
            $result->forceFill(['placement' => $placement])->save();
        }
    }

    public function getBracketData(): array
    {
        $all = RoundRobinMatch::where('division_id', $this->division_id)
            ->with([
                'homeEnrolmentEvent.enrolment.competitor',
                'homeEnrolmentEvent.enrolment.rank',
                'awayEnrolmentEvent.enrolment.competitor',
                'awayEnrolmentEvent.enrolment.rank',
            ])
            ->orderBy('bracket')->orderBy('round')->orderBy('bracket_slot')
            ->get();

        $division = $this->getSelectedDivision();
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $eeNames = [];
        $eeInfo  = [];
        foreach ($all as $m) {
            foreach ([$m->homeEnrolmentEvent, $m->awayEnrolmentEvent] as $ee) {
                if ($ee && ! isset($eeNames[$ee->id])) {
                    $eeNames[$ee->id] = $this->resolveEeName($ee);
                    $eeInfo[$ee->id]  = $this->buildRollcallInfo($ee, $filter);
                }
            }
        }

        // Index scored matches per competitor so we can check downstream usage only.
        // A match is undoable if the winner hasn't appeared in any scored match that is
        // downstream from it (higher round in same bracket, or a grand_final match).
        // Total-appearance counting was too conservative: it blocked final-round undos
        // because finalists had accumulated multiple prior wins.
        $scoredMatchesByEe = [];
        foreach ($all as $m) {
            if ($m->home_result !== null && ! $m->isBye()) {
                foreach ([$m->home_enrolment_event_id, $m->away_enrolment_event_id] as $eeId) {
                    if ($eeId) $scoredMatchesByEe[$eeId][] = $m;
                }
            }
        }

        $map = ['winners' => [], 'losers' => [], 'repechage' => [], 'grand_final' => []];
        foreach ($all as $m) {
            if ($m->isPending() && ! $m->isBye()) {
                if (! isset($this->bracketScoreInput[$m->id]['home'])) {
                    $this->bracketScoreInput[$m->id]['home'] = $m->home_score !== null
                        ? (string) ((float) $m->home_score + 0)
                        : '0';
                }
                if (! isset($this->bracketScoreInput[$m->id]['away'])) {
                    $this->bracketScoreInput[$m->id]['away'] = $m->away_score !== null
                        ? (string) ((float) $m->away_score + 0)
                        : '0';
                }
            }
            $winner = $m->winnerId();
            $canUndo = false;
            if ($m->home_result !== null && $winner) {
                $usedDownstream = false;
                foreach ($scoredMatchesByEe[$winner] ?? [] as $m2) {
                    if ($m2->id === $m->id) continue;
                    if ($m2->bracket === $m->bracket && $m2->round > $m->round) {
                        $usedDownstream = true;
                        break;
                    }
                    if (in_array($m->bracket, ['winners', 'losers']) && $m2->bracket === 'grand_final') {
                        $usedDownstream = true;
                        break;
                    }
                }
                $canUndo = ! $usedDownstream;
            }
            $map[$m->bracket][$m->round][] = (object) [
                'id'          => $m->id,
                'slot'        => $m->bracket_slot,
                'home_id'     => $m->home_enrolment_event_id,
                'away_id'     => $m->away_enrolment_event_id,
                'home_name'   => isset($eeNames[$m->home_enrolment_event_id]) ? $eeNames[$m->home_enrolment_event_id] : '—',
                'home_info'   => $eeInfo[$m->home_enrolment_event_id] ?? '',
                'away_name'   => $m->away_enrolment_event_id
                    ? ($eeNames[$m->away_enrolment_event_id] ?? '—')
                    : ($m->home_result === null ? 'Waiting...' : 'BYE'),
                'away_info'   => $m->away_enrolment_event_id ? ($eeInfo[$m->away_enrolment_event_id] ?? '') : '',
                'home_result' => $m->home_result,
                'home_score'  => $m->home_score,
                'away_score'  => $m->away_score,
                'is_bye'      => $m->isBye(),
                'is_pending'  => $m->isPending() && ! $m->isBye(),
                'winner_id'   => $winner,
                'loser_id'    => $m->loserId(),
                'can_undo'    => $canUndo,
            ];
        }

        return $map;
    }

    private function resolveEeName(?EnrolmentEvent $ee): string
    {
        if (! $ee) return '—';
        return $ee->enrolment->competitor?->full_name ?? '—';
    }

    private function buildBracketOrder(\Illuminate\Support\Collection $competitors, ?\App\Models\CompetitionEvent $event): \Illuminate\Support\Collection
    {
        // Step 1: base sort order
        $sorted = match ($event?->bracket_sort ?? 'first_name') {
            'surname'            => $competitors->sortBy(fn ($ee) => strtolower($ee->enrolment->competitor?->surname ?? '')),
            'registration_order' => $competitors->sortBy(fn ($ee) => $ee->enrolment->created_at),
            'random'             => $competitors->shuffle(),
            default              => $competitors->sortBy(fn ($ee) => strtolower($ee->enrolment->competitor?->first_name ?? '')),
        };
        $sorted = $sorted->values();

        // Step 2: first-round ordering (mutually exclusive)
        $sorted = match ($event?->bracket_first_round_order) {
            'seed_by_rank'       => $this->applyRankSeeding($sorted),
            'match_similar_age'  => $competitors->sortBy(fn ($ee) => $ee->enrolment->competitor?->age ?? 0)->values(),
            'match_similar_weight' => $competitors->sortBy(fn ($ee) => (float) ($ee->enrolment->weight_kg ?? 0))->values(),
            default              => $sorted,
        };

        if ($event?->bracket_prefer_different_dojo) {
            $sorted = $this->applyPreferDifferentDojo($sorted);
        }

        if ($event?->bracket_avoid_repeat_matchups) {
            $sorted = $this->applyAvoidRepeatMatchups($sorted);
        }

        return $sorted;
    }

    private function applyRankSeeding(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        // Sort highest sort_order first (best rank = top seed), then interleave high-low
        $ranked = $competitors->sortByDesc(fn ($ee) => $ee->enrolment->rank?->sort_order ?? 0)->values();

        $result = [];
        $lo     = 0;
        $hi     = $ranked->count() - 1;

        while ($lo <= $hi) {
            $result[] = $ranked[$lo++];
            if ($lo <= $hi) {
                $result[] = $ranked[$hi--];
            }
        }

        return collect($result);
    }

    private function applyPreferDifferentDojo(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        $arr = $competitors->all();
        $n   = count($arr);

        for ($i = 0; $i < $n - 2; $i += 2) {
            $dojoA = $arr[$i]->enrolment->dojo_name ?? null;
            $dojoB = $arr[$i + 1]->enrolment->dojo_name ?? null;

            if ($dojoA && $dojoB && $dojoA === $dojoB) {
                // Swap the second of this pair with the first of the next pair
                [$arr[$i + 1], $arr[$i + 2]] = [$arr[$i + 2], $arr[$i + 1]];
            }
        }

        return collect($arr);
    }

    private function applyAvoidRepeatMatchups(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        if (! $this->competition_id || $competitors->isEmpty()) return $competitors;

        // Map ee_id → competitor_profile_id for the current division's competitors
        $profileById = $competitors->keyBy('id')->map(fn ($ee) => $ee->enrolment->competitor_id);
        $profileIds  = $profileById->values()->unique();
        $currentDivisionId = $competitors->first()->division_id;

        // Find EEs for the same profiles in OTHER divisions of this competition
        $otherEeMap = EnrolmentEvent::with('enrolment')
            ->whereHas('enrolment', fn ($q) =>
                $q->where('competition_id', $this->competition_id)
                  ->whereIn('competitor_id', $profileIds)
            )
            ->where('division_id', '!=', $currentDivisionId)
            ->get()
            ->keyBy('id')
            ->map(fn ($ee) => $ee->enrolment->competitor_id);

        if ($otherEeMap->isEmpty()) return $competitors;

        // Collect profile_id pairs that have already faced each other
        $priorPairs = RoundRobinMatch::whereIn('home_enrolment_event_id', $otherEeMap->keys())
            ->whereIn('away_enrolment_event_id', $otherEeMap->keys())
            ->get()
            ->mapWithKeys(function ($match) use ($otherEeMap) {
                $a = $otherEeMap[$match->home_enrolment_event_id] ?? null;
                $b = $otherEeMap[$match->away_enrolment_event_id] ?? null;
                if (! $a || ! $b) return [];
                $key = min($a, $b) . '_' . max($a, $b);
                return [$key => true];
            });

        if ($priorPairs->isEmpty()) return $competitors;

        // Best-effort: for each pair, if they've already met, swap with the next element
        $arr = $competitors->all();
        $n   = count($arr);

        for ($i = 0; $i < $n - 2; $i += 2) {
            $a = $profileById[$arr[$i]->id] ?? null;
            $b = $profileById[$arr[$i + 1]->id] ?? null;

            if ($a && $b && $priorPairs->has(min($a, $b) . '_' . max($a, $b))) {
                [$arr[$i + 1], $arr[$i + 2]] = [$arr[$i + 2], $arr[$i + 1]];
            }
        }

        return collect($arr);
    }

    public function getAwardedPlacesLabel(): string
    {
        if (! $this->division_id) return '';

        $division = Division::with('competitionEvent')->find($this->division_id);
        if (! $division) return '';

        $count = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->count();

        $event = $division->competitionEvent;
        $cap   = match (true) {
            $count <= 2  => $event->awarded_places_2    ?? 2,
            $count === 3 => $event->awarded_places_3    ?? 3,
            default      => $event->awarded_places_4plus ?? 3,
        };

        return match ($cap) {
            1       => '1st only',
            2       => '1st & 2nd',
            default => 'Podium',
        };
    }

    public function getScoringMethod(): ?string
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->effectiveScoringMethod();
    }

    public function getJudgeCount(): int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return 3;

        return $div->competitionEvent->effectiveJudgeCount();
    }

    public function getTargetScore(): ?int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->effectiveTargetScore();
    }

    public function getRoundDuration(): ?int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->round_duration_seconds;
    }

    public function getTiebreakerDuration(): ?int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->tiebreak_duration_seconds;
    }

    public function getTiebreakerMode(): string
    {
        $div = $this->getSelectedDivision();
        if (! $div) return 'sudden_death';

        return $div->competitionEvent->getTiebreakerMode();
    }

    public function getOvertimeRounds(): int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return 1;

        return $div->competitionEvent->getOvertimeRounds();
    }

    public function saveJudgeScores(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $service = app(ScoringService::class);
        foreach ($this->judgeScores[$resultId] ?? [] as $judgeNum => $score) {
            if ($score !== null && $score !== '') {
                $service->submitJudgeScore($result, (int) $judgeNum, (float) $score);
            }
        }

        if (! in_array($resultId, $this->savedResultIds)) {
            $this->savedResultIds[] = $resultId;
        }

        Notification::make()->title('Scores saved.')->success()->send();
    }

    public function undoJudgeScores(int $resultId): void
    {
        $result = Result::find($resultId);
        if ($result) {
            $service      = app(ScoringService::class);
            $hasTiebreaker = $result->tiebreaker_score !== null;

            if ($hasTiebreaker) {
                $service->clearTiebreakerScore($result);
                unset($this->tiebreakerJudgeInputs[$resultId]);
            }

            // Clear any head-judge placement overrides on same-score peers
            $eeIds   = EnrolmentEvent::where('division_id', $result->division_id)->pluck('id');
            $cleared = Result::whereIn('enrolment_event_id', $eeIds)
                ->where('total_score', $result->total_score)
                ->where('placement_overridden', true)
                ->pluck('id');

            if ($cleared->isNotEmpty()) {
                Result::whereIn('id', $cleared)->update(['placement_overridden' => false]);
                foreach ($cleared as $rid) {
                    unset($this->placementInput[$rid]);
                }
                if (! $hasTiebreaker) {
                    $service->autoRankDivision(Division::find($result->division_id));
                }
            }
        }

        $this->savedResultIds = array_values(array_diff($this->savedResultIds, [$resultId]));
    }

    public function saveWinLoss(int $resultId, string $value): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->recordWinLoss($result, $value);
        Notification::make()->title('Result recorded.')->success()->send();
    }

    public function addPoints(int $resultId, float $amount): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $target = $this->getTargetScore();
        if ($target !== null && (($result->total_score ?? 0) + $amount) > $target) {
            return;
        }

        app(ScoringService::class)->addPoints($result, $amount);
    }

    public function undoPoints(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->undoLastPoints($result);
    }

    public function getIncrementButtons(): array
    {
        $div = $this->getSelectedDivision();
        if (! $div) return [1];

        return $div->competitionEvent->getIncrementButtons();
    }

    public function savePoints(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->recordPoints($result, (int) ($this->pointsInput[$resultId] ?? 0));
        Notification::make()->title('Points saved.')->success()->send();
    }

    public function overridePlacement(int $resultId): void
    {
        $result    = Result::find($resultId);
        $placement = (int) ($this->placementInput[$resultId] ?? 0);

        if (! $result) return;

        if ($placement < 1) {
            if ($result->placement_overridden) {
                $result->forceFill(['placement' => null, 'placement_overridden' => false])->save();
                Notification::make()->title('Placement cleared.')->success()->send();
            }
            return;
        }

        $service = app(ScoringService::class);
        $service->overridePlacement($result, $placement);

        if ($result->fresh()->tiebreaker_score !== null) {
            $service->clearTiebreakerScore($result);
            unset($this->tiebreakerJudgeInputs[$resultId]);
        }

    }

    public function headJudgeSavePlacement(int $resultId): void
    {
        $result    = Result::find($resultId);
        $placement = (int) ($this->placementInput[$resultId] ?? 0);

        if (! $result || $placement < 1) {
            Notification::make()->title('Select a place first.')->warning()->send();
            return;
        }

        $service = app(ScoringService::class);
        $service->overridePlacement($result, $placement);
        $service->autoRankDivision(Division::find($result->division_id));
        Notification::make()->title('Placement saved.')->success()->send();
    }

    public function headJudgeUndoPlacement(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->clearPlacementOverride($result);
        unset($this->placementInput[$resultId]);
        Notification::make()->title('Placement cleared.')->success()->send();
    }

    public function clearOverride(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->clearPlacementOverride($result);
        Notification::make()->title('Override cleared — auto-ranked.')->success()->send();
    }

    public function togglePlacementOverrideMode(): void
    {
        $this->placementOverrideMode = ! $this->placementOverrideMode;

        $division = Division::find($this->division_id);
        if (! $division) return;

        $division->placement_override_mode = $this->placementOverrideMode;
        $division->save();

        if ($this->placementOverrideMode) {
            // Null out stale auto-ranked placements so non-set competitors start blank.
            Result::where('division_id', $division->id)
                ->where('placement_overridden', false)
                ->update(['placement' => null]);
        } else {
            // Clear all overrides and placements, then re-rank once.
            Result::where('division_id', $division->id)
                ->update(['placement_overridden' => false, 'placement' => null]);

            $divisionResultIds = [];
            foreach ($this->getCompetitorRows() as $row) {
                $id = $row->result->id;
                unset($this->placementInput[$id]);
                $divisionResultIds[] = $id;
            }

            $this->savedResultIds = array_values(array_diff($this->savedResultIds, $divisionResultIds));

            app(ScoringService::class)->autoRankDivision($division);

            Notification::make()->title('Auto-ranking restored.')->success()->send();
        }
    }

    public function getEnabledPenaltyTypes(): array
    {
        $div = $this->getSelectedDivision();
        if (! $div) return [];

        $order   = ['warn', 'deduction', 'opponent_point', 'dq', 'forfeit'];
        $enabled = $div->competitionEvent->enabledPenaltyTypes();
        usort($enabled, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));
        return $enabled;
    }

    public function hasPenalties(): bool
    {
        return ! empty($this->getEnabledPenaltyTypes());
    }

    public function getPenaltyLabel(string $type): string
    {
        return match ($type) {
            'warn'           => 'Warn',
            'dq'             => 'DQ',
            'forfeit'        => 'Forfeit',
            'deduction'      => '-1',
            'opponent_point' => '+1 Opp',
            default          => $type,
        };
    }

    public function getDqLabel(int $resultId): string
    {
        $type = MatchPenalty::where('result_id', $resultId)
            ->whereIn('type', ['forfeit', 'dq'])
            ->latest()
            ->value('type');

        return $type === 'forfeit' ? 'Forfeit' : 'DQ';
    }

    public function getWarnCount(int $resultId, ?int $matchId = null): int
    {
        return MatchPenalty::where('result_id', $resultId)
            ->where('type', 'warn')
            ->when($matchId, fn ($q) => $q->where('round_robin_match_id', $matchId))
            ->count();
    }

    public function getPenaltyLog(int $resultId, ?int $matchId = null): array
    {
        $penalties = MatchPenalty::where('result_id', $resultId)
            ->when($matchId, fn ($q) => $q->where('round_robin_match_id', $matchId))
            ->orderBy('created_at')
            ->get();

        $warnCount = 0;
        $log = [];
        foreach ($penalties as $penalty) {
            if ($penalty->type === 'warn') {
                $warnCount++;
                $ordinal = match ($warnCount) {
                    1 => '1st', 2 => '2nd', 3 => '3rd',
                    default => "{$warnCount}th",
                };
                $log[] = ['id' => $penalty->id, 'label' => "{$ordinal} warning" . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'dq') {
                $log[] = ['id' => $penalty->id, 'label' => 'DQ' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'forfeit') {
                $log[] = ['id' => $penalty->id, 'label' => 'Forfeit' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'deduction') {
                $log[] = ['id' => $penalty->id, 'label' => '-1 deduction'];
            } elseif ($penalty->type === 'opponent_point') {
                $log[] = ['id' => $penalty->id, 'label' => '+1 to opponent'];
            }
        }
        return $log;
    }

    public function hasUndoablePenalty(int $resultId, ?int $matchId = null): bool
    {
        return MatchPenalty::where('result_id', $resultId)
            ->when($matchId, fn ($q) => $q->where('round_robin_match_id', $matchId))
            ->whereNotIn('type', ['dq'])
            ->exists();
    }

    public function openPenaltyModal(int $resultId, string $type, ?int $matchId = null): void
    {
        $div = $this->getSelectedDivision();
        if (! $div) return;

        $this->penaltyModalResultId = $resultId;
        $this->penaltyModalMatchId  = $matchId;
        $this->penaltyModalType     = $type;
        $this->penaltyModalSelectedReason = '';

        if (in_array($type, ['warn', 'dq', 'forfeit'])) {
            $reasons = $div->competitionEvent->penaltyReasonsFor($type);
            if (empty($reasons)) {
                $this->applyPenalty($resultId, $type, null, $matchId);
                return;
            }
            $this->penaltyModalReasons = $reasons;
            $this->penaltyModalOpen    = true;
            $this->dispatch('open-modal', id: 'penalty-reason-modal');
        } else {
            // deduction / opponent_point: apply immediately
            $this->applyPenalty($resultId, $type, null, $matchId);
        }
    }

    public function confirmPenalty(): void
    {
        if (! $this->penaltyModalResultId || ! $this->penaltyModalType) return;

        $this->applyPenalty(
            $this->penaltyModalResultId,
            $this->penaltyModalType,
            $this->penaltyModalSelectedReason ?: null,
            $this->penaltyModalMatchId,
        );

        $this->penaltyModalOpen           = false;
        $this->penaltyModalSelectedReason = '';
    }

    private function applyPenalty(int $resultId, string $type, ?string $reason, ?int $matchId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $match = $matchId ? RoundRobinMatch::find($matchId) : null;

        $opponentResult = null;
        if ($type === 'opponent_point' && $match) {
            $opponentEeId = $match->home_enrolment_event_id === $result->enrolment_event_id
                ? $match->away_enrolment_event_id
                : $match->home_enrolment_event_id;
            $opponentResult = $opponentEeId ? Result::where('enrolment_event_id', $opponentEeId)->first() : null;
        }

        ['triggered_dq' => $triggeredDq] = app(ScoringService::class)->addPenalty(
            $result, $type, $reason, $match, $opponentResult
        );

        $label = match ($type) {
            'warn'           => 'Warning added.',
            'dq'             => 'DQ applied.',
            'forfeit'        => 'Forfeit applied.',
            'deduction'      => '-1 deduction applied.',
            'opponent_point' => '+1 awarded to opponent.',
            default          => 'Penalty applied.',
        };
        Notification::make()->title($label)->warning()->send();

        if ($triggeredDq) {
            $result->refresh();
            $this->handleDqAutoAdvance($result);
        }
    }

    public function undoPenalty(int $resultId, ?int $matchId = null): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $match = $matchId ? RoundRobinMatch::find($matchId) : null;

        $reversedDq = app(ScoringService::class)->undoLastPenalty($result, $match);

        Notification::make()->title('Penalty undone.')->success()->send();

        if ($reversedDq) {
            $result->refresh();
        }
    }

    private function handleDqAutoAdvance(Result $result): void
    {
        if (! $result->disqualified) return;
        if (! $this->isTournament()) return;
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points', 'win_loss'])) return;

        $eeId  = $result->enrolment_event_id;
        $match = RoundRobinMatch::where('division_id', $this->division_id)
            ->whereNull('home_result')
            ->whereNotNull('away_enrolment_event_id')
            ->where(fn ($q) => $q->where('home_enrolment_event_id', $eeId)
                ->orWhere('away_enrolment_event_id', $eeId))
            ->first();

        if ($match) {
            $winnerEeId = $match->home_enrolment_event_id === $eeId
                ? $match->away_enrolment_event_id
                : $match->home_enrolment_event_id;

            $otherResult = $winnerEeId
                ? Result::where('enrolment_event_id', $winnerEeId)->first()
                : null;

            if ($winnerEeId && ! $otherResult?->disqualified) {
                $homeWins = $match->home_enrolment_event_id === $winnerEeId;
                $match->update(['home_result' => $homeWins ? 'win' : 'loss']);
                app(BracketService::class)->advance($match->fresh());
                $this->applyBracketPlacements();
                Notification::make()->title('Match awarded to opponent.')->info()->send();
            }
        }
    }

    public function toggleDisqualify(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->toggleDisqualify($result);
        $result->refresh();

        $label = $result->disqualified ? 'Disqualified.' : 'Disqualification removed.';
        Notification::make()->title($label)->warning()->send();

        $this->handleDqAutoAdvance($result);
    }

    public function hasSavedScores(): bool
    {
        return ! empty($this->savedResultIds);
    }

    public function resetJudgeScores(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update(['placement_override_mode' => false]);

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->forceFill([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'disqualified'         => false,
            ])->save();
        });

        $this->judgeScores           = [];
        $this->tiebreakerJudgeInputs = [];
        $this->placementOverrideMode = false;
        Notification::make()->title('Scores cleared.')->success()->send();
    }

    public function cancelScoring(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();

        EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

        Division::find($this->division_id)?->update([
            'placement_override_mode' => false,
            'awarded_places'          => null,
            'status'                  => 'assigned',
            'scoring_locked_by'       => null,
            'scoring_locked_at'       => null,
        ]);

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->scoreEvents()->delete();
            $result->forceFill([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'win_loss'             => null,
                'disqualified'         => false,
            ])->save();
        });

        $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
        $cancelledEeIds = $eeIds->toArray();
        $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, $cancelledEeIds));
        $this->saveRollcallToSession();
        $this->division_id = null;
        $this->clearScoringMemory();
        $this->dispatch('scoring-cleared');
    }

    public function getTiedGroups(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        $rows = $this->getCompetitorRows();

        // Tiebreaker only activates once ALL non-DQ competitors have had their score saved
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) {
            return collect();
        }

        $division     = $this->getSelectedDivision();
        $defaultScore = $division?->competitionEvent->default_score;
        $judgeCount   = $this->getJudgeCount();

        // Build score groups sorted highest-first and track cumulative placement.
        // A tied group only needs a tiebreaker if its starting position is within medal positions (≤ 3).
        $scoreGroups = $rows
            ->filter(fn ($row) => $row->result->total_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score)
            ->sortByDesc(fn ($group, $key) => (float) $key);

        $cumulative  = 0;
        $tiedGroups  = collect();

        foreach ($scoreGroups as $group) {
            $startingPosition = $cumulative + 1;

            if ($group->count() > 1 && $startingPosition <= 3) {
                if ($defaultScore !== null) {
                    foreach ($group as $row) {
                        $resultId = $row->result->id;
                        if (! isset($this->tiebreakerJudgeInputs[$resultId])) {
                            for ($j = 1; $j <= $judgeCount; $j++) {
                                $this->tiebreakerJudgeInputs[$resultId][$j] = (float) $defaultScore;
                            }
                        }
                    }
                }

                $tiedGroups->push((object) [
                    'group'             => $group,
                    'starting_position' => $startingPosition,
                ]);
            }

            $cumulative += $group->count();
        }

        return $tiedGroups->values();
    }

    public function getStillTiedAfterTiebreaker(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        $rows = $this->getCompetitorRows();

        // Tiebreaker only activates once ALL non-DQ competitors have had their score saved
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) {
            return collect();
        }

        // Groups where all members have a tiebreaker_score but they're still equal
        return $rows
            ->filter(fn ($row) => $row->result->tiebreaker_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score . '|' . (string) $row->result->tiebreaker_score)
            ->filter(fn ($group) => $group->count() > 1)
            ->values();
    }

    public function saveTiebreakerScores(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $inputs  = $this->tiebreakerJudgeInputs[$resultId] ?? [];
        $method  = $this->getScoringMethod();
        $scores  = collect($inputs)->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v);

        if ($scores->isEmpty()) {
            Notification::make()->title('Enter at least one judge score.')->warning()->send();
            return;
        }

        $total = $method === 'judges_average'
            ? round($scores->avg(), 3)
            : round($scores->sum(), 3);

        $service = app(ScoringService::class);
        foreach ($inputs as $judgeNum => $score) {
            if ($score !== null && $score !== '') {
                $service->submitJudgeScore($result, (int) $judgeNum, (float) $score, true);
            }
        }
        $service->saveTiebreakerScore($result, $total);
        Notification::make()->title('Tiebreaker score saved.')->success()->send();
    }

    public function clearTiebreakerScore(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        // Clear head-judge placement overrides on this result and same-score peers
        $eeIds   = EnrolmentEvent::where('division_id', $result->division_id)->pluck('id');
        $cleared = Result::whereIn('enrolment_event_id', $eeIds)
            ->where('total_score', $result->total_score)
            ->where('placement_overridden', true)
            ->pluck('id');

        if ($cleared->isNotEmpty()) {
            Result::whereIn('id', $cleared)->update(['placement_overridden' => false]);
            foreach ($cleared as $rid) {
                unset($this->placementInput[$rid]);
            }
        }

        unset($this->tiebreakerJudgeInputs[$resultId]);
        app(ScoringService::class)->clearTiebreakerScore($result);
        Notification::make()->title('Tiebreaker score cleared.')->success()->send();
    }

    public function reactivateDivision(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update([
            'status'       => 'assigned',
            'completed_at' => null,
            'completed_by' => null,
        ]);

        // Pre-populate savedResultIds so the tiebreaker gate works immediately
        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
            ->whereNotNull('total_score')
            ->pluck('id')
            ->toArray();

        // Keep the competitor count visible in the division list
        if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
            $this->completedRollcallDivisions[] = $this->division_id;
        }

        Notification::make()->title('Division re-activated — scoring is now editable.')->warning()->send();
    }

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        if ($this->isTournament()) {
            if (! $this->bracketExists) {
                Notification::make()
                    ->warning()
                    ->title('Cannot complete — bracket has not been generated yet.')
                    ->send();
                return;
            }

            // All non-bye bracket matches must have a result
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();

            if ($pending > 0) {
                Notification::make()
                    ->warning()
                    ->title("Cannot complete — {$pending} bracket match(es) still pending.")
                    ->send();
                return;
            }
        } else {
            $method = $this->getScoringMethod();

            if (in_array($method, ['judges_total', 'judges_average'])) {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->total_score === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no score entered.")
                        ->send();
                    return;
                }
            } elseif ($method === 'win_loss') {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->win_loss === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no result recorded.")
                        ->send();
                    return;
                }
            } elseif (in_array($method, ['first_to_n', 'timed_points'])) {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->total_score === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no points recorded.")
                        ->send();
                    return;
                }
            }
        }

        Division::find($this->division_id)?->update([
            'status'            => 'complete',
            'completed_at'      => now(),
            'completed_by'      => auth()->id(),
            'scoring_locked_by' => null,
            'scoring_locked_at' => null,
        ]);
        Notification::make()->title('Division marked complete.')->success()->send();
    }

    public function updatedCompetitionId(): void
    {
        $this->filter_location            = null;
        $this->division_id                = null;
        $this->rollcallPresent            = [];
        $this->completedRollcallDivisions = [];
        $this->clearRollcallFromSession();
        $this->clearScoringMemory();
        $this->completedRollcallDivisions = $this->loadCompletedRollcallDivisionsFromDb();
    }

    public function updatedFilterLocation(): void
    {
        $this->division_id = null;
        $this->clearScoringMemory();
    }
}
