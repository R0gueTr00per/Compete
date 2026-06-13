<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use App\Notifications\Notification;
use Filament\Pages\Page;

class MyEnrolmentsPage extends Page
{
    protected static ?string $title            = 'Account';
    protected static ?string $navigationIcon  = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Account';
    protected static string  $view            = 'filament.portal.pages.my-enrolments-page';
    protected static ?string $slug            = 'my-enrolments';
    protected static ?int    $navigationSort  = 6;

    // Withdrawal modal state
    public ?int    $withdrawingId     = null;
    public ?string $withdrawalReason  = '';

    // Edit events modal state
    public ?int   $editingId      = null;
    public array  $editingEntries = [];

    public function getTransactions(): \Illuminate\Support\Collection
    {
        $userId   = auth()->id();
        $tenantId = app('tenant')?->id;

        return EnrolmentCart::where('user_id', $userId)
            ->where('status', 'submitted')
            ->whereHas('enrolments', fn ($q) => $q->withTrashed()
                ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId))
            )
            ->with([
                'competition.organisation',
                'enrolments' => fn ($q) => $q->withTrashed()->whereNotIn('status', ['draft'])
                    ->with([
                        'competition',
                        'competitor',
                        'activeEvents.competitionEvent',
                        'activeEvents.division',
                        'activeEvents.previousDivision',
                        'enrolmentEvents' => fn ($q2) => $q2->where('removed', true)
                            ->with('competitionEvent', 'division'),
                    ]),
            ])
            ->orderByDesc('submitted_at')
            ->get();
    }

    public function getDraftCart(): ?EnrolmentCart
    {
        return EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->whereHas('enrolments', fn ($q) => $q
                ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', app('tenant')?->id))
            )
            ->with(['enrolments.competition'])
            ->latest()
            ->first();
    }

    // ── Withdrawal ───────────────────────────────────────────────────────────

    public function startWithdraw(int $enrolmentId): void
    {
        $this->authoriseEnrolmentAccess($enrolmentId);
        $this->withdrawingId    = $enrolmentId;
        $this->withdrawalReason = '';
    }

    public function cancelWithdraw(): void
    {
        $this->withdrawingId    = null;
        $this->withdrawalReason = '';
    }

    public function confirmWithdraw(): void
    {
        $enrolment = Enrolment::with(['competition.organisation'])->findOrFail($this->withdrawingId);
        $this->authoriseEnrolmentAccess($enrolment->id, $enrolment->competitor_profile_id);

        try {
            app(EnrolmentService::class)->withdraw($enrolment, $this->withdrawalReason ?? '');
            Notification::make()->title('Registration withdrawn.')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->withdrawingId    = null;
        $this->withdrawalReason = '';
    }

    // ── Edit events ──────────────────────────────────────────────────────────

    public function startEdit(int $enrolmentId): void
    {
        $this->authoriseEnrolmentAccess($enrolmentId);

        $enrolment = Enrolment::with(['activeEvents'])->findOrFail($enrolmentId);

        $this->editingId      = $enrolmentId;
        $this->editingEntries = $enrolment->activeEvents()
            ->whereNotNull('division_id')
            ->get()
            ->map(fn ($ee) => 'd' . $ee->division_id)
            ->values()
            ->toArray();
    }

    public function cancelEdit(): void
    {
        $this->editingId      = null;
        $this->editingEntries = [];
    }

    public function saveEdit(): void
    {
        if (empty($this->editingEntries)) {
            Notification::make()->title('Select at least one event.')->warning()->send();
            return;
        }

        $enrolment = Enrolment::findOrFail($this->editingId);
        $this->authoriseEnrolmentAccess($enrolment->id, $enrolment->competitor_profile_id);

        try {
            app(EnrolmentService::class)->editEnrolmentEvents($enrolment, $this->editingEntries);
            Notification::make()->title('Events updated successfully.')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->editingId      = null;
        $this->editingEntries = [];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function canWithdraw(Enrolment $enrolment): bool
    {
        return $enrolment->canWithdraw();
    }

    public function canEditEvents(Enrolment $enrolment): bool
    {
        if (in_array($enrolment->status, ['withdrawn', 'checked_in', 'draft'])) {
            return false;
        }
        $status = $enrolment->competition->status;
        return in_array($status, ['open', 'planning']);
    }

    public function getAvailableEventsForEdit(): array
    {
        if (! $this->editingId) {
            return [];
        }

        $enrolment = Enrolment::with(['competitor', 'competition'])->find($this->editingId);
        if (! $enrolment) {
            return [];
        }

        $competition     = $enrolment->competition;
        $profile         = $enrolment->competitor;
        $divisionService = app(DivisionAssignmentService::class);

        $ctx = (object) [
            'gender'    => $profile->gender,
            'age'       => $profile->age,
            'rank_id'   => $enrolment->rank_id,
            'weight_kg' => $enrolment->weight_kg,
        ];

        $options = [];
        foreach ($competition->competitionEvents()->where('status', 'scheduled')->orderBy('running_order')->get() as $event) {
            foreach ($divisionService->getEligibleDivisions($event, $ctx) as $division) {
                $options["d{$division->id}"] = "{$division->code} — {$event->name}: {$division->label}";
            }
        }

        return $options;
    }

    private function authoriseEnrolmentAccess(int $enrolmentId, ?int $profileId = null): void
    {
        $ownedIds = auth()->user()->ownedProfiles()->pluck('id');

        if ($profileId !== null) {
            if (! $ownedIds->contains($profileId)) {
                abort(403);
            }
            return;
        }

        $enrolment = Enrolment::findOrFail($enrolmentId);
        if (! $ownedIds->contains($enrolment->competitor_profile_id)) {
            abort(403);
        }
    }
}
