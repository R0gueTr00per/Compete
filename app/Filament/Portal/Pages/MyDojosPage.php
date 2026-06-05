<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Notifications\Notification;
use Filament\Pages\Page;

class MyDojosPage extends Page
{
    protected static ?string $title           = 'My Dojos/Clubs';
    protected static ?string $navigationIcon  = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'My Dojos/Clubs';
    protected static string  $view            = 'filament.portal.pages.my-dojos-page';
    protected static ?string $slug            = 'my-dojos';
    protected static ?int    $navigationSort  = 10;


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

        return Competition::whereIn('status', ['open', 'enrolments_closed', 'check_in', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->whereHas('enrolments', fn ($q) => $q->whereIn('dojo_name', $dojoNames))
            ->with([
                'enrolments' => fn ($q) => $q->whereIn('dojo_name', $dojoNames)
                    ->with(['competitor', 'cart', 'activeEvents.competitionEvent', 'activeEvents.division', 'activeEvents.result']),
            ])
            ->orderBy('competition_date', 'desc')
            ->get();
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('cart')->find($enrolmentId);

        $dojoNames = auth()->user()->instructorOf()->pluck('name');
        if (! $enrolment || ! $dojoNames->contains($enrolment->dojo_name)) {
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
    }
}
