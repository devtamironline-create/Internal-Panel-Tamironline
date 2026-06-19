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

            // تماس (شماره‌ها به‌صورت لیست برچسب‌دار از طریق repeater مدیریت می‌شوند — کلید contact_phones)
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

        // لیست شماره‌های تماس برچسب‌دار — از کلید contact_phones (JSON).
        $phones = $this->decodePhones($values['contact_phones'] ?? null);
        if (empty($phones)) {
            // در صورت نبود لیست، یک ردیف از شماره‌ی legacy پر می‌شود تا ادمین شماره‌ی فعلی را ببیند.
            $legacy = $values['contact_phone'] ?? null;
            $phones = [['label' => '', 'number' => $legacy !== null ? trim($legacy) : '']];
        }

        return view('site::admin.settings.edit', compact('groups', 'phones'));
    }

    /**
     * تبدیل JSON ذخیره‌شده‌ی contact_phones به آرایه‌ای از ['label'=>..,'number'=>..].
     *
     * @return array<int, array{label: string, number: string}>
     */
    private function decodePhones(?string $json): array
    {
        if (! is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $phones = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $phones[] = [
                'label'  => trim((string) ($row['label'] ?? '')),
                'number' => $number,
            ];
        }

        return $phones;
    }

    public function update(Request $request): RedirectResponse
    {
        $this->checkAccess();

        $fields = $this->fieldMap();
        $rules = [];
        foreach ($fields as $key => [$group, $label, $type, $r]) {
            $rules[$key] = $r;
        }

        // اعتبارسنجی شماره‌های repeater: phones[][label] / phones[][number].
        $rules['phones']            = ['nullable', 'array', 'max:10'];
        $rules['phones.*.label']    = ['nullable', 'string', 'max:60'];
        $rules['phones.*.number']   = ['nullable', 'string', 'max:30'];

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

        // ساخت لیست تمیز شماره‌ها: trim، حذف ردیف‌های بدون شماره، حداکثر ۱۰ ردیف.
        $phones = [];
        foreach ((array) ($data['phones'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $phones[] = [
                'label'  => trim((string) ($row['label'] ?? '')),
                'number' => $number,
            ];
            if (count($phones) >= 10) {
                break;
            }
        }

        $json = json_encode($phones, JSON_UNESCAPED_UNICODE);
        SiteSetting::set('contact_phones', $json, 'contact');

        // هم‌گام‌سازی legacy: contact_phone = شماره‌ی اولین ردیف (برای مصرف‌کننده‌های قدیمی).
        SiteSetting::set('contact_phone', $phones[0]['number'] ?? null, 'contact');

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
