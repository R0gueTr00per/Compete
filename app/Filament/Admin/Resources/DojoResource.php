<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DojoResource\Pages;
use App\Models\Dojo;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class DojoResource extends Resource
{
    protected static ?string $model = Dojo::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $navigationLabel = 'Dojos';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['system_admin', 'competition_administrator']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->isUsed();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->unique(Dojo::class, 'name', ignoreRecord: true),

                Toggle::make('is_active')
                    ->label('Active (shown in competitor enrolment)')
                    ->default(true)
                    ->inline(false),

                Select::make('instructor_id')
                    ->label('Instructor')
                    ->placeholder('None')
                    ->nullable()
                    ->searchable()
                    ->options(
                        User::whereHas('competitorProfile')
                            ->get()
                            ->mapWithKeys(fn (User $u) => [$u->id => $u->getFilamentName()])
                            ->toArray()
                    ),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->defaultGroup(
                Group::make('is_active')
                    ->label('Status')
                    ->getTitleFromRecordUsing(fn (Dojo $record) => $record->is_active ? 'Active' : 'Inactive')
                    ->orderQueryUsing(fn ($query, $direction) => $query->orderBy('is_active', 'desc')->orderBy('name'))
            )
            ->actions([
                EditAction::make(),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Dojo $record) => ! $record->is_active)
                    ->action(fn (Dojo $record) => $record->update(['is_active' => true])),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Dojo $record) => $record->is_active)
                    ->action(fn (Dojo $record) => $record->update(['is_active' => false])),

                DeleteAction::make()
                    ->disabled(fn (Dojo $record) => $record->isUsed())
                    ->tooltip(fn (Dojo $record) => $record->isUsed()
                        ? 'Cannot delete — used in an enrolment. Deactivate instead.'
                        : null
                    ),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDojos::route('/'),
        ];
    }
}
