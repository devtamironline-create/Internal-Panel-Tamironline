<?php

namespace Modules\OKR\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OKR\Models\Cycle;
use Morilog\Jalali\Jalalian;

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
            'start_date' => 'required|string',
            'end_date' => 'required|string',
            'status' => 'required|in:draft,active',
        ]);

        // Convert Jalali dates to Gregorian
        try {
            $validated['start_date'] = Jalalian::fromFormat('Y/m/d', $this->persianToLatin($validated['start_date']))->toCarbon();
            $validated['end_date'] = Jalalian::fromFormat('Y/m/d', $this->persianToLatin($validated['end_date']))->toCarbon();
        } catch (\Exception $e) {
            return back()->withErrors(['start_date' => 'فرمت تاریخ نامعتبر است'])->withInput();
        }

        if ($validated['end_date'] <= $validated['start_date']) {
            return back()->withErrors(['end_date' => 'تاریخ پایان باید بعد از تاریخ شروع باشد'])->withInput();
        }

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
            'start_date' => 'required|string',
            'end_date' => 'required|string',
        ]);

        // Convert Jalali dates to Gregorian
        try {
            $validated['start_date'] = Jalalian::fromFormat('Y/m/d', $this->persianToLatin($validated['start_date']))->toCarbon();
            $validated['end_date'] = Jalalian::fromFormat('Y/m/d', $this->persianToLatin($validated['end_date']))->toCarbon();
        } catch (\Exception $e) {
            return back()->withErrors(['start_date' => 'فرمت تاریخ نامعتبر است'])->withInput();
        }

        if ($validated['end_date'] <= $validated['start_date']) {
            return back()->withErrors(['end_date' => 'تاریخ پایان باید بعد از تاریخ شروع باشد'])->withInput();
        }

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

    protected function persianToLatin(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($persian, $latin, $string);
        $string = str_replace($arabic, $latin, $string);

        return $string;
    }
}
