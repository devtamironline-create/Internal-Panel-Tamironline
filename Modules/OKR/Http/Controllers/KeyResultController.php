<?php

namespace Modules\OKR\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OKR\Models\KeyResult;
use Modules\OKR\Models\Objective;
use Modules\OKR\Models\CheckIn;

class KeyResultController extends Controller
{
    public function create(Request $request)
    {
        $objectiveId = $request->input('objective_id');
        $objective = Objective::findOrFail($objectiveId);
        $users = User::staff()->get();

        return view('okr::key-results.create', compact('objective', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'objective_id' => 'required|exists:okr_objectives,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metric_type' => 'required|in:number,percentage,currency,boolean',
            'start_value' => 'required|numeric',
            'target_value' => 'required|numeric',
            'unit' => 'nullable|string|max:50',
            'owner_id' => 'required|exists:users,id',
        ]);

        $validated['current_value'] = $validated['start_value'];
        $validated['status'] = 'not_started';
        $validated['progress'] = 0;

        $keyResult = KeyResult::create($validated);

        return redirect()->route('okr.objectives.show', $validated['objective_id'])
            ->with('success', 'نتیجه کلیدی ایجاد شد');
    }

    public function show(KeyResult $keyResult)
    {
        $keyResult->load(['objective.cycle', 'owner', 'checkIns.user']);

        return view('okr::key-results.show', compact('keyResult'));
    }

    public function edit(KeyResult $keyResult)
    {
        $users = User::staff()->get();

        return view('okr::key-results.edit', compact('keyResult', 'users'));
    }

    public function update(Request $request, KeyResult $keyResult)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metric_type' => 'required|in:number,percentage,currency,boolean',
            'start_value' => 'required|numeric',
            'target_value' => 'required|numeric',
            'unit' => 'nullable|string|max:50',
            'owner_id' => 'required|exists:users,id',
        ]);

        $keyResult->update($validated);
        $keyResult->updateProgress();

        return redirect()->route('okr.objectives.show', $keyResult->objective_id)
            ->with('success', 'نتیجه کلیدی بروزرسانی شد');
    }

    public function destroy(KeyResult $keyResult)
    {
        $objectiveId = $keyResult->objective_id;
        $keyResult->delete();

        // Recalculate objective progress
        $keyResult->objective->updateProgress();

        return redirect()->route('okr.objectives.show', $objectiveId)
            ->with('success', 'نتیجه کلیدی حذف شد');
    }

    public function checkIn(Request $request, KeyResult $keyResult)
    {
        $validated = $request->validate([
            'new_value' => 'required|numeric',
            'confidence' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string',
            'blockers' => 'nullable|string',
        ]);

        // Create check-in record
        CheckIn::create([
            'key_result_id' => $keyResult->id,
            'user_id' => auth()->id(),
            'previous_value' => $keyResult->current_value,
            'new_value' => $validated['new_value'],
            'confidence' => $validated['confidence'],
            'note' => $validated['note'],
            'blockers' => $validated['blockers'],
        ]);

        // Update key result
        $keyResult->current_value = $validated['new_value'];
        if (isset($validated['confidence'])) {
            $keyResult->confidence = $validated['confidence'];
        }
        $keyResult->updateProgress();

        return back()->with('success', 'چک‌این ثبت شد');
    }
}
