<?php

namespace App\Filament\OrgAdmin\Pages;

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

class Support extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-lifebuoy';
    protected static ?string $navigationLabel = 'Support';
    protected static ?string $navigationGroup = 'Account';
    protected static ?int    $navigationSort  = 200;
    protected static string  $view            = 'filament.org-admin.pages.support';

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
        return $form
            ->schema([
                Section::make('Contact Kompetic Support')
                    ->description('Use this form to contact the Kompetic platform support team for help with billing, technical issues, or platform questions.')
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

                        Select::make('area')
                            ->label('Area of concern')
                            ->required()
                            ->options([
                                'Competition Setup'    => 'Competition Setup',
                                'Enrolments'           => 'Enrolments',
                                'Users & Permissions'  => 'Users & Permissions',
                                'Scoring & Results'    => 'Scoring & Results',
                                'Reporting'            => 'Reporting',
                                'Billing / Payments'   => 'Billing / Payments',
                                'Technical / App Error'=> 'Technical / App Error',
                                'Account / Profile'    => 'Account / Profile',
                                'Other'                => 'Other',
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

        Mail::to('support@kompetic.com')->send(new SupportRequestMail(
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
            'email' => $data['email'],
            'name'  => $data['name'],
            'area'  => null,
            'notes' => null,
        ]);
    }

    public function getTitle(): string
    {
        return 'Support';
    }
}
