<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\CrmSetting;

/**
 * مدیریت لیست «ایرادات دستگاه» — همان objectionsList که در فرم ثبت سفارش
 * (OrderWizard / فرم edit) برای multi-select استفاده می‌شود.
 *
 * این لیست در crm_settings زیر کلید `wp.objectionsList` (به‌صورت JSON) نگه
 * داشته می‌شود. کلید با پیشوند wp. است چون به‌صورت تاریخی از پلاگین WP
 * سینک می‌شد؛ از این صفحه می‌توان مستقیماً از پنل ویرایشش کرد.
 */
class ObjectionsSettingsController extends Controller
{
    private const SETTING_KEY = 'wp.objectionsList';

    public function index()
    {
        $raw = CrmSetting::getJson(self::SETTING_KEY, []);
        $items = is_array($raw)
            ? array_values(array_filter(array_map('strval', $raw), fn ($s) => trim($s) !== ''))
            : [];

        return view('crm::objections-settings.index', compact('items'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'items'   => 'nullable|array|max:200',
            'items.*' => 'nullable|string|max:255',
        ]);

        $clean = [];
        foreach ($validated['items'] ?? [] as $item) {
            $item = trim((string) $item);
            if ($item === '') continue;
            // یکتاسازی بدون تغییر ترتیب
            if (! in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        CrmSetting::setJson(self::SETTING_KEY, $clean);

        return back()->with('success', 'لیست ایرادات دستگاه ذخیره شد (' . count($clean) . ' آیتم).');
    }
}
