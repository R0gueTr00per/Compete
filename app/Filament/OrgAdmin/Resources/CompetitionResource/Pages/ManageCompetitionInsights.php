<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Mail\CompetitionInsightsMail;
use App\Models\CompetitionTask;
use App\Services\CompetitionInsightService;
use Filament\Actions\Action;
use App\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Mail;

class ManageCompetitionInsights extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static string $view = 'filament.org-admin.pages.competition-insights';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            in_array($this->record->status, ['planning', 'open', 'closed', 'check_in', 'running', 'complete']),
            403
        );
    }

    public static function getNavigationLabel(): string
    {
        return 'Insights';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — AI Insights';
    }

    public function getBreadcrumb(): string
    {
        return 'Insights';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInsights')
                ->label(fn () => $this->getRecord()->insight ? 'Refresh Insights' : 'Generate Insights')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation(fn () => $this->getRecord()->insight !== null)
                ->modalHeading('Refresh AI Insights')
                ->modalDescription('This will regenerate insights using the latest competition data.')
                ->modalWidth('sm')
                ->modalSubmitActionLabel('Refresh')
                ->action(function () {
                    if (! config('services.google_ai.api_key')) {
                        Notification::make()
                            ->danger()
                            ->title('AI not configured')
                            ->body('GOOGLE_AI_API_KEY is not set.')
                            ->send();
                        return;
                    }

                    try {
                        app(CompetitionInsightService::class)->generate($this->getRecord());

                        Notification::make()
                            ->success()
                            ->title('Insights generated')
                            ->send();

                        $this->record->refresh();
                    } catch (\Throwable) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to generate insights')
                            ->body('Please try again.')
                            ->send();
                    }
                }),

            Action::make('emailInsights')
                ->label('Email Insights')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->insight !== null)
                ->requiresConfirmation()
                ->modalHeading('Email insights')
                ->modalDescription(fn () => 'Send these insights to ' . auth()->user()->email . '?')
                ->modalWidth('sm')
                ->modalSubmitActionLabel('Send')
                ->action(function () {
                    $insight = $this->getRecord()->insight;
                    if (! $insight) return;

                    try {
                        Mail::to(auth()->user()->email)
                            ->send(new CompetitionInsightsMail($this->getRecord(), $insight));

                        Notification::make()
                            ->success()
                            ->title('Insights emailed to ' . auth()->user()->email)
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to send email')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    public bool $creatingTaskFromHeading = false;
    public array $newTaskFromHeading = ['title' => '', 'notes' => ''];

    public function openCreateTaskModal(string $title, string $body): void
    {
        $this->newTaskFromHeading = [
            'title' => $title,
            'notes' => strip_tags(\Illuminate\Support\Str::markdown($body)),
        ];
        $this->creatingTaskFromHeading = true;
    }

    public function saveTaskFromHeading(): void
    {
        $title = trim($this->newTaskFromHeading['title'] ?? '');
        if ($title === '') return;

        $maxOrder = $this->getRecord()->tasks()->max('sort_order') ?? -1;

        CompetitionTask::create([
            'competition_id' => $this->getRecord()->id,
            'title'          => $title,
            'notes'          => trim($this->newTaskFromHeading['notes'] ?? '') ?: null,
            'sort_order'     => $maxOrder + 1,
        ]);

        $this->creatingTaskFromHeading = false;
        $this->newTaskFromHeading = ['title' => '', 'notes' => ''];

        Notification::make()->success()->title('Task created.')->send();
    }

    public function cancelTaskFromHeading(): void
    {
        $this->creatingTaskFromHeading = false;
        $this->newTaskFromHeading = ['title' => '', 'notes' => ''];
    }

    protected function getViewData(): array
    {
        $this->record->load('insight');

        return [
            'competition' => $this->getRecord(),
            'insight'     => $this->getRecord()->insight,
        ];
    }
}
