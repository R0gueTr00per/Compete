<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\EnrolmentCart;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?string $navigationGroup = 'Registrations';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.org-admin.pages.transactions';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getTotals(): array
    {
        $base = \App\Models\Enrolment::query()
            ->whereNotIn('status', ['draft'])
            ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id));

        return [
            'total_fees'  => (float) (clone $base)->whereNotIn('status', ['withdrawn'])->sum('fee_calculated'),
            'total_paid'  => (float) (clone $base)->where('payment_status', 'received')->sum('payment_amount'),
            'outstanding' => (float) (clone $base)->where('payment_status', '!=', 'received')->whereNotIn('status', ['withdrawn'])->sum('fee_calculated'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EnrolmentCart::query()
                    ->whereHas('enrolments', fn (Builder $q) => $q->whereNotIn('status', ['draft']))
                    ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id))
                    ->with(['competition', 'user', 'enrolments.competitor', 'enrolments.activeEvents.competitionEvent', 'enrolments.activeEvents.division'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('submitted_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? tenant_date($state) : '—')
                    ->sortable(),

                TextColumn::make('competition.name')
                    ->label('Competition')
                    ->sortable()
                    ->searchable()
                    ->description(fn (EnrolmentCart $r) => $r->competition ? tenant_date($r->competition->competition_date) : null),

                TextColumn::make('user.email')
                    ->label('Registered by')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('competitors')
                    ->label('Competitors')
                    ->getStateUsing(fn (EnrolmentCart $r) => $r->enrolments
                        ->whereNotIn('status', ['draft'])
                        ->map(fn ($e) => $e->competitor?->full_name)
                        ->filter()
                        ->join(', '))
                    ->wrap(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state !== null ? tenant_money($state) : '—')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(function (EnrolmentCart $r) {
                        $active = $r->enrolments->whereNotIn('status', ['withdrawn', 'draft']);
                        if ($active->isEmpty()) return 'n/a';
                        $paid = $active->where('payment_status', 'received')->count();
                        return match(true) {
                            $paid === $active->count() => 'paid',
                            $paid > 0                  => 'partial',
                            default                    => 'outstanding',
                        };
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'paid'        => 'Paid',
                        'partial'     => 'Partial',
                        'outstanding' => 'Outstanding',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'paid'    => 'success',
                        'partial' => 'warning',
                        default   => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('competition_id')
                    ->label('Competition')
                    ->options(fn () => Competition::where('organisation_id', app('tenant')?->id)
                        ->orderByDesc('competition_date')
                        ->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(fn (EnrolmentCart $r) => 'Transaction — ' . $r->competition?->name)
                    ->modalContent(fn (EnrolmentCart $r) => view('filament.org-admin.pages.transaction-detail', ['cart' => $r]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('No registrations found')
            ->emptyStateDescription('Registrations will appear here once competitors have enrolled.')
            ->defaultPaginationPageOption(25);
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }
}
