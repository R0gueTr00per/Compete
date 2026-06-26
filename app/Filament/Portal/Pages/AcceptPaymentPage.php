<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class AcceptPaymentPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Accept Payment';
    protected string $view            = 'filament.portal.pages.accept-payment-page';
    protected static ?string $slug            = 'accept-payment';
    protected static ?int    $navigationSort  = 9;

    public ?int $viewingUserId = null;
    public ?int $confirmingCartId = null;

    #[Url]
    public ?string $code = null;

    public string $search = '';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return auth()->check()
            && auth()->user()->instructorOf()->exists()
            && $tenant
            && $tenant->instructorsCanAcceptPayments();
    }

    public function mount(): void
    {
        if ($this->code) {
            $this->lookupCode();
        }
    }

    public function updatedCode(): void
    {
        $this->lookupCode();
    }

    public function clearCode(): void
    {
        $this->code = null;
    }

    public function updatedSearch(): void
    {
        $results = $this->getSearchResults();
        if ($results->count() === 1) {
            $this->viewCart($results->first()->id);
        }
    }

    private function lookupCode(): void
    {
        $this->code = strtoupper(trim($this->code ?? '')) ?: null;
        if (! $this->code) {
            return;
        }

        $enrolment = $this->findEnrolment(fn ($q) => $q->where('checkin_code', $this->code));
        if ($enrolment && $enrolment->cart) {
            $this->viewingUserId = $enrolment->cart->user_id;
        }
    }

    #[Computed]
    public function getSearchResults()
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        // Search by cart so a family or multi-event entry returns one row per cart,
        // not one row per enrolment.
        return EnrolmentCart::where('payment_status', '!=', 'received')
            ->whereHas('competition', fn ($q) => $q->where('organisation_id', app('tenant')?->id))
            ->whereHas('enrolments', fn ($q) => $q
                ->whereNotIn('status', ['withdrawn', 'draft'])
                ->whereHas('competitor', fn ($q2) => $q2
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('surname', 'like', '%' . $this->search . '%')))
            ->with(['competition', 'enrolments.competitor'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    private function findEnrolment(callable $constraint): ?Enrolment
    {
        $query = Enrolment::with('cart')
            ->whereHas('competition', fn ($q) => $q->where('organisation_id', app('tenant')?->id));

        $constraint($query);

        return $query->first();
    }

    public function viewCart(int $cartId): void
    {
        $cart = EnrolmentCart::where('id', $cartId)
            ->whereHas('competition', fn ($q) => $q->where('organisation_id', app('tenant')?->id))
            ->first();
        if (! $cart) {
            return;
        }
        $this->viewingUserId = $cart->user_id;
        $this->search = '';
        $this->code   = null;
    }

    public function viewAccount(int $enrolmentId): void
    {
        $enrolment = $this->findEnrolment(fn ($q) => $q->where('id', $enrolmentId));
        if (! $enrolment || ! $enrolment->cart) {
            return;
        }
        $this->viewingUserId = $enrolment->cart->user_id;
        $this->search = '';
        $this->code   = null;
    }

    public function closeAccount(): void
    {
        $this->viewingUserId = null;
        $this->confirmingCartId = null;
    }

    public function startConfirm(int $cartId): void
    {
        $this->confirmingCartId = $cartId;
    }

    public function cancelConfirm(): void
    {
        $this->confirmingCartId = null;
    }

    /** This payer's outstanding (unpaid) carts for competitions in the current org. */
    public function getViewingCarts()
    {
        if (! $this->viewingUserId) {
            return collect();
        }

        return EnrolmentCart::where('user_id', $this->viewingUserId)
            ->where('payment_status', '!=', 'received')
            ->whereHas('competition', fn ($q) => $q->where('organisation_id', app('tenant')?->id))
            ->whereHas('enrolments', fn ($q) => $q->whereNotIn('status', ['draft', 'withdrawn']))
            ->with([
                'competition',
                'enrolments' => fn ($q) => $q->withTrashed()
                    ->whereNotIn('status', ['draft', 'withdrawn'])
                    ->with(['competitor', 'activeEvents.competitionEvent', 'activeEvents.division']),
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function recordPayment(int $cartId): void
    {
        if (! $this->viewingUserId) {
            return;
        }

        $cart = EnrolmentCart::where('id', $cartId)
            ->where('user_id', $this->viewingUserId)
            ->whereHas('competition', fn ($q) => $q->where('organisation_id', app('tenant')?->id))
            ->first();

        if (! $cart || $cart->isPaid()) {
            return;
        }

        $platformFee = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
        $totalDue    = $cart->outstandingAmount($platformFee);

        $cart->forceFill([
            'payment_status'              => 'received',
            'payment_amount'              => $totalDue,
            'payment_received_at'         => now(),
            'payment_accepted_by_user_id' => auth()->id(),
        ])->save();

        $this->confirmingCartId = null;
        Notification::make()->title('Payment recorded.')->success()->send();
    }
}
