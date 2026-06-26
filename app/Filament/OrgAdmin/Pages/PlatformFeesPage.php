<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformFeesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Platform Fees';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?int    $navigationSort  = 7;
    protected string $view            = 'filament.org-admin.pages.platform-fees';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getTitle(): string
    {
        return 'Platform Fees';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Competition::query()
                    ->where('organisation_id', app('tenant')?->id)
                    ->where('is_template', false)
                    ->whereHas('carts', fn (Builder $q) => $q->where('status', 'submitted'))
                    ->withCount(['enrolments as registrations_count' => fn (Builder $q) => $q->whereNotIn('status', ['draft'])])
            )
            ->defaultSort('competition_date', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Competition')
                    ->description(fn (Competition $r) => $r->competition_date->format('d M Y'))
                    ->searchable(),

                TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->sortable(),

                TextColumn::make('platform_fee_total')
                    ->label('Platform fees')
                    ->getStateUsing(fn (Competition $r) => $r->platformFeeTotal())
                    ->formatStateUsing(fn ($state) => (app('tenant')?->currency ?: 'AUD') . ' ' . number_format((float) $state, 2)),

                TextColumn::make('platform_fee_settled_at')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Competition $r) {
                        if ($r->platform_fee_settled_at) {
                            return 'Paid';
                        }
                        return $r->competition_date->isPast() ? 'Outstanding' : 'Pending';
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Paid'        => 'success',
                        'Outstanding' => 'danger',
                        default       => 'gray',
                    })
                    ->description(fn (Competition $r) => $r->platform_fee_settled_at?->format('d M Y')),
            ])
            ->filters([
                TernaryFilter::make('settled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Paid')
                    ->falseLabel('Not yet paid')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('platform_fee_settled_at'),
                        false: fn (Builder $q) => $q->whereNull('platform_fee_settled_at'),
                    ),
            ])
            ->emptyStateHeading('No competitions with registrations yet')
            ->defaultPaginationPageOption(25);
    }
}
