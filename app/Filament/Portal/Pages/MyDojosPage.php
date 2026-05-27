<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Notifications\Notification;
use Filament\Pages\Page;

class MyDojosPage extends Page
{
    protected static ?string $title           = 'My Dojos';
    protected static ?string $navigationIcon  = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'My Dojos';
    protected static string  $view            = 'filament.portal.pages.my-dojos-page';
    protected static ?string $slug            = 'my-dojos';
    protected static ?int    $navigationSort  = 10;

    public array $paymentAmounts = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->instructorOf()->exists();
    }

    public function getDojos()
    {
        return auth()->user()->instructorOf()->orderBy('name')->get();
    }

    public function getCompetitions()
    {
        $dojos = $this->getDojos();
        if ($dojos->isEmpty()) return collect();

        $dojoNames = $dojos->pluck('name');

        return Competition::whereIn('status', ['open', 'closed', 'check_in', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->whereHas('enrolments', fn ($q) => $q->whereIn('dojo_name', $dojoNames))
            ->with([
                'enrolments' => fn ($q) => $q->whereIn('dojo_name', $dojoNames)
                    ->with(['competitor', 'activeEvents.competitionEvent', 'activeEvents.division', 'activeEvents.result']),
            ])
            ->orderBy('competition_date', 'desc')
            ->get();
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::find($enrolmentId);

        $dojoNames = auth()->user()->instructorOf()->pluck('name');
        if (! $enrolment || ! $dojoNames->contains($enrolment->dojo_name)) {
            return;
        }

        $amount = isset($this->paymentAmounts[$enrolmentId])
            ? (float) $this->paymentAmounts[$enrolmentId]
            : null;

        $enrolment->forceFill([
            'payment_status' => 'received',
            'payment_amount' => $amount ?? $enrolment->fee_calculated,
        ])->save();

        unset($this->paymentAmounts[$enrolmentId]);

        Notification::make()->title('Payment recorded.')->success()->send();
    }
}
