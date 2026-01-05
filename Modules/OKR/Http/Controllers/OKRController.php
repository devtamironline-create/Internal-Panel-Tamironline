<?php

namespace Modules\OKR\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\OKR\Models\Cycle;
use Modules\OKR\Models\Objective;
use Modules\OKR\Models\KeyResult;

class OKRController extends Controller
{
    public function dashboard()
    {
        $activeCycle = Cycle::where('status', 'active')->first();

        $stats = [
            'total_cycles' => Cycle::count(),
            'active_objectives' => Objective::where('status', 'active')->count(),
            'my_objectives' => Objective::where('owner_id', auth()->id())->where('status', 'active')->count(),
            'my_key_results' => KeyResult::where('owner_id', auth()->id())->count(),
        ];

        $myObjectives = Objective::with(['keyResults', 'cycle'])
            ->where('owner_id', auth()->id())
            ->where('status', 'active')
            ->latest()
            ->take(5)
            ->get();

        $atRiskKeyResults = KeyResult::with(['objective.cycle', 'owner'])
            ->whereIn('status', ['at_risk', 'behind'])
            ->whereHas('objective', fn($q) => $q->where('status', 'active'))
            ->latest()
            ->take(5)
            ->get();

        $recentCycles = Cycle::withCount('objectives')
            ->latest()
            ->take(5)
            ->get();

        return view('okr::dashboard', compact(
            'activeCycle',
            'stats',
            'myObjectives',
            'atRiskKeyResults',
            'recentCycles'
        ));
    }
}
