<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Services\EnrolmentService;
use App\Notifications\Notification;
use Filament\Pages\Page;

class CartPage extends Page
{
    protected static ?string $title          = 'Cart';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Cart';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.portal.pages.cart-page';
    protected static ?string $slug            = 'cart';

    public ?int $removingId = null;

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check()) {
            return null;
        }
        $cart = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first();
        $count = $cart?->draftEnrolments()->count() ?? 0;
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    // ── Data ────────────────────────────────────────────────────────────────

    private function getCart(): ?EnrolmentCart
    {
        return EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first();
    }

    public function getCartTotal(): array
    {
        $cart = $this->getCart();
        if (! $cart) {
            return ['items' => [], 'grand_total' => 0.0];
        }

        $cart->load(['competition', 'enrolments.competitor', 'enrolments.competition', 'enrolments.activeEvents.competitionEvent']);

        return app(EnrolmentService::class)->calculateCartTotal($cart);
    }

    // ── Remove from cart ─────────────────────────────────────────────────────

    public function startRemove(int $enrolmentId): void
    {
        $this->removingId = $enrolmentId;
    }

    public function cancelRemove(): void
    {
        $this->removingId = null;
    }

    public function confirmRemove(): void
    {
        $cart = $this->getCart();

        $enrolment = Enrolment::where('id', $this->removingId)
            ->where('status', 'draft')
            ->when($cart, fn ($q) => $q->where('cart_id', $cart->id))
            ->first();

        if ($enrolment) {
            $enrolment->delete();
            Notification::make()->title('Removed from cart.')->success()->send();
        }

        $this->removingId = null;
    }

    // ── Submit ───────────────────────────────────────────────────────────────

    public function submitCart(): void
    {
        $cart = $this->getCart();

        if (! $cart || $cart->draftEnrolments()->count() === 0) {
            Notification::make()->title('Your cart is empty.')->warning()->send();
            return;
        }

        app(EnrolmentService::class)->submitCart($cart);

        Notification::make()->title('Enrolment submitted! Invoice emailed.')->success()->send();
        $this->redirect(route('filament.portal.pages.dashboard'));
    }
}
