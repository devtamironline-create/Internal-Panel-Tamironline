<?php

namespace Modules\OKR\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OKR\Models\Cycle;

class CycleController extends Controller
{
    public function index()
    {
        $cycles = Cycle::withCount('objectives')
            ->with('creator')
            ->latest()
            ->paginate(10);

        return view('okr::cycles.index', compact('cycles'));
    }

    public function create()
    {
        return view('okr::cycles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:draft,active',
        ]);

        $validated['created_by'] = auth()->id();

        // If activating this cycle, deactivate others
        if ($validated['status'] === 'active') {
            Cycle::where('status', 'active')->update(['status' => 'closed']);
        }

        Cycle::create($validated);

        return redirect()->route('okr.cycles.index')
            ->with('success', 'دوره جدید ایجاد شد');
    }

    public function show(Cycle $cycle)
    {
        $cycle->load(['objectives.keyResults', 'objectives.owner', 'creator']);

        $objectivesByLevel = [
            'organization' => $cycle->objectives->where('level', 'organization'),
            'team' => $cycle->objectives->where('level', 'team'),
            'individual' => $cycle->objectives->where('level', 'individual'),
        ];

        return view('okr::cycles.show', compact('cycle', 'objectivesByLevel'));
    }

    public function edit(Cycle $cycle)
    {
        return view('okr::cycles.edit', compact('cycle'));
    }

    public function update(Request $request, Cycle $cycle)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $cycle->update($validated);

        return redirect()->route('okr.cycles.index')
            ->with('success', 'دوره بروزرسانی شد');
    }

    public function destroy(Cycle $cycle)
    {
        if ($cycle->objectives()->exists()) {
            return back()->with('error', 'امکان حذف دوره با اهداف موجود نیست');
        }

        $cycle->delete();

        return redirect()->route('okr.cycles.index')
            ->with('success', 'دوره حذف شد');
    }

    public function activate(Cycle $cycle)
    {
        // Deactivate other cycles
        Cycle::where('status', 'active')->update(['status' => 'closed']);

        $cycle->update(['status' => 'active']);

        return back()->with('success', 'دوره فعال شد');
    }

    public function close(Cycle $cycle)
    {
        $cycle->update(['status' => 'closed']);

        return back()->with('success', 'دوره بسته شد');
    }
}
