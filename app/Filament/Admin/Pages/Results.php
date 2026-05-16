<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\Division;
use App\Services\PdfReportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Results extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationLabel = 'Results';
    protected static string  $view            = 'filament.admin.pages.results';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['competition_administrator', 'system_admin', 'competition_official']);
    }

    #[Url]
    public ?int $competition_id = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $competition = Competition::whereIn('status', ['running', 'complete'])
                ->orderByDesc('competition_date')
                ->first();

            if ($competition) {
                $this->competition_id = $competition->id;
            }
        }
    }

    public function getCompetitions(): array
    {
        return Competition::whereNotIn('status', ['draft'])
            ->orderByDesc('competition_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getResultsData(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return collect();
        }

        return $competition->competitionEvents()
            ->with([
                'divisions'                                          => fn ($q) => $q->whereNotIn('status', ['combined']),
                'divisions.enrolmentEvents'                          => fn ($q) => $q->where('removed', false),
                'divisions.enrolmentEvents.enrolment.competitor.competitorProfile',
                'divisions.enrolmentEvents.result.judgeScores',
            ])
            ->whereNotIn('status', ['combined'])
            ->orderBy('running_order')
            ->get()
            ->filter(fn ($event) => $event->divisions
                ->filter(fn ($div) => $div->enrolmentEvents->contains(fn ($ee) => $ee->result !== null))
                ->isNotEmpty()
            );
    }

    public function downloadPdf(): StreamedResponse
    {
        $competition = Competition::find($this->competition_id);

        if (! $competition) {
            Notification::make()->title('No competition selected.')->warning()->send();
            return response()->streamDownload(fn () => null, 'error.pdf');
        }

        $pdf      = app(PdfReportService::class)->generateCompetitionResults($competition);
        $filename = str($competition->name)->slug() . '-results.pdf';

        return response()->streamDownload(
            fn () => print($pdf),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => (bool) $this->competition_id)
                ->action('downloadPdf'),
        ];
    }
}
