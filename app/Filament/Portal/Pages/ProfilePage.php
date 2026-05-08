<?php

namespace App\Filament\Portal\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class ProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'My Profile';
    protected static string  $view            = 'filament.portal.pages.profile-page';
    protected static ?string $slug            = 'profile';

    public ?array $data = [];

    public function mount(): void
    {
        $profile = auth()->user()->competitorProfile;
        $this->form->fill($profile ? $profile->toArray() : []);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Account')
                    ->schema([
                        Placeholder::make('email')
                            ->label('Login email')
                            ->content(fn () => auth()->user()->email),
                    ]),

                Section::make('Personal Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('surname')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(100),

                        DatePicker::make('date_of_birth')
                            ->required()
                            ->maxDate(now()->subYears(5)),

                        Radio::make('gender')
                            ->options(['M' => 'Male', 'F' => 'Female'])
                            ->required()
                            ->inline(),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('height_cm')
                            ->numeric()
                            ->suffix('cm')
                            ->visible(fn () => $this->isUnder15())
                            ->helperText('Required for competitors under 15.')
                            ->minValue(50)
                            ->maxValue(250),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['profile_complete'] = true;

        if (! $this->isUnder15($data['date_of_birth'] ?? null)) {
            $data['height_cm'] = null;
        }

        auth()->user()->competitorProfile()->updateOrCreate(
            ['user_id' => auth()->id()],
            $data
        );

        Notification::make()->title('Profile saved')->success()->send();

        $this->redirect(route('filament.portal.pages.dashboard'));
    }

    private function isUnder15(?string $dob = null): bool
    {
        $dobValue = $dob ?? ($this->data['date_of_birth'] ?? null);
        if (! $dobValue) {
            return false;
        }
        return Carbon::parse($dobValue)->age < 15;
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save profile')
                ->submit('save'),

            \Filament\Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(route('filament.portal.pages.dashboard')),
        ];
    }
}
