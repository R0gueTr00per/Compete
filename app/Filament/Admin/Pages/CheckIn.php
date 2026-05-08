<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Services\CheckInService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CheckIn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Check-in';
    protected static string $view = 'filament.admin.pages.check-in';

    public ?int $competition_id = null;
    public string $search = '';
    public array $weights = []; // enrolment_event_id => weight string

    public function mount(): void
    {
        // Prefer a competition happening today, then fall back to next upcoming
        $today = now()->toDateString();

        $competition = Competition::whereIn('status', ['open', 'running'])
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['open', 'running'])
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->competition_id = $competition->id;
        }
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['open', 'running', 'closed'])
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
                'activeEvents.competitionEvent.eventType',
                'activeEvents.division',
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
        $enrolment = Enrolment::with('activeEvents.competitionEvent.eventType')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        // Block check-in if any weight-check event has no confirmed weight
        $missingWeight = $enrolment->activeEvents->filter(
            fn ($ee) => $ee->competitionEvent->eventType->requires_weight_check
                && ! $ee->weight_confirmed_kg
        );

        if ($missingWeight->isNotEmpty()) {
            $eventNames = $missingWeight
                ->map(fn ($ee) => $ee->competitionEvent->eventType->name)
                ->join(', ');
            Notification::make()
                ->title('Weight required before check-in')
                ->body("Enter and confirm weight for: {$eventNames}")
                ->danger()
                ->send();
            return;
        }

        app(CheckInService::class)->checkIn($enrolment);
        Notification::make()->title('Checked in.')->success()->send();
    }

    public function undoCheckIn(int $enrolmentId): void
    {
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

        $enrolment = Enrolment::with('activeEvents.competitionEvent.eventType')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $changed = app(CheckInService::class)->confirmWeightForEnrolment($enrolment, $weight);

        unset($this->weights[$enrolmentId]);

        if ($changed->isNotEmpty()) {
            $names = $changed->map(fn ($ee) => $ee->competitionEvent->eventType->name
                . ' → ' . ($ee->division?->full_label ?? 'unassigned')
            )->join(', ');

            Notification::make()
                ->title('Weight confirmed — division updated')
                ->body("Division changed for: {$names}")
                ->warning()
                ->send();
        } else {
            Notification::make()->title('Weight confirmed.')->success()->send();
        }
    }
}
