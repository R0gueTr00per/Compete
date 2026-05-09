<?php
namespace App\Services;

use App\Models\Competition;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfReportService
{
    public function generateCompetitionResults(Competition $competition): string
    {
        $competition->load([
            'competitionEvents.divisions.enrolmentEvents.enrolment.competitor.competitorProfile',
            'competitionEvents.divisions.enrolmentEvents.result.judgeScores',
        ]);

        $pdf = Pdf::loadView('pdf.competition-results', compact('competition'))
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
