<?php

namespace Modules\OKR\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OKR\Models\Cycle;
use Modules\OKR\Models\Objective;
use Modules\Task\Models\Team;

class ObjectiveController extends Controller
{
    public function index(Request $request)
    {
        $query = Objective::with(['cycle', 'owner', 'team', 'keyResults']);

        if ($cycleId = $request->input('cycle_id')) {
            $query->where('cycle_id', $cycleId);
        }

        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $objectives = $query->latest()->paginate(15);
        $cycles = Cycle::orderBy('start_date', 'desc')->get();

        return view('okr::objectives.index', compact('objectives', 'cycles'));
    }

    public function myObjectives()
    {
        $objectives = Objective::with(['cycle', 'keyResults'])
            ->where('owner_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('okr::objectives.my', compact('objectives'));
    }

    public function create(Request $request)
    {
        $cycles = Cycle::whereIn('status', ['draft', 'active'])->orderBy('start_date', 'desc')->get();
        $teams = Team::all();
        $users = User::staff()->get();
        $parentObjectives = Objective::where('level', 'organization')->get();

        $selectedCycle = $request->input('cycle_id');

        return view('okr::objectives.create', compact('cycles', 'teams', 'users', 'parentObjectives', 'selectedCycle'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:okr_cycles,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|in:organization,team,individual',
            'owner_id' => 'required|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
            'parent_id' => 'nullable|exists:okr_objectives,id',
            'status' => 'required|in:draft,active',
        ]);

        $objective = Objective::create($validated);

        return redirect()->route('okr.objectives.show', $objective)
            ->with('success', 'هدف جدید ایجاد شد');
    }

    public function show(Objective $objective)
    {
        $objective->load(['cycle', 'owner', 'team', 'parent', 'children', 'keyResults.owner', 'keyResults.checkIns']);

        return view('okr::objectives.show', compact('objective'));
    }

    public function edit(Objective $objective)
    {
        $cycles = Cycle::whereIn('status', ['draft', 'active'])->orderBy('start_date', 'desc')->get();
        $teams = Team::all();
        $users = User::staff()->get();
        $parentObjectives = Objective::where('level', 'organization')
            ->where('id', '!=', $objective->id)
            ->get();

        return view('okr::objectives.edit', compact('objective', 'cycles', 'teams', 'users', 'parentObjectives'));
    }

    public function update(Request $request, Objective $objective)
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:okr_cycles,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|in:organization,team,individual',
            'owner_id' => 'required|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
            'parent_id' => 'nullable|exists:okr_objectives,id',
            'status' => 'required|in:draft,active,completed,cancelled',
        ]);

        $objective->update($validated);

        return redirect()->route('okr.objectives.show', $objective)
            ->with('success', 'هدف بروزرسانی شد');
    }

    public function destroy(Objective $objective)
    {
        if ($objective->keyResults()->exists()) {
            return back()->with('error', 'امکان حذف هدف با نتایج کلیدی موجود نیست');
        }

        $objective->delete();

        return redirect()->route('okr.objectives.index')
            ->with('success', 'هدف حذف شد');
    }
}
