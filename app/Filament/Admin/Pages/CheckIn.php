<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Services\CheckInService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CheckIn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Check-in';
    protected static string $view = 'filament.admin.pages.check-in';

    #[Url]
    public ?int $competition_id = null;
    public string $search = '';
    public array $weights = [];
    public array $paymentAmounts = [];
    public array $pendingWeightConfirm = [];

    public function mount(): void
    {
        if ($this->competition_id) {
            return;
        }

        $today = now()->toDateString();

        $competition = Competition::whereIn('status', ['check_in', 'running'])
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['check_in', 'running'])
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->competition_id = $competition->id;
        }
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['check_in', 'running'])
            ->orderBy('competition_date', 'desc')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getEnrolments()
    {
        if (! $this->competition_id) {
            return collect();
        }

        $query = Enrolment::where('competition_id', $this->competition_id)
            ->with([
                'competitor.competitorProfile',
                'activeEvents.division',
                'activeEvents.competitionEvent' => fn ($q) => $q->withExists([
                    'divisions as has_weight_divisions' => fn ($q) => $q->whereNotNull('weight_class_id'),
                ]),
            ])
            ->whereHas('activeEvents');

        if ($this->search) {
            $query->whereHas('competitor', fn ($q) =>
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('competitorProfile', fn ($q2) =>
                      $q2->where('surname', 'like', '%' . $this->search . '%')
                         ->orWhere('first_name', 'like', '%' . $this->search . '%')
                  )
            );
        }

        return $query->get()
            ->sortBy(fn ($e) => $e->competitor?->competitorProfile?->surname ?? $e->competitor?->name);
    }

    public function checkIn(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('activeEvents.competitionEvent')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        // Block check-in if any weight-check event has no confirmed weight
        $missingWeight = $enrolment->activeEvents->filter(
            fn ($ee) => $ee->competitionEvent->requires_weight_check
                && ! $ee->weight_confirmed_kg
        );

        if ($missingWeight->isNotEmpty()) {
            $eventNames = $missingWeight
                ->map(fn ($ee) => $ee->competitionEvent->name)
                ->join(', ');
            Notification::make()
                ->title('Weight required before check-in')
                ->body("Enter and confirm weight for: {$eventNames}")
                ->danger()
                ->send();
            return;
        }

        $competition = Competition::find($this->competition_id);
        if ($competition?->status === 'running') {
            Notification::make()
                ->title('Competition has begun')
                ->body('This competitor is being checked in late — some events they were enrolled in may have been missed.')
                ->warning()
                ->send();
        }

        app(CheckInService::class)->checkIn($enrolment);
        Notification::make()->title('Checked in.')->success()->send();
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $amount = isset($this->paymentAmounts[$enrolmentId])
            ? (float) $this->paymentAmounts[$enrolmentId]
            : null;

        $enrolment->update([
            'payment_status' => 'received',
            'payment_amount' => $amount ?? $enrolment->fee_calculated,
        ]);

        unset($this->paymentAmounts[$enrolmentId]);
        Notification::make()->title('Payment recorded.')->success()->send();
    }

    public function undoCheckIn(int $enrolmentId): void
    {
        $competition = Competition::find($this->competition_id);
        if ($competition?->status === 'running') {
            Notification::make()->title('Cannot undo check-in — competition is running.')->danger()->send();
            return;
        }

        $enrolment = Enrolment::find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        app(CheckInService::class)->undoCheckIn($enrolment);
        Notification::make()->title('Check-in reversed.')->warning()->send();
    }

    public function confirmWeight(int $enrolmentId): void
    {
        $weight = isset($this->weights[$enrolmentId]) ? (float) $this->weights[$enrolmentId] : null;

        if (! $weight || $weight <= 0) {
            Notification::make()->title('Enter a valid weight before confirming.')->danger()->send();
            return;
        }

        $enrolment = Enrolment::with(['activeEvents.competitionEvent', 'activeEvents.division'])->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $changes = app(CheckInService::class)->applyWeightWithDivisions($enrolment, $weight);

        if ($changes->isNotEmpty()) {
            // Division changed — show confirmation panel (change already in DB)
            $this->pendingWeightConfirm[$enrolmentId] = [
                'weight_kg' => $weight,
                'changes'   => $changes->toArray(),
            ];
        } else {
            unset($this->weights[$enrolmentId]);
            Notification::make()->title('Weight confirmed.')->success()->send();
        }
    }

    public function acceptDivisionChange(int $enrolmentId): void
    {
        // Division already updated in DB — just dismiss the confirmation panel
        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
        Notification::make()->title('Weight confirmed — division updated.')->success()->send();
    }

    public function ignoreDivisionChange(int $enrolmentId): void
    {
        $pending = $this->pendingWeightConfirm[$enrolmentId] ?? null;
        if ($pending) {
            app(CheckInService::class)->revertDivisionChanges($pending['changes']);
        }
        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
        Notification::make()->title('Weight confirmed — original division kept.')->success()->send();
    }

    public function cancelWeightChange(int $enrolmentId): void
    {
        $pending  = $this->pendingWeightConfirm[$enrolmentId] ?? null;
        $enrolment = Enrolment::find($enrolmentId);

        if ($enrolment && $enrolment->competition_id === $this->competition_id) {
            $svc = app(CheckInService::class);
            if ($pending) {
                $svc->revertDivisionChanges($pending['changes']);
            }
            $svc->revertWeight($enrolment);
        }

        unset($this->pendingWeightConfirm[$enrolmentId]);
    }
}
