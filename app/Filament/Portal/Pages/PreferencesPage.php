<?php

namespace App\Filament\Portal\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Notifications\Notification;
use Filament\Pages\Page;

class PreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Preferences';
    protected static ?string $navigationGroup = 'Account';
    protected static ?int    $navigationSort  = 190;
    protected static string  $view            = 'filament.portal.pages.preferences-page';
    protected static ?string $slug            = 'preferences';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'receive_competition_emails' => auth()->user()->receive_competition_emails,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
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
