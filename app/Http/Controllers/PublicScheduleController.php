<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Division;

class PublicScheduleController extends Controller
{
    public function show(Competition $competition)
    {
        $divisions = Division::whereHas('competitionEvent', fn ($q) =>
                $q->where('competition_id', $competition->id)
            )
            ->with(['competitionEvent'])
            ->whereNotIn('status', ['combined'])
            ->orderBy('running_order')
            ->orderBy('code')
            ->get()
            ->groupBy(fn ($div) => $div->competitionEvent->location_label ?? 'Unassigned');

        return view('public.schedule', compact('competition', 'divisions'));
    }
}
