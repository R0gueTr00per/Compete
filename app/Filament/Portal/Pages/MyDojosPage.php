<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Notifications\Notification;
use Filament\Pages\Page;

class MyDojosPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-building-storefront';

    public function getTitle(): string
    {
        return 'My ' . tenant_group_name_plural();
    }

    public static function getNavigationLabel(): string
    {
        return 'My ' . tenant_group_name_plural();
    }
    protected static string  $view            = 'filament.portal.pages.my-dojos-page';
    protected static ?string $slug            = 'my-dojos';
    protected static ?int    $navigationSort  = 10;

    public ?int $viewingCartId = null;

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

        return Competition::whereIn('status', ['open', 'enrolments_closed', 'check_in', 'running', 'complete'])
            ->where('organisation_id', app('tenant')?->id)
            ->whereHas('enrolments', fn ($q) => $q->whereIn('dojo_name', $dojoNames))
            ->with([
                'enrolments' => fn ($q) => $q->whereIn('dojo_name', $dojoNames)
                    ->with(['competitor', 'cart', 'activeEvents.competitionEvent', 'activeEvents.division', 'activeEvents.result']),
            ])
            ->orderBy('competition_date', 'desc')
            ->get();
    }

    public function viewAccount(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('cart')->find($enrolmentId);
        $dojoNames = auth()->user()->instructorOf()->pluck('name');
        if (! $enrolment || ! $dojoNames->contains($enrolment->dojo_name)) {
            return;
        }
        $this->viewingCartId = $enrolment->cart_id;
    }

    public function closeAccount(): void
    {
        $this->viewingCartId = null;
    }

    public function getViewingCart(): ?EnrolmentCart
    {
        if (! $this->viewingCartId) {
            return null;
        }
        return EnrolmentCart::with([
            'enrolments' => fn ($q) => $q->withTrashed()
                ->whereNotIn('status', ['draft', 'withdrawn'])
                ->with(['competitor', 'activeEvents.competitionEvent', 'activeEvents.division']),
        ])->find($this->viewingCartId);
    }

    public function recordPayment(int $enrolmentId): void
    {
        $enrolment = Enrolment::with('cart')->find($enrolmentId);

        $dojoNames = auth()->user()->instructorOf()->pluck('name');
        if (! $enrolment || ! $dojoNames->contains($enrolment->dojo_name)) {
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

        $this->viewingCartId = null;

        Notification::make()->title('Payment recorded.')->success()->send();
    }
}
