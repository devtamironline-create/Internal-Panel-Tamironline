<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Support\AssignmentSettings;

/**
 * صفحهٔ «نحوهٔ پخش سفارش بین تکنسین‌ها» — کلیدِ خودکار/پیشنهاد و
 * پارامترهای آن.
 */
class AssignmentSettingsController extends Controller
{
    public function index(AutoAssignService $service)
    {
        // پیش‌نمایشِ خشک: اگر همین حالا اجرا می‌شد، چند سفارش پخش می‌شد.
        $preview = $service->run(dryRun: true);

        // تکنسین‌های فعالی که «نوع خدمات»شان تعیین نشده — این‌ها از پیشنهاد
        // و پخش کنار گذاشته می‌شوند و مدیر باید بداند چند نفرند.
        $activeTechnicians = \Modules\CRM\Models\Technician::query()
            ->where('status', 'active')
            ->where('exclude_from_suggestions', false)
            ->get(['id', 'first_name', 'firstname_tech', 'service_types']);

        $withoutServiceTypes = $activeTechnicians->filter(
            fn ($t) => ! is_array($t->service_types) || empty($t->service_types)
        )->values();

        return view('crm::assignment-settings.index', [
            'settings' => AssignmentSettings::all(),
            'activeTechnicianCount' => $activeTechnicians->count(),
            'withoutServiceTypes' => $withoutServiceTypes,
            'weightLabels' => AssignmentSettings::WEIGHT_LABELS,
            'weightDefaults' => \Modules\CRM\Services\TechnicianSuggestionService::WEIGHTS,
            'preview' => $preview,
            'pendingCount' => $service->pendingOrders()->count(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'assign_mode' => 'required|in:suggest,auto',
            'assign_grace_minutes' => 'required|integer|min:0|max:1440',
            'assign_min_score' => 'required|integer|min:0|max:100',
            'assign_max_per_run' => 'required|integer|min:1|max:500',
            'assign_max_age_days' => 'required|integer|min:1|max:90',
            'assign_history_enabled' => 'required|boolean',
            'assign_history_months' => 'required|integer|min:1|max:60',
            'assign_balance_cap' => 'required|integer|min:1|max:50',
            'assign_run_every_minutes' => 'required|integer|min:1|max:60',
            'weights' => 'required|array',
            'weights.*' => 'required|integer|min:0|max:100',
        ]);

        $validated['assign_history_enabled'] = $validated['assign_history_enabled'] ? 1 : 0;

        AssignmentSettings::save($validated);
        AssignmentSettings::saveWeights($validated['weights']);

        return back()->with('success', $validated['assign_mode'] === AssignmentSettings::MODE_AUTO
            ? 'پخش خودکار روشن شد. سفارش‌های واجد شرایط هر ۵ دقیقه تخصیص داده می‌شوند.'
            : 'حالت روی «فقط پیشنهاد» تنظیم شد. تخصیص همچنان دستی انجام می‌شود.');
    }
}
