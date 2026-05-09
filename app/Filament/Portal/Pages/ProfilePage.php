<?php

namespace App\Filament\Portal\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
        $user    = auth()->user();
        $profile = $user->competitorProfile;

        $this->form->fill(array_merge(
            $profile ? $profile->toArray() : [],
            ['timezone' => $user->timezone ?? 'Australia/Sydney'],
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('email')
                            ->label('Login email')
                            ->content(fn () => auth()->user()->email),

                        Select::make('timezone')
                            ->label('Timezone')
                            ->options(function () {
                                $zones = \DateTimeZone::listIdentifiers();
                                return array_combine($zones, $zones);
                            })
                            ->default('Australia/Sydney')
                            ->searchable()
                            ->required(),
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

        auth()->user()->update(['timezone' => $data['timezone'] ?? 'Australia/Sydney']);

        $profileData                     = $data;
        $profileData['profile_complete'] = true;
        unset($profileData['timezone']);

        if (! $this->isUnder15($profileData['date_of_birth'] ?? null)) {
            $profileData['height_cm'] = null;
        }

        auth()->user()->competitorProfile()->updateOrCreate(
            ['user_id' => auth()->id()],
            $profileData
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
