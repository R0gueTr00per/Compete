<?php

namespace App\Filament\Admin\Resources\UserResource\RelationManagers;

use App\Filament\Admin\Resources\CompetitorResource;
use App\Models\CompetitorProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'ownedProfiles';
    protected static ?string $title       = 'Profiles';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile Details')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('surname')
                        ->required()
                        ->maxLength(100),

                    DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()->subYears(1)),

                    Radio::make('gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required()
                        ->inline(),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),

                    Radio::make('profile_type')
                        ->options(['self' => 'Self', 'child' => 'Child'])
                        ->required()
                        ->inline(),
                ]),
        ]);
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
                    ->color(fn (string $state) => $state === 'child' ? 'warning' : 'info')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('date_of_birth')
                    ->label('DOB')
                    ->date('d M Y'),

                TextColumn::make('age')
                    ->label('Age'),

                TextColumn::make('gender')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'M' ? 'Male' : 'Female')
                    ->color(fn (string $state) => $state === 'M' ? 'info' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['owner_user_id']   = $this->getOwnerRecord()->id;
                        $data['is_active']       = true;
                        $data['profile_complete'] = filled($data['first_name'] ?? null)
                            && filled($data['surname'] ?? null)
                            && filled($data['date_of_birth'] ?? null)
                            && filled($data['gender'] ?? null);
                        return $data;
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit Profile')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (CompetitorProfile $record) => CompetitorResource::getUrl('edit', ['record' => $record])),

                ActionGroup::make([
                    Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (CompetitorProfile $record) => $record->is_active)
                        ->action(function (CompetitorProfile $record) {
                            $record->update(['is_active' => false]);
                            Notification::make()->title('Profile deactivated.')->success()->send();
                        }),

                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (CompetitorProfile $record) => ! $record->is_active)
                        ->action(function (CompetitorProfile $record) {
                            $record->update(['is_active' => true]);
                            Notification::make()->title('Profile activated.')->success()->send();
                        }),

                    DeleteAction::make()
                        ->visible(fn (CompetitorProfile $record) => $record->enrolments()->doesntExist())
                        ->before(function (CompetitorProfile $record) {
                            if ($record->enrolments()->exists()) {
                                Notification::make()
                                    ->title('Cannot delete a profile with enrolment history. Deactivate it instead.')
                                    ->danger()
                                    ->send();
                                $this->halt();
                            }
                        }),
                ]),
            ])
            ->bulkActions([]);
    }
}
