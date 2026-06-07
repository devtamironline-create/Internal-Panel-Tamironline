<?php

namespace Modules\CustomerApp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\Holiday;

/**
 * مدیریت تعطیلات برای picker اپ موبایل + استفاده در گزارشات داخلی.
 */
class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = $request->integer('year') ?: (int) now()->format('Y');
        $type = $request->query('type');

        $query = Holiday::query()->orderBy('date');
        if ($year > 0) {
            $query->whereYear('date', $year);
        }
        if ($type && array_key_exists($type, Holiday::TYPE_LABELS)) {
            $query->where('type', $type);
        }

        $items = $query->paginate(40)->withQueryString();

        return view('customerapp::admin.holidays.index', [
            'items' => $items,
            'year' => $year,
            'type' => $type,
            'types' => Holiday::TYPE_LABELS,
        ]);
    }

    public function create(): View
    {
        return view('customerapp::admin.holidays.create', [
            'types' => Holiday::TYPE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            Holiday::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withInput()->withErrors(['date' => 'این تاریخ از قبل ثبت شده است.']);
        }

        return redirect()->route('customer-app.holidays.index')->with('success', 'تعطیلی اضافه شد.');
    }

    public function edit(Holiday $holiday): View
    {
        return view('customerapp::admin.holidays.edit', [
            'item' => $holiday,
            'types' => Holiday::TYPE_LABELS,
        ]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $this->validateData($request, $holiday->id);
        $holiday->update($data);

        return redirect()->route('customer-app.holidays.index')->with('success', 'تعطیلی به‌روز شد.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('customer-app.holidays.index')->with('success', 'حذف شد.');
    }

    public function toggleActive(Holiday $holiday): RedirectResponse
    {
        $holiday->forceFill(['is_active' => ! $holiday->is_active])->save();

        return back()->with('success', $holiday->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $rule = 'required|date_format:Y-m-d|unique:crm_holidays,date'.($id ? ','.$id : '');

        return $request->validate([
            'date' => $rule,
            'label' => 'required|string|max:200',
            'type' => 'required|in:'.implode(',', array_keys(Holiday::TYPE_LABELS)),
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => (bool) $request->input('is_active', true)];
    }
}
