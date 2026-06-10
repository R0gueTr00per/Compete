<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Division;

class PublicScheduleController extends Controller
{
    public function show(Competition $competition)
    {
        $tenant = app('tenant');
        if ($tenant && $competition->organisation_id !== $tenant->id) {
            abort(404);
        }

        if (! $competition->isPublicScheduleAvailable()) {
            return redirect('/portal');
        }

        $divisions = Division::whereHas('competitionEvent', fn ($q) =>
                $q->where('competition_id', $competition->id)
            )
            ->with([
                'competitionEvent',
                'results' => fn ($q) => $q->orderBy('placement'),
                'results.enrolmentEvent.competitor',
            ])
            ->whereNotIn('status', ['combined'])
            ->whereNotNull('location_label')
            ->orderBy('running_order')
            ->orderBy('code')
            ->get()
            ->groupBy(fn ($div) => $div->location_label ?? 'Unassigned');

        $breaks = $competition->breaks;

        return view('public.schedule', compact('competition', 'divisions', 'breaks'));
    }
}
