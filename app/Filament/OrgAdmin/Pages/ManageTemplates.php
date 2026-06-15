<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageTemplates extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-bookmark';
    protected static ?string $navigationLabel = 'Competition Templates';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.org-admin.pages.manage-templates';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public function getTitle(): string
    {
        return 'Competition Templates';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Competition::query()
                    ->templates()
                    ->where('organisation_id', app('tenant')?->id)
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('template_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date(tenant_date_format())
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->actions([
                Action::make('toggleActive')
                    ->label(fn (Competition $record) => $record->template_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Competition $record) => $record->template_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Competition $record) => $record->template_active ? 'warning' : 'success')
                    ->action(function (Competition $record) {
                        $record->update(['template_active' => ! $record->template_active]);
                        Notification::make()
                            ->success()
                            ->title($record->template_active ? 'Template activated' : 'Template deactivated')
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete template?')
                    ->modalDescription(fn (Competition $record) => 'Permanently delete "' . $record->name . '"? This cannot be undone.')
                    ->action(function (Competition $record) {
                        $name = $record->name;
                        $record->forceDelete();
                        Notification::make()
                            ->success()
                            ->title("Template \"{$name}\" deleted")
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No templates yet')
            ->emptyStateDescription('Use "Save as Template" from the competition list to create one.');
    }
}
