<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Services\Arm\OpportunityScoreService;

/**
 * تنظیمات بازوی سئو — وزن‌های امتیازدهی (روی settings پروژه ذخیره می‌شود)
 * و وضعیت یکپارچه‌سازی‌ها (فاز ۱ همه خاموش؛ فقط نمایش).
 */
class ArmSettingsController extends BaseArmController
{
    public function index(OpportunityScoreService $score)
    {
        $project = $this->project();

        return view('seo::arm.settings.index', [
            'project' => $project,
            'weights' => $score->weights(),
            'healthWeights' => (array) config('seo.arm.health_weights', []),
            'integrations' => (array) config('seo.arm.integrations', []),
            'provider' => (string) config('seo.arm.default_provider', 'database'),
        ]);
    }

    public function update(Request $request)
    {
        $project = $this->project();

        $validated = $request->validate([
            'description' => 'nullable|string|max:3000',
            'target_date' => 'nullable|date',
            'weekly_new_content_target' => 'nullable|integer|min:0|max:20',
            'weekly_refresh_target' => 'nullable|integer|min:0|max:20',
        ], [], [
            'weekly_new_content_target' => 'هدف محتوای جدید هفتگی',
            'weekly_refresh_target' => 'هدف به‌روزرسانی هفتگی',
        ]);

        $settings = (array) $project->settings;
        $settings['weekly_new_content_target'] = (int) ($validated['weekly_new_content_target'] ?? ($settings['weekly_new_content_target'] ?? 2));
        $settings['weekly_refresh_target'] = (int) ($validated['weekly_refresh_target'] ?? ($settings['weekly_refresh_target'] ?? 2));

        $project->update([
            'description' => $validated['description'] ?? $project->description,
            'target_date' => $validated['target_date'] ?? $project->target_date,
            'settings' => $settings,
        ]);

        SeoChangeLog::record('arm-settings', 'بازوی سئو', 'تنظیمات پروژهٔ بازوی سئو به‌روزرسانی شد.');

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
