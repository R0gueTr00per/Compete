<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Enrolment;
use App\Notifications\Notification;
use Filament\Pages\Page;

class RefundRequestsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Refund Requests';
    protected static ?string $navigationGroup = 'Registrations';
    protected static ?int    $navigationSort  = 20;
    protected static string  $view            = 'filament.org-admin.pages.refund-requests';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && static::hasPendingRefunds();
    }

    private static function hasPendingRefunds(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) {
            return false;
        }
        return Enrolment::whereHas('competition', fn ($q) => $q->where('organisation_id', $tenant->id))
            ->where('refund_requested', true)
            ->where('status', 'withdrawn')
            ->exists();
    }

    public function getEnrolments()
    {
        $tenant = app('tenant');

        return Enrolment::whereHas('competition', fn ($q) => $q->where('organisation_id', $tenant->id))
            ->where('refund_requested', true)
            ->where('status', 'withdrawn')
            ->with(['competitor', 'competition'])
            ->orderByDesc('withdrawn_at')
            ->get();
    }

    public function markResolved(int $enrolmentId): void
    {
        $enrolment = Enrolment::findOrFail($enrolmentId);

        $tenant = app('tenant');
        if ($enrolment->competition->organisation_id !== $tenant->id) {
            abort(403);
        }

        $enrolment->forceFill(['refund_requested' => false])->save();

        Notification::make()->title('Refund request marked as resolved.')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Refund Requests';
    }
}
