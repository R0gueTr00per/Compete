<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\CompetitionDay;
use App\Models\Enrolment;
use App\Services\CheckInService;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class CheckIn extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $navigationLabel = 'Check-in';
    protected string $view = 'filament.admin.pages.check-in';

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
    public ?int $selectedDayId = null;

    #[Url]
    public ?string $code = null;

    public string $search = '';
    public array $weights = [];
    public array $pendingWeightConfirm = [];

    // [enrolmentId => dayId] — tracks weight confirmed this session per enrolment+day
    public array $weightConfirmedForDay = [];

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

        if (! $this->competition_id) {
            $today = now()->toDateString();
            $orgId = app('tenant')?->id;

            $competition = Competition::whereIn('status', ['enrolments_closed', 'running'])
                ->where('organisation_id', $orgId)
                ->whereHas('competitionDays', fn ($q) => $q->where('date', $today))
                ->first()
                ?? Competition::whereIn('status', ['enrolments_closed', 'running'])
                    ->where('organisation_id', $orgId)
                    ->orderBy('competition_date')
                    ->first();

            if ($competition) {
                $this->competition_id = $competition->id;
            }
        }

        if ($this->competition_id && ! $this->selectedDayId) {
            $this->autoSelectDay();
        }
    }

    private function autoSelectDay(): void
    {
        if (! $this->competition_id) {
            return;
        }

        $today = now()->toDateString();
        $days  = CompetitionDay::where('competition_id', $this->competition_id)
            ->orderBy('date')
            ->get();

        if ($days->isEmpty()) {
            return;
        }

        $todayDay = $days->first(fn ($d) => $d->date->toDateString() === $today);
        $this->selectedDayId = ($todayDay ?? $days->first())->id;
    }

    public function updatedCompetitionId(): void
    {
        $this->selectedDayId         = null;
        $this->weights               = [];
        $this->pendingWeightConfirm  = [];
        $this->weightConfirmedForDay = [];
        $this->autoSelectDay();
    }

    public function updatedSelectedDayId(): void
    {
        $this->weights               = [];
        $this->pendingWeightConfirm  = [];
        $this->weightConfirmedForDay = [];
    }

    public function updatedCode(): void
    {
        $this->code = strtoupper(trim($this->code ?? '')) ?: null;

        if ($this->code) {
            $enrolment = Enrolment::where('checkin_code', $this->code)->first();

            if (! $this->competition_id && $enrolment) {
                $this->competition_id = $enrolment->competition_id;
                $this->autoSelectDay();
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
        return Competition::whereIn('status', ['enrolments_closed', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->orderBy('competition_date', 'desc')
            ->pluck('name', 'id')
            ->toArray();
    }

    #[Computed]
    public function competitionDays()
    {
        if (! $this->competition_id) {
            return collect();
        }

        return CompetitionDay::where('competition_id', $this->competition_id)
            ->orderBy('date')
            ->get();
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
                'checkIns.competitionDay',
                'activeEvents.division',
                'activeEvents.competitionEvent',
            ])
            ->whereHas('activeEvents');

        // Filter to competitors who have divisions on the selected day
        if ($this->selectedDayId) {
            $query->whereHas('activeEvents', fn ($q) =>
                $q->whereHas('division', fn ($q2) =>
                    $q2->where('competition_day_id', $this->selectedDayId)
                )
            );
        }

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
        if (! $this->selectedDayId) {
            return;
        }

        $enrolment = Enrolment::with('activeEvents.division')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $needsWeightForDay = $enrolment->activeEvents->contains(fn ($ee) =>
            $ee->division?->competition_day_id === $this->selectedDayId
            && $ee->division?->weight_class_id !== null
        );

        $weightConfirmedThisSession = ($this->weightConfirmedForDay[$enrolmentId] ?? null) === $this->selectedDayId;

        if ($needsWeightForDay && ! $weightConfirmedThisSession) {
            Notification::make()
                ->title('Weight required before check-in')
                ->body('Enter and confirm weight for weight-bracket events today.')
                ->danger()
                ->send();
            return;
        }

        $dayHasStarted = \App\Models\Division::where('competition_day_id', $this->selectedDayId)
            ->whereIn('status', ['running', 'complete'])
            ->exists();
        if ($dayHasStarted) {
            Notification::make()
                ->title('Events have begun')
                ->body('Some events for this day have already started — verify this competitor has not been called.')
                ->warning()
                ->send();
        }

        // Capture confirmed weight for today's check-in record
        $weightKg = null;
        if ($needsWeightForDay) {
            $weightKg = $enrolment->activeEvents
                ->first(fn ($ee) =>
                    $ee->division?->competition_day_id === $this->selectedDayId
                    && $ee->weight_confirmed_kg !== null
                )?->weight_confirmed_kg;
        }

        app(CheckInService::class)->checkIn(
            $enrolment,
            $this->selectedDayId,
            $weightKg ? (float) $weightKg : null
        );

        Notification::make()->title('Checked in.')->success()->send();

        $this->code = null;
        $this->dispatch('checkin-complete', id: $enrolmentId);
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('cart.enrolments')->find($enrolmentId);
        if (! $enrolment || $enrolment->competition_id !== $this->competition_id) {
            return;
        }

        $cart = $enrolment->cart;
        if (! $cart || $cart->isPaid()) {
            return;
        }

        $platformFee = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
        $totalDue    = $cart->outstandingAmount($platformFee);

        $cart->forceFill([
            'payment_status'      => 'received',
            'payment_amount'      => $totalDue,
            'payment_received_at' => now(),
        ])->save();

        Notification::make()->title('Payment recorded.')->success()->send();
        $this->dispatch('payment-recorded', id: $enrolmentId);
    }

    public function undoCheckIn(int $enrolmentId): void
    {
        if (! $this->selectedDayId) {
            return;
        }

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

        app(CheckInService::class)->undoCheckIn($enrolment, $this->selectedDayId);

        $dayHasStarted = \App\Models\Division::where('competition_day_id', $this->selectedDayId)
            ->whereIn('status', ['running', 'complete'])
            ->exists();

        Notification::make()
            ->title('Check-in reversed.')
            ->when($dayHasStarted, fn ($n) => $n->body('Events have begun for this day — verify this competitor has not yet been called.'))
            ->warning()
            ->send();

        unset($this->weightConfirmedForDay[$enrolmentId]);
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

        $changes = app(CheckInService::class)->applyWeightWithDivisions($enrolment, $weight, $this->selectedDayId);

        if ($changes->isNotEmpty()) {
            $this->pendingWeightConfirm[$enrolmentId] = [
                'weight_kg' => $weight,
                'changes'   => $changes->toArray(),
            ];
        } else {
            unset($this->weights[$enrolmentId]);
            $this->weightConfirmedForDay[$enrolmentId] = $this->selectedDayId;
            Notification::make()->title('Weight confirmed.')->success()->send();
        }
    }

    public function acceptDivisionChange(int $enrolmentId): void
    {
        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
        $this->weightConfirmedForDay[$enrolmentId] = $this->selectedDayId;
        Notification::make()->title('Weight confirmed — division updated.')->success()->send();
    }

    public function ignoreDivisionChange(int $enrolmentId): void
    {
        $pending = $this->pendingWeightConfirm[$enrolmentId] ?? null;
        if ($pending) {
            app(CheckInService::class)->revertDivisionChanges($pending['changes']);
        }
        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
        $this->weightConfirmedForDay[$enrolmentId] = $this->selectedDayId;
        Notification::make()->title('Weight confirmed — original division kept.')->success()->send();
    }

    public function cancelWeightChange(int $enrolmentId): void
    {
        $pending   = $this->pendingWeightConfirm[$enrolmentId] ?? null;
        $enrolment = Enrolment::find($enrolmentId);

        if ($enrolment && $enrolment->competition_id === $this->competition_id) {
            $svc = app(CheckInService::class);
            if ($pending) {
                $svc->revertDivisionChanges($pending['changes']);
            }
            $svc->revertWeight($enrolment, $this->selectedDayId);
        }

        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
    }

    public function cancelEventRegistration(int $enrolmentId): void
    {
        $pending = $this->pendingWeightConfirm[$enrolmentId] ?? null;
        if (! $pending) {
            return;
        }

        app(CheckInService::class)->cancelEventRegistration($pending['changes']);

        unset($this->pendingWeightConfirm[$enrolmentId], $this->weights[$enrolmentId]);
        Notification::make()->title('Removed from event — weight mismatch.')->warning()->send();
    }
}
