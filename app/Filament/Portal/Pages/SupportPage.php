<?php

namespace App\Filament\Portal\Pages;

use App\Mail\SupportRequestMail;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class SupportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-lifebuoy';
    protected static ?string $navigationLabel = 'Support';
    protected static ?string $navigationGroup = 'Account';
    protected static ?int    $navigationSort  = 200;
    protected static string  $view            = 'filament.portal.pages.support-page';
    protected static ?string $slug            = 'support';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'email' => $user->email,
            'name'  => $user->selfProfile?->full_name ?: $user->name,
        ]);
    }

    public function form(Form $form): Form
    {
        $tenant = app('tenant');

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('email')
                            ->label('Your email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->inputMode('email'),

                        TextInput::make('name')
                            ->label('Your name')
                            ->required()
                            ->maxLength(255),

                        Select::make('recipient')
                            ->label('Who do you need help from?')
                            ->required()
                            ->options(array_filter([
                                'platform'  => 'Kompetic Platform Support',
                                'organiser' => $tenant?->contact_email ? ('Organiser — ' . $tenant->name) : null,
                            ]))
                            ->default('platform'),

                        Select::make('area')
                            ->label('Area of concern')
                            ->required()
                            ->options([
                                'Profile / Account'        => 'Profile / Account',
                                'Registration'             => 'Registration',
                                'Competition Information'  => 'Competition Information',
                                'Results & Scoring'        => 'Results & Scoring',
                                'Payments / Fees'          => 'Payments / Fees',
                                'Technical / App Error'    => 'Technical / App Error',
                                'Other'                    => 'Other',
                            ]),

                        Textarea::make('notes')
                            ->label('Description')
                            ->required()
                            ->minLength(10)
                            ->maxLength(5000)
                            ->rows(6)
                            ->placeholder('Please describe your issue or request in as much detail as possible.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data   = $this->form->getState();
        $tenant = app('tenant');

        $toEmail = ($data['recipient'] === 'organiser' && $tenant?->contact_email)
            ? $tenant->contact_email
            : 'support@kompetic.com';

        Mail::to($toEmail)->send(new SupportRequestMail(
            fromName:         $data['name'],
            fromEmail:        $data['email'],
            area:             $data['area'],
            notes:            $data['notes'],
            organisationName: $tenant?->name ?? 'Unknown',
            organisationSlug: $tenant?->slug ?? 'unknown',
        ));

        Notification::make()
            ->success()
            ->title('Request sent')
            ->body('Your support request has been submitted. We\'ll be in touch soon.')
            ->send();

        $this->form->fill([
            'email'     => $data['email'],
            'name'      => $data['name'],
            'recipient' => $data['recipient'],
            'area'      => null,
            'notes'     => null,
        ]);
    }

    public function getTitle(): string
    {
        return 'Support';
    }
}
