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

        return view('crm::objections.index', compact('items'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            // هر خط یک آیتم؛ تا ۲۰۰ آیتم و هر آیتم حداکثر ۲۵۵ کاراکتر.
            'items' => 'nullable|string|max:50000',
        ]);

        $lines = preg_split('/\r\n|\r|\n/', (string) ($validated['items'] ?? ''));
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (mb_strlen($line) > 255) {
                $line = mb_substr($line, 0, 255);
            }
            // یکتاسازی بدون تغییر ترتیب
            if (! in_array($line, $clean, true)) {
                $clean[] = $line;
            }
        }
        if (count($clean) > 200) {
            $clean = array_slice($clean, 0, 200);
        }

        CrmSetting::setJson(self::SETTING_KEY, $clean);

        return back()->with('success', 'لیست ایرادات دستگاه ذخیره شد (' . count($clean) . ' آیتم).');
    }
}
