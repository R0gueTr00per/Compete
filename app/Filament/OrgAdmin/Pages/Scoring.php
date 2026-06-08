<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Filament\OrgAdmin\Concerns\HasScoringLock;
use App\Models\Competition;
use App\Models\Division;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Scoring extends Page
{
    use HasScoringLock;

    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationLabel = 'Scoring';
    protected static string  $view            = 'filament.org-admin.pages.scoring';

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

    #[Url]
    public ?int $highlight_division = null;

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
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->filter_location ? 'Scoring — ' . $this->filter_location : 'Scoring';
    }

    // ─── Division list ────────────────────────────────────────────────────────

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

    #[Computed]
    public function divisionList(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) return collect();

        $comp = Competition::find($this->competition_id);
        if (! $comp || $comp->status !== 'running') return collect();

        $query = Division::whereHas('competitionEvent', fn ($q) =>
            $q->where('competition_id', $this->competition_id)
              ->whereIn('status', ['scheduled', 'running', 'complete'])
        )
        ->whereNotNull('location_label')
        ->with(['competitionEvent', 'completedBy.selfProfile', 'scoringLockedBy'])
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
        ->withExists(['roundRobinMatches as has_bracket'])
        ->when($this->filter_location, fn ($q) => $q->where('location_label', $this->filter_location))
        ->whereIn('status', ['pending', 'assigned', 'running', 'complete'])
        ->orderBy('code');

        $divisions = $query->get()->toBase();

        $completeDivisionIds = $divisions->where('status', 'complete')->pluck('id');

        $topResultsByDivision = $completeDivisionIds->isNotEmpty()
            ? \Illuminate\Support\Facades\DB::table('results')
                ->join('enrolment_events', 'results.enrolment_event_id', '=', 'enrolment_events.id')
                ->join('enrolments', 'enrolment_events.enrolment_id', '=', 'enrolments.id')
                ->join('competitor_profiles', 'enrolments.competitor_profile_id', '=', 'competitor_profiles.id')
                ->whereIn('enrolment_events.division_id', $completeDivisionIds)
                ->whereNotNull('results.placement')
                ->where('results.placement', '<=', 3)
                ->where('enrolment_events.removed', false)
                ->orderBy('enrolment_events.division_id')
                ->orderBy('results.placement')
                ->select([
                    'enrolment_events.division_id',
                    'results.placement',
                    'results.total_score',
                    'competitor_profiles.first_name',
                    'competitor_profiles.surname',
                ])
                ->get()
                ->groupBy('division_id')
                ->map(fn ($group) => $group->take(3)->map(fn ($r) => (object) [
                    'placement' => $r->placement,
                    'name'      => trim($r->first_name . ' ' . $r->surname),
                    'score'     => $r->total_score,
                ])->values())
            : collect();

        return $divisions->map(fn (Division $div) => (object) [
            'division'          => $div,
            'checked_in_count'  => $div->checked_in_count,
            'competitors_count' => $div->competitors_count,
            'scoring_started'   => $div->absent_count > 0 || $div->scoring_count > 0 || $div->status === 'running' || $div->has_bracket,
            'locked_by_other'   => $this->lockedByOtherName($div),
            'top_results'       => $topResultsByDivision->get($div->id) ?? collect(),
            'is_bracket'        => $div->competitionEvent?->isTournament() ?? false,
        ]);
    }

    // ─── Navigation ───────────────────────────────────────────────────────────

    public function navigateToDivision(int $divisionId): void
    {
        $division = Division::with(['scoringLockedBy', 'competitionEvent'])->find($divisionId);
        if (! $division) return;

        $lockerName = $this->lockedByOtherName($division);
        if ($lockerName) {
            Notification::make()
                ->title('Division in use')
                ->body("{$lockerName} is currently scoring this division.")
                ->warning()
                ->send();
            return;
        }

        $isBracket  = $division->competitionEvent?->isTournament() ?? false;
        $entryClass = $isBracket ? ScoringBracketEntry::class : ScoringEntry::class;

        $this->redirect(
            $entryClass::getUrl(array_filter([
                'division_id'    => $divisionId,
                'competition_id' => $this->competition_id,
            ])),
            navigate: true
        );
    }

    public function jumpToNextIncomplete(): void
    {
        $incomplete = $this->divisionList
            ->filter(fn ($item) => $item->division->status !== 'complete');

        if ($incomplete->isEmpty()) return;

        $first = $incomplete->first();
        $this->navigateToDivision($first->division->id);
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updatedCompetitionId(): void
    {
        $this->filter_location = null;
    }
}
