<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AnnualFeeRemindersWidget;
use App\Models\Competition;
use App\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrgBillingReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Billing';
    protected static string | \UnitEnum | null $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 3;
    protected string $view            = 'filament.admin.pages.org-billing-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public function getTitle(): string
    {
        return 'Billing';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnnualFeeRemindersWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustBilling')
                ->label('Adjust competition billing')
                ->icon('heroicon-o-adjustments-horizontal')
                ->form([
                    Select::make('competition_id')
                        ->label('Competition')
                        ->options(fn () => Competition::where('is_template', false)
                            ->with('organisation')
                            ->orderByDesc('competition_date')
                            ->get()
                            ->mapWithKeys(fn (Competition $c) => [
                                $c->id => "{$c->name} — {$c->organisation?->name} ({$c->competition_date->format('d M Y')})",
                            ]))
                        ->searchable()
                        ->required(),

                    Select::make('override')
                        ->label('Billing status')
                        ->options([
                            ''         => 'Automatic (default — bill once the date has passed)',
                            'forced'   => 'Force into the unpaid list now',
                            'excluded' => 'Cancel — exclude from the unpaid list',
                        ])
                        ->default('')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $competition = Competition::find($data['competition_id']);
                    if (! $competition) {
                        return;
                    }
                    $competition->update(['platform_fee_billing_override' => $data['override'] ?: null]);
                    Notification::make()->title('Billing status updated')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Platform Fees')
            ->query(
                Competition::query()
                    ->where('is_template', false)
                    ->billable()
                    ->whereHas('carts', fn (Builder $q) => $q->where('status', 'submitted'))
                    ->with('organisation')
                    ->withCount(['enrolments as registrations_count' => fn (Builder $q) => $q->whereNotIn('status', ['draft'])])
            )
            ->defaultSort('competition_date', 'desc')
            ->columns([
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Competition')
                    ->description(fn (Competition $r) => $r->competition_date->format('d M Y'))
                    ->searchable(),

                TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->sortable(),

                TextColumn::make('unpaid_platform_fee')
                    ->label('Unpaid platform fee')
                    ->getStateUsing(fn (Competition $r) => $r->unpaidPlatformFeeTotal())
                    ->formatStateUsing(fn ($state, Competition $r) => ($r->organisation?->currency ?: 'AUD') . ' ' . number_format((float) $state, 2)),

                TextColumn::make('platform_fee_settled_at')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Competition $r) => $r->platform_fee_settled_at ? 'Settled' : 'Unsettled')
                    ->color(fn (Competition $r) => $r->platform_fee_settled_at ? 'success' : 'warning')
                    ->description(fn (Competition $r) => $r->platform_fee_settled_at?->format('d M Y')),
            ])
            ->filters([
                SelectFilter::make('organisation')
                    ->label('Organisation')
                    ->relationship('organisation', 'name')
                    ->searchable(),

                TernaryFilter::make('settled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Settled')
                    ->falseLabel('Unsettled')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('platform_fee_settled_at'),
                        false: fn (Builder $q) => $q->whereNull('platform_fee_settled_at'),
                    ),
            ])
            ->actions([
                Action::make('markSettled')
                    ->label('Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Competition $r) => ! $r->platform_fee_settled_at && $r->unpaidPlatformFeeTotal() > 0)
                    ->action(function (Competition $r) {
                        $r->update(['platform_fee_settled_at' => now()]);
                        Notification::make()->title('Platform fees marked as received')->success()->send();
                    }),

                Action::make('unmarkSettled')
                    ->label('Unmark')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Competition $r) => (bool) $r->platform_fee_settled_at)
                    ->action(fn (Competition $r) => $r->update(['platform_fee_settled_at' => null])),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Removes this competition from the unpaid list (e.g. if the date changed). Use "Adjust competition billing" above to bring it back.')
                    ->visible(fn (Competition $r) => ! $r->platform_fee_settled_at)
                    ->action(function (Competition $r) {
                        $r->update(['platform_fee_billing_override' => 'excluded']);
                        Notification::make()->title('Removed from the unpaid list')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No completed competitions with platform fees yet')
            ->defaultPaginationPageOption(25);
    }
}
