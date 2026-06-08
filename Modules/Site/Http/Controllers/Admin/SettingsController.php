<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\SiteSetting;

class SettingsController extends Controller
{
    /**
     * نقشه‌ی فیلدها: key => [group, label, type, rules].
     */
    private function fieldMap(): array
    {
        return [
            // عمومی
            'site_name'           => ['general', 'نام سایت',        'string', ['nullable', 'string', 'max:120']],
            'site_tagline'        => ['general', 'شعار سایت',       'string', ['nullable', 'string', 'max:200']],
            'site_logo_url'       => ['general', 'لوگوی سایت',     'url',    ['nullable', 'url', 'max:500']],

            // تماس
            'contact_phone'       => ['contact', 'تلفن تماس',       'string', ['nullable', 'string', 'max:30']],
            'contact_email'       => ['contact', 'ایمیل پشتیبانی',  'email',  ['nullable', 'email:rfc', 'max:120']],
            'contact_address'     => ['contact', 'آدرس',           'string', ['nullable', 'string', 'max:500']],
            'contact_working_hours' => ['contact', 'ساعت کاری',     'string', ['nullable', 'string', 'max:200']],

            // شبکه‌های اجتماعی
            'social_instagram'    => ['social', 'اینستاگرام',       'url',    ['nullable', 'url', 'max:300']],
            'social_telegram'     => ['social', 'تلگرام',          'url',    ['nullable', 'url', 'max:300']],
            'social_whatsapp'     => ['social', 'واتساپ',          'url',    ['nullable', 'url', 'max:300']],
            'social_youtube'      => ['social', 'یوتیوب',          'url',    ['nullable', 'url', 'max:300']],
            'social_linkedin'     => ['social', 'لینکدین',          'url',    ['nullable', 'url', 'max:300']],

            // سئو پیش‌فرض
            'seo_default_title'       => ['seo', 'Meta Title پیش‌فرض',       'string', ['nullable', 'string', 'max:200']],
            'seo_default_description' => ['seo', 'Meta Description پیش‌فرض', 'string', ['nullable', 'string', 'max:500']],
            'seo_google_analytics'    => ['seo', 'Google Analytics ID',     'string', ['nullable', 'string', 'max:40']],
        ];
    }

    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-settings')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function edit(): View
    {
        $this->checkAccess();

        $fields = $this->fieldMap();
        $values = SiteSetting::query()->pluck('value', 'key')->all();

        $groups = [];
        foreach ($fields as $key => [$group, $label, $type, $rules]) {
            $groups[$group][] = [
                'key'   => $key,
                'label' => $label,
                'type'  => $type,
                'value' => $values[$key] ?? null,
            ];
        }

        return view('site::admin.settings.edit', compact('groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->checkAccess();

        $fields = $this->fieldMap();
        $rules = [];
        foreach ($fields as $key => [$group, $label, $type, $r]) {
            $rules[$key] = $r;
        }

        $data = $request->validate($rules);

        foreach ($fields as $key => [$group, $label, $type, $r]) {
            $value = $data[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            SiteSetting::set($key, $value, $group);
        }

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
