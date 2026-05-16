<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Services\PdfReportService;
use Illuminate\Http\Request;

class ResultsPdfController extends Controller
{
    public function show(Request $request, PdfReportService $pdfService)
    {
        abort_unless(
            auth()->user()?->hasRole(['competition_administrator', 'system_admin', 'competition_official']),
            403
        );

        $competition = Competition::findOrFail($request->integer('competition_id'));

        $filters = [
            'only_placings'  => (bool) $request->integer('only_placings', 0),
            'search'         => trim($request->string('search', '')),
            'selected_event' => $request->integer('selected_event') ?: null,
            'selected_dojo'  => trim($request->string('selected_dojo', '')),
        ];

        $pdf      = $pdfService->generateCompetitionResults($competition, $filters);
        $filename = str($competition->name)->slug() . '-results.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
