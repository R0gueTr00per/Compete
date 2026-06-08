<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Services\CheckInService;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class CheckIn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Check-in';
    protected static string $view = 'filament.admin.pages.check-in';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user->isOrgAdmin($tenant)) return true;
        return $user->getActiveOfficialRoleFor($tenant)?->can_access_checkin ?? false;
    }

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public ?string $code = null;

    public string $search = '';
    public array $weights = [];
    public array $pendingWeightConfirm = [];

    public function mount(): void
    {
        if ($this->code) {
            $this->code = strtoupper(trim($this->code));
        }

        if ($this->code && ! $this->competition_id) {
            $enrolment = Enrolment::where('checkin_code', $this->code)->first();
            if ($enrolment) {
                $this->competition_id = $enrolment->competition_id;
            }
        }

        if ($this->competition_id) {
            return;
        }

        $today = now()->toDateString();

        $orgId = app('tenant')?->id;

        $competition = Competition::whereIn('status', ['check_in', 'running'])
            ->where('organisation_id', $orgId)
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['check_in', 'running'])
                ->where('organisation_id', $orgId)
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->competition_id = $competition->id;
        }
    }

    public function updatedCode(): void
    {
        $this->code = strtoupper(trim($this->code ?? '')) ?: null;

        if ($this->code) {
            $enrolment = Enrolment::where('checkin_code', $this->code)->first();

            if (! $this->competition_id && $enrolment) {
                $this->competition_id = $enrolment->competition_id;
            }

            if ($enrolment && $enrolment->competition_id === $this->competition_id) {
                $this->dispatch('checkin-code-matched');
            }
        }
    }

    public function clearCode(): void
    {
        $this->code = null;
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['check_in', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->orderBy('competition_date', 'desc')
            ->pluck('name', 'id')
            ->toArray();
    }

    #[Computed]
    public function getEnrolments()
    {
        if (! $this->competition_id) {
            return collect();
        }

        if (! $this->code && ! $this->search) {
            return collect();
        }

        $query = Enrolment::where('competition_id', $this->competition_id)
            ->with([
                'competitor',
                'cart',
                'activeEvents.division',
                'activeEvents.competitionEvent' => fn ($q) => $q->withExists([
                    'divisions as has_weight_divisions' => fn ($q) => $q->whereNotNull('weight_class_id'),
                ]),
            ])
            ->whereHas('activeEvents');

        if ($this->code) {
            $query->where('checkin_code', $this->code);
        } elseif ($this->search) {
            $query->whereHas('competitor', fn ($q) =>
                $q->where('surname', 'like', '%' . $this->search . '%')
                  ->orWhere('first_name', 'like', '%' . $this->search . '%')
            );
        }

        return $query->get()
            ->sortBy(fn ($e) => ($e->competitor?->first_name ?? '') . ' ' . ($e->competitor?->surname ?? ''));
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

        $this->code = null;
        $this->dispatch('checkin-complete', id: $enrolmentId);
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('cart')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $platformFee = (float) ($enrolment->cart?->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
        $totalDue    = (float) $enrolment->fee_calculated + $platformFee;

        $enrolment->forceFill([
            'payment_status'      => 'received',
            'payment_amount'      => $totalDue,
            'payment_received_at' => now(),
        ])->save();

        Notification::make()->title('Payment recorded.')->success()->send();
        $this->dispatch('payment-recorded', id: $enrolmentId);
    }

    public function undoCheckIn(int $enrolmentId): void
    {
        $enrolment = Enrolment::find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $hasScores = $enrolment->activeEvents()
            ->whereHas('result', fn ($q) => $q
                ->whereNotNull('total_score')
                ->orWhereNotNull('win_loss')
                ->orWhereHas('judgeScores')
            )
            ->exists();

        if ($hasScores) {
            Notification::make()
                ->title('Cannot undo — scores already recorded for this competitor.')
                ->danger()
                ->send();
            return;
        }

        $competition = Competition::find($this->competition_id);

        app(CheckInService::class)->undoCheckIn($enrolment);

        $notification = Notification::make()->title('Check-in reversed.');

        if ($competition?->status === 'running') {
            $notification->body('Competition is running — verify this competitor has not yet been called.')->warning();
        } else {
            $notification->warning();
        }

        $notification->send();
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
