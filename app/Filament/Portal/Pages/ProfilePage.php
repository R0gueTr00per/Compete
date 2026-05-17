<?php

namespace App\Filament\Portal\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

        $data = $profile ? $profile->toArray() : [];
        $this->form->fill($data);
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
                    ]),

                Section::make('Profile Photo')
                    ->schema([
                        FileUpload::make('profile_photo')
                            ->label('Photo')
                            ->image()
                            ->imagePreviewHeight('200')
                            ->disk('public')
                            ->directory('profile-photos')
                            ->visibility('public')
                            ->maxSize(2048),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $profileData = array_intersect_key($data, array_flip([
            'surname', 'first_name', 'date_of_birth', 'gender', 'phone', 'profile_photo',
        ]));

        // FileUpload returns an array of paths; unwrap to a single path
        if (isset($profileData['profile_photo']) && is_array($profileData['profile_photo'])) {
            $profileData['profile_photo'] = array_values($profileData['profile_photo'])[0] ?? null;
        }

        $profileData['profile_complete'] = true;

        auth()->user()->competitorProfile()->updateOrCreate(
            ['user_id' => auth()->id()],
            $profileData
        );

        Notification::make()->title('Profile saved')->success()->send();

        $this->redirect(route('filament.portal.pages.dashboard'));
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
