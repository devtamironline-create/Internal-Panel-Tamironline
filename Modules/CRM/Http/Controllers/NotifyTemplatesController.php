<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\CrmSetting;
use Modules\CustomerApp\Support\NotifyTemplates;

/**
 * مدیریتِ قالب‌های پیامِ اطلاع‌رسانیِ مشتری (نوتیفِ اپ + پیام‌رسانِ بله) —
 * برای هر رویداد (ثبت سفارش + هر وضعیت): روشن/خاموش، تیتر و متن با placeholder.
 */
class NotifyTemplatesController extends Controller
{
    public function index()
    {
        return view('crm::notify-templates.index', [
            'templates' => NotifyTemplates::all(),
            'defaults' => NotifyTemplates::defaults(),
            'labels' => $this->labels(),
        ]);
    }

    public function update(Request $request)
    {
        $rows = (array) $request->input('templates', []);
        $defaults = NotifyTemplates::defaults();

        $out = [];
        foreach ($defaults as $key => $def) {
            $row = (array) ($rows[$key] ?? []);
            $out[$key] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'title' => mb_substr(trim((string) ($row['title'] ?? '')), 0, 200),
                'body' => mb_substr(trim((string) ($row['body'] ?? '')), 0, 1000),
            ];
        }

        CrmSetting::setJson(NotifyTemplates::SETTING_KEY, $out);

        return back()->with('success', 'قالب‌های پیام ذخیره شد و از همین لحظه اعمال می‌شود.');
    }

    /** بازگشت به پیش‌فرض‌های حرفه‌ای. */
    public function reset()
    {
        CrmSetting::setJson(NotifyTemplates::SETTING_KEY, null);

        return back()->with('success', 'قالب‌ها به پیش‌فرضِ سیستم بازگشت.');
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        $labels = ['order_created' => '📥 ثبت سفارش (پیامِ خوش‌آمد)'];
        foreach (OrderStatus::cases() as $status) {
            $labels[NotifyTemplates::statusKey($status)] = 'وضعیت: '.$status->label();
        }

        return $labels;
    }
}
