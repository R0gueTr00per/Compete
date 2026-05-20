<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;

use App\Models\OfficialRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfficialsRelationManager extends RelationManager
{
    protected static string $relationship = 'officials';
    protected static ?string $title = 'Officials';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('user_id')
                ->label('User')
                ->required()
                ->searchable()
                ->options(
                    User::whereHas('ownedProfiles')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->getFilamentName()])
                        ->toArray()
                )
                ->unique(
                    table: 'competition_officials',
                    column: 'user_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, RelationManager $livewire) =>
                        $rule->where('competition_id', $livewire->ownerRecord->id),
                ),

            Select::make('official_role_id')
                ->label('Role')
                ->required()
                ->options(fn (RelationManager $livewire) => OfficialRole::where('organisation_id', $livewire->ownerRecord->organisation_id)->orderBy('name')->pluck('name', 'id')->toArray()),

            Select::make('competition_location_id')
                ->label('Location')
                ->placeholder('None')
                ->nullable()
                ->options(
                    fn (RelationManager $livewire) => $livewire->ownerRecord
                        ->competitionLocations()
                        ->pluck('name', 'id')
                        ->toArray()
                ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'officialRole', 'location']))
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->getStateUsing(fn ($record) => $record->user?->getFilamentName() ?? '—'),

                TextColumn::make('officialRole.name')
                    ->label('Role'),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('—'),
            ])
            ->paginated(false)
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
