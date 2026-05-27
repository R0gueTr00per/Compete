<?php

namespace App\Filament\OrgAdmin\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Notifications\Notification;
use Filament\Pages\Page;

class OrganisationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'AI Insights';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.org-admin.pages.organisation-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function mount(): void
    {
        $tenant = app('tenant');
        $this->form->fill([
            'ai_context' => $tenant?->ai_context,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI Insights')
                    ->description('Customise how AI analyses your competitions. Provide context about your organisation and sport so insights are more relevant.')
                    ->schema([
                        Textarea::make('ai_context')
                            ->label('Organisation context for AI')
                            ->placeholder('e.g. This is a judo competition organised by Judo Australia. Competitors are graded from white belt (10th kyu) to black belt (1st dan and above).')
                            ->helperText('This text is included in every AI insight prompt. Describe your sport, grading system, or any domain knowledge that helps the AI give better advice.')
                            ->rows(4)
                            ->maxLength(1000),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = app('tenant');
        if (! $tenant) return;

        $data = $this->form->getState();

        $tenant->update(['ai_context' => $data['ai_context'] ?? null]);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Organisation Settings';
    }
}
