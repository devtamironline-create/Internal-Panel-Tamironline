<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\PushEventSetting;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Services\Najva\NajvaPushService;
use Modules\CRM\Support\TechPushPolicy;

/**
 * پنلِ مدیریتِ پوش — داشبورد، رویدادها و تنظیمات (بخش‌های ۵.۱، ۵.۲، ۵.۶).
 *
 * کمپین، گزارش و مشترکین کنترلرِ خودشان را دارند؛ این‌جا فقط چیزهایی است
 * که «پیکربندی» محسوب می‌شوند.
 */
class PushManagementController extends Controller
{
    /** ۵.۱ — داشبورد. */
    public function index()
    {
        return view('crm::push.dashboard', [
            'totals' => $this->totals(),
            'daily' => $this->daily(),
            'alarm' => $this->latestAlarm(),
            'configured' => app(NajvaPushService::class)->configured(),
            'coverage' => $this->coverage(),
        ]);
    }

    /** ۵.۲ — فهرستِ شش رویداد با تنظیماتشان. */
    public function events()
    {
        $saved = PushEventSetting::all()->keyBy('event_key');

        $rows = collect(PushEvent::cases())->map(fn (PushEvent $event) => [
            'event' => $event,
            'setting' => $saved->get($event->value),
            'default_title' => $event->defaultTitle(),
            'default_body' => $event->defaultBody(),
            'default_ttl' => $event->ttl(),
            'variables' => $event->variables(),
            'governed' => in_array($event->value, TechPushPolicy::GOVERNED, true),
            'deduped' => in_array($event->value, TechPushPolicy::DEDUPED, true),
            'sent_30d' => PushLog::query()
                ->where('event_key', $event->value)
                ->where('created_at', '>=', now()->subDays(30))
                ->succeeded()->count(),
        ])->all();

        return view('crm::push.events', [
            'rows' => $rows,
            'window' => TechPushPolicy::window(),
            'titleMax' => NajvaPushService::TITLE_MAX,
            'bodyMax' => NajvaPushService::BODY_MAX,
        ]);
    }

    public function updateEvent(Request $request, string $eventKey)
    {
        $event = PushEvent::tryFrom($eventKey);
        if (! $event) {
            return back()->with('error', 'رویداد ناشناخته است.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:'.NajvaPushService::TITLE_MAX,
            'body' => 'nullable|string|max:'.NajvaPushService::BODY_MAX,
            'ttl' => 'nullable|integer|min:1|max:720',
            'quiet_start' => 'nullable|integer|min:0|max:23',
            'quiet_end' => 'nullable|integer|min:0|max:23',
            'send_sms' => 'nullable|in:0,1',
        ], [
            'title.max' => 'عنوان حداکثر '.NajvaPushService::TITLE_MAX.' کاراکتر است.',
            'body.max' => 'متن حداکثر '.NajvaPushService::BODY_MAX.' کاراکتر است.',
            'ttl.max' => 'ماندگاری حداکثر ۷۲۰ ساعت است.',
        ]);

        // متغیرِ ناشناخته روی گوشیِ تکنسین به رشتهٔ خالی تبدیل می‌شود و
        // ادمین هیچ‌وقت نمی‌فهمد چرا. جلوی ذخیره گرفته می‌شود.
        foreach (['title', 'body'] as $field) {
            $unknown = PushEventSetting::unknownVariables($event, $validated[$field] ?? null);
            if ($unknown !== []) {
                return back()->withInput()->with(
                    'error',
                    'متغیر نامعتبر در '.($field === 'title' ? 'عنوان' : 'متن').': '
                    .collect($unknown)->map(fn ($v) => '{'.$v.'}')->implode('، ')
                    .' — متغیرهای مجاز: '
                    .collect($event->variables())->map(fn ($v) => '{'.$v.'}')->implode('، ')
                );
            }
        }

        // یکی از دو ساعت بدونِ دیگری معنا ندارد.
        $start = $validated['quiet_start'] ?? null;
        $end = $validated['quiet_end'] ?? null;
        if (($start === null) !== ($end === null)) {
            return back()->withInput()->with('error', 'برای پنجرهٔ ساعت باید هر دو مقدار «از» و «تا» را وارد کنید.');
        }

        PushEventSetting::updateOrCreate(
            ['event_key' => $event->value],
            [
                'title' => filled($validated['title'] ?? null) ? $validated['title'] : null,
                'body' => filled($validated['body'] ?? null) ? $validated['body'] : null,
                'ttl' => $validated['ttl'] ?? null,
                'quiet_start' => $start,
                'quiet_end' => $end,
                'send_sms' => (bool) ($validated['send_sms'] ?? false),
            ]
        );
        PushEventSetting::flushCache();

        return back()->with('success', '✓ تنظیمات رویداد «'.$event->label().'» ذخیره شد.');
    }

    public function toggleEvent(string $eventKey)
    {
        $event = PushEvent::tryFrom($eventKey);
        if (! $event) {
            return back()->with('error', 'رویداد ناشناخته است.');
        }

        $row = PushEventSetting::firstOrNew(['event_key' => $event->value]);
        $row->is_enabled = ! ($row->is_enabled ?? true);
        $row->save();
        PushEventSetting::flushCache();

        return back()->with('success', $row->is_enabled
            ? '✓ رویداد «'.$event->label().'» فعال شد.'
            : '✓ رویداد «'.$event->label().'» غیرفعال شد.');
    }

    /** ۵.۶ — تنظیمات و تست اتصال. */
    public function settings()
    {
        return view('crm::push.settings', [
            'settings' => [
                'najva_api_key' => CrmSetting::get('najva_api_key', ''),
                'najva_website_id' => CrmSetting::get('najva_website_id', ''),
                'najva_whitelisted_ips' => CrmSetting::get('najva_whitelisted_ips', ''),
            ],
            'window' => TechPushPolicy::window(),
            'envKey' => filled(config('services.najva.api_key')),
            'envWebsite' => filled(config('services.najva.website_id')),
            'queueConnection' => trim((string) config('services.najva.queue_connection', '')),
            'appUrl' => config('services.tech_app.url'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'najva_api_key' => 'nullable|string|max:500',
            'najva_website_id' => 'nullable|string|max:50',
            'najva_whitelisted_ips' => 'nullable|string|max:500',
            'tech_push_quiet_start' => 'required|integer|min:0|max:23',
            'tech_push_quiet_end' => 'required|integer|min:0|max:23',
        ], [
            'tech_push_quiet_start.required' => 'ساعت شروع پنجرهٔ مجاز را وارد کنید.',
            'tech_push_quiet_end.required' => 'ساعت پایان پنجرهٔ مجاز را وارد کنید.',
        ]);

        // شناسهٔ سایت عددی است؛ UUIDِ اسکریپت این‌جا خطای ۴۰۰ می‌سازد و
        // تشخیصش از پیامِ نجوا سخت است.
        $websiteId = trim((string) ($validated['najva_website_id'] ?? ''));
        if ($websiteId !== '' && ! ctype_digit($websiteId)) {
            return back()->withInput()->with(
                'error',
                'شناسهٔ سایت باید عدد باشد (مثل 45290). آنچه وارد کردید شبیه UUID اسکریپت است — '
                .'با دکمهٔ «تست اتصال» عدد درست را از نجوا بگیرید.'
            );
        }

        CrmSetting::set('najva_api_key', trim((string) ($validated['najva_api_key'] ?? '')));
        CrmSetting::set('najva_website_id', $websiteId);
        CrmSetting::set('najva_whitelisted_ips', trim((string) ($validated['najva_whitelisted_ips'] ?? '')));
        TechPushPolicy::saveWindow(
            (int) $validated['tech_push_quiet_start'],
            (int) $validated['tech_push_quiet_end'],
        );

        return back()->with('success', '✓ تنظیمات پوش ذخیره شد.');
    }

    /**
     * دکمهٔ «تست اتصال» — ساده‌ترین راهِ تشخیصِ ۴۰۳ از ۴۱۶، و جایی که
     * شناسهٔ عددیِ سایت پیدا می‌شود.
     */
    public function testConnection(NajvaPushService $najva)
    {
        $result = $najva->sender();

        if (! $result['ok']) {
            return back()->with('error', '✗ '.$result['message']);
        }

        $list = collect($result['websites'])
            ->map(fn ($w) => ($w['address'] ?? '?').' → شناسه: '.($w['id'] ?? '?'))
            ->implode(' | ');

        return back()->with('success', '✓ اتصال برقرار است. '
            .($list !== '' ? 'سایت‌های حساب: '.$list : 'هیچ سایتی روی این حساب ثبت نشده.'));
    }

    // ───────────────────────── دادهٔ داشبورد

    /** @return array<string, int> */
    private function totals(): array
    {
        $since = fn (int $days) => PushLog::query()->where('created_at', '>=', now()->subDays($days));

        $month = (clone $since(30))->get(['status', 'cost']);

        $succeeded = $month->whereIn('status', [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED])->count();

        return [
            'today' => $since(1)->count(),
            'week' => $since(7)->count(),
            'month' => $month->count(),
            'month_succeeded' => $succeeded,
            'month_failed' => $month->count() - $succeeded,
            'month_cost' => (int) $month->sum('cost'),
            // نرخِ پذیرشِ نجوا، نه نرخِ تحویل: تحویل فقط از گزارشِ بعدیِ
            // نجوا معلوم می‌شود و این‌جا ادعایش نمی‌کنیم.
            'accept_rate' => $month->count() > 0 ? (int) round($succeeded / $month->count() * 100) : 0,
        ];
    }

    /** @return list<array{day: string, total: int, ok: int}> */
    private function daily(): array
    {
        $rows = PushLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as ok', [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED])
            ->groupBy('day')
            ->pluck('total', 'day');

        $ok = PushLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('date(created_at) as day')
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as ok', [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED])
            ->groupBy('day')
            ->pluck('ok', 'day');

        // روزهای بی‌ارسال هم باید در نمودار جای خالی داشته باشند، وگرنه
        // نمودار وقفه را پنهان می‌کند.
        return collect(range(29, 0))->map(function (int $back) use ($rows, $ok) {
            $day = now()->subDays($back)->format('Y-m-d');

            return [
                'day' => $day,
                'total' => (int) ($rows[$day] ?? 0),
                'ok' => (int) ($ok[$day] ?? 0),
            ];
        })->all();
    }

    /** آخرین خطای پیکربندی — مبنای بنرِ قرمز. */
    private function latestAlarm(): ?PushLog
    {
        return PushLog::query()->alarming()->latest('created_at')->first();
    }

    /** @return array{technicians: int, with_token: int, tokens: int} */
    private function coverage(): array
    {
        $active = DB::table('crm_technicians')->where('status', 'active')->whereNull('deleted_at')->count();

        $withToken = TechnicianPushToken::query()->active()
            ->distinct()->count('technician_id');

        return [
            'technicians' => (int) $active,
            'with_token' => (int) $withToken,
            'tokens' => TechnicianPushToken::query()->active()->count(),
        ];
    }
}
