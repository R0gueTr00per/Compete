<?php

namespace App\Filament\Portal\Pages;

use App\Models\CompetitorProfile;
use App\Models\User;
use Filament\Actions\Action;
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
use App\Notifications\AccountCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ProfilesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Profiles';
    protected static string  $view            = 'filament.portal.pages.profiles-page';
    protected static ?string $slug            = 'profiles';

    // Which profile is open in the edit panel (null = none, 'new' = create form)
    public ?string $editing = null;

    // Graduation form state
    public ?int    $graduating_profile_id = null;
    public ?array  $graduateData          = [];

    // Delete confirmation state
    public ?int $deleting_profile_id = null;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function getProfiles()
    {
        return auth()->user()->ownedProfiles()
            ->orderByRaw("CASE WHEN profile_type = 'self' THEN 0 ELSE 1 END")
            ->orderBy('first_name')
            ->orderBy('surname')
            ->get();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Details')
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

    public function graduateForm(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('info')
                    ->label('')
                    ->content('Moving this profile to its own account will create a new login. They will receive an email to set their password. You will no longer be able to manage this profile.'),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique('users', 'email')
                    ->validationMessages(['unique' => 'An account already exists for this email address.']),
            ])
            ->statePath('graduateData');
    }

    protected function getForms(): array
    {
        return ['form', 'graduateForm'];
    }

    public function startCreate(): void
    {
        $this->form->fill(['profile_type' => 'child']);
        $this->editing = 'new';
    }

    public function startEdit(int $profileId): void
    {
        $profile = $this->authoriseProfile($profileId);
        if (! $profile) {
            return;
        }

        $this->form->fill($profile->toArray());
        $this->editing = (string) $profileId;
    }

    public function cancelEdit(): void
    {
        $this->editing               = null;
        $this->graduating_profile_id = null;
        $this->deleting_profile_id   = null;
    }

    public function saveProfile(): void
    {
        $data = $this->form->getState();

        $profileData = array_intersect_key($data, array_flip([
            'surname', 'first_name', 'date_of_birth', 'gender', 'phone', 'profile_photo',
        ]));

        if (isset($profileData['profile_photo']) && is_array($profileData['profile_photo'])) {
            $profileData['profile_photo'] = array_values($profileData['profile_photo'])[0] ?? null;
        }

        $profileData['profile_complete'] = filled($profileData['surname'] ?? null)
            && filled($profileData['first_name'] ?? null)
            && filled($profileData['date_of_birth'] ?? null)
            && filled($profileData['gender'] ?? null);

        if ($this->editing === 'new') {
            $profileData['owner_user_id']   = auth()->id();
            $profileData['is_active']       = true;
            $profileData['profile_type']    = 'child';
            $profileData['organisation_id'] = app('tenant')?->id;

            CompetitorProfile::create($profileData);
            Notification::make()->title('Profile created.')->success()->send();
        } else {
            $profile = $this->authoriseProfile((int) $this->editing);
            if (! $profile) {
                return;
            }

            unset($profileData['profile_type']); // type cannot be changed after creation

            $profile->update($profileData);
            Notification::make()->title('Profile saved.')->success()->send();
        }

        $this->editing = null;
    }

    public function toggleActive(int $profileId): void
    {
        $profile = $this->authoriseProfile($profileId);
        if (! $profile) {
            return;
        }

        $profile->update(['is_active' => ! $profile->is_active]);

        $label = $profile->is_active ? 'activated' : 'deactivated';
        Notification::make()->title("Profile {$label}.")->success()->send();
    }

    public function startDelete(int $profileId): void
    {
        $profile = $this->authoriseProfile($profileId);
        if (! $profile) {
            return;
        }

        if ($profile->enrolments()->exists()) {
            Notification::make()
                ->title('This profile cannot be deleted because it has competition enrolments on record.')
                ->warning()
                ->send();
            return;
        }

        $this->deleting_profile_id = $profileId;
    }

    public function confirmDelete(): void
    {
        $profile = $this->authoriseProfile($this->deleting_profile_id);
        if (! $profile) {
            return;
        }

        if ($profile->enrolments()->exists()) {
            Notification::make()
                ->title('This profile cannot be deleted because it has competition enrolments on record.')
                ->warning()
                ->send();
            $this->deleting_profile_id = null;
            return;
        }

        $profile->delete();
        $this->deleting_profile_id = null;
        Notification::make()->title('Profile deleted.')->success()->send();
    }

    public function deactivateProfileAction(): Action
    {
        return Action::make('deactivateProfile')
            ->requiresConfirmation()
            ->modalHeading('Deactivate profile?')
            ->modalDescription('This profile will no longer be able to enrol in competitions.')
            ->modalSubmitActionLabel('Deactivate')
            ->color('warning')
            ->action(function (array $arguments) {
                $profile = $this->authoriseProfile((int) $arguments['profileId']);
                if (! $profile) {
                    return;
                }
                $profile->update(['is_active' => false]);
                Notification::make()->title('Profile deactivated.')->success()->send();
            });
    }

    public function startGraduate(int $profileId): void
    {
        $profile = $this->authoriseProfile($profileId);
        if (! $profile || ! $profile->isChild()) {
            return;
        }

        $this->graduating_profile_id = $profileId;
        $this->graduateForm->fill([]);
    }

    public function graduateProfile(): void
    {
        $data    = $this->graduateForm->getState();
        $profile = $this->authoriseProfile($this->graduating_profile_id);

        if (! $profile || ! $profile->isChild()) {
            return;
        }

        DB::transaction(function () use ($data, $profile) {
            $newUser = User::create([
                'email'           => $data['email'],
                'organisation_id' => app('tenant')?->id,
                'password'        => Hash::make(Str::random(32)),
                'status'          => 'active',
            ]);
            $newUser->forceFill(['email_verified_at' => now()])->save();
            $newUser->assignRole('user');

            $profile->update([
                'user_id'       => $newUser->id,
                'owner_user_id' => $newUser->id,
                'profile_type'  => 'self',
            ]);

            $token = Password::broker()->createToken($newUser);
            $newUser->notify(new AccountCreatedNotification($token, app('tenant')));
        });

        $this->graduating_profile_id = null;
        Notification::make()
            ->title('Profile moved to its own account. They will receive an email to set their password.')
            ->success()
            ->send();
    }

    private function authoriseProfile(int $profileId): ?CompetitorProfile
    {
        $profile = CompetitorProfile::find($profileId);

        if (! $profile || $profile->owner_user_id !== auth()->id()) {
            Notification::make()->title('Profile not found.')->danger()->send();
            return null;
        }

        return $profile;
    }
}
