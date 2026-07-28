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

        return view('crm::assignment-settings.index', [
            'settings' => AssignmentSettings::all(),
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
        ]);

        $validated['assign_history_enabled'] = $validated['assign_history_enabled'] ? 1 : 0;

        AssignmentSettings::save($validated);

        return back()->with('success', $validated['assign_mode'] === AssignmentSettings::MODE_AUTO
            ? 'پخش خودکار روشن شد. سفارش‌های واجد شرایط هر ۵ دقیقه تخصیص داده می‌شوند.'
            : 'حالت روی «فقط پیشنهاد» تنظیم شد. تخصیص همچنان دستی انجام می‌شود.');
    }
}
