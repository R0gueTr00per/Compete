<?php

namespace App\Filament\Portal\Pages;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use App\Notifications\Notification;
use Filament\Pages\Page;

class PreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Preferences';
    protected static string | \UnitEnum | null $navigationGroup = 'Account';
    protected static ?int    $navigationSort  = 190;
    protected string $view            = 'filament.portal.pages.preferences-page';
    protected static ?string $slug            = 'preferences';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'receive_competition_emails' => auth()->user()->receive_competition_emails,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Email notifications')
                    ->schema([
                        Toggle::make('receive_competition_emails')
                            ->label('Receive competition promotional emails')
                            ->helperText('When enabled, you will receive emails when new competitions open for registration and reminder emails for upcoming competitions.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        auth()->user()->update([
            'receive_competition_emails' => $data['receive_competition_emails'],
        ]);

        Notification::make()
            ->success()
            ->title('Preferences saved')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Preferences';
    }
}
