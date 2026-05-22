<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\SmsTemplate;
use Modules\SMS\Services\KavenegarService;

/**
 * صفحه یکپارچه مدیریت پیامک:
 *   - تنظیمات کاوه‌نگار (API key، sender)
 *   - تنظیمات پراکسی (enabled، URL، secret)
 *   - لیست همه triggerها با ادیت inline (template body + toggle on/off)
 *   - تست ارسال پیامک
 */
class SmsManagementController extends Controller
{
    public function index()
    {
        $templates = SmsTemplate::orderBy('recipient')->orderBy('trigger_key')->get();

        $settings = [
            'kavenegar_api_key' => CrmSetting::get('kavenegar_api_key', ''),
            'sms_proxy_enabled' => CrmSetting::get('sms_proxy_enabled', '0'),
            'sms_proxy_url'     => CrmSetting::get('sms_proxy_url', 'https://api.ganjemarket.com'),
            'sms_proxy_secret'  => CrmSetting::get('sms_proxy_secret', ''),
        ];

        return view('crm::sms-management.index', [
            'templates' => $templates,
            'settings'  => $settings,
            'variables' => $this->availableVariables(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'kavenegar_api_key' => 'nullable|string|max:500',
            'sms_proxy_enabled' => 'nullable|in:0,1',
            'sms_proxy_url'     => 'nullable|string|max:255|url',
            'sms_proxy_secret'  => 'nullable|string|max:255',
        ]);

        CrmSetting::set('kavenegar_api_key', (string) ($validated['kavenegar_api_key'] ?? ''));
        CrmSetting::set('sms_proxy_enabled', (string) ($validated['sms_proxy_enabled'] ?? '0'));
        CrmSetting::set('sms_proxy_url', (string) ($validated['sms_proxy_url'] ?? ''));
        CrmSetting::set('sms_proxy_secret', (string) ($validated['sms_proxy_secret'] ?? ''));

        return back()->with('success', '✓ تنظیمات سرویس پیامک ذخیره شد.');
    }

    public function updateTemplate(Request $request, SmsTemplate $template)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'title' => $validated['title'],
            'body'  => $validated['body'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', "✓ قالب «{$template->title}» ذخیره شد.");
    }

    public function toggle(SmsTemplate $template)
    {
        $template->update(['is_active' => ! $template->is_active]);
        return back()->with('success', $template->is_active ? 'قالب فعال شد.' : 'قالب غیرفعال شد.');
    }

    /** ارسال تست — یک پیامک به شماره داده‌شده با body دل‌خواه. */
    public function test(Request $request, KavenegarService $sms)
    {
        $request->validate([
            'mobile' => 'required|string|regex:/^09\d{9}$/',
            'body'   => 'required|string|max:500',
        ]);

        $result = $sms->send($request->input('mobile'), $request->input('body'));

        if ($result['success'] ?? false) {
            return back()->with('success', '✓ پیامک تست ارسال شد.');
        }
        return back()->with('error', '✗ خطا در ارسال: ' . ($result['message'] ?? 'unknown'));
    }

    protected function availableVariables(): array
    {
        return [
            '{customer_name}'    => 'نام مشتری',
            '{customer_mobile}'  => 'موبایل مشتری',
            '{order_code}'       => 'کد سفارش',
            '{technician_name}'  => 'نام تکنسین',
            '{technician_mobile}'=> 'موبایل تکنسین',
            '{status}'           => 'وضعیت فعلی سفارش',
            '{shop_name}'        => 'نام فروشگاه (از تنظیمات)',
            '{visit_date}'       => 'زمان مراجعه',
            '{amount}'           => 'مبلغ (برای پیامک‌های مالی)',
            '{pay_link}'         => 'لینک پرداخت (برای پیامک فاکتور)',
        ];
    }
}
