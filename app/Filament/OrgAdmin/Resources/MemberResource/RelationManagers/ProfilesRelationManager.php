<?php

namespace App\Filament\OrgAdmin\Resources\MemberResource\RelationManagers;

use App\Filament\OrgAdmin\Resources\CompetitorResource;
use App\Models\CompetitorProfile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'competitorProfiles';

    protected static ?string $title = 'Linked Profiles';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(query: fn ($query, $search) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                    ),

                TextColumn::make('profile_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'family_member' ? 'warning' : 'info')
                    ->formatStateUsing(fn (string $state) => $state === 'family_member' ? 'Family Member' : 'Self'),

                TextColumn::make('date_of_birth')
                    ->label('DOB')
                    ->date(tenant_date_format()),

                TextColumn::make('age')
                    ->label('Age'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (CompetitorProfile $record) => CompetitorResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
