<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Services\Najva\NajvaPushService;
use Modules\CRM\Support\TechPushPolicy;

/**
 * ۵.۳ — ارسالِ دستی (کمپین).
 *
 * ارسالِ دستی عمداً از `SendTechnicianPush` رد نمی‌شود: آن Job برای
 * رویدادهای سیستمی است و ضدِتکرار و پنجرهٔ ساعت رویش اعمال می‌شود. ادمینی
 * که آگاهانه دکمهٔ ارسال را می‌زند نباید با «ساعت مجاز» یا «قبلاً فرستاده
 * شده» ساکت شود — ولی باید ببیند چه چیزی دارد می‌فرستد و به چند نفر.
 */
class PushCampaignController extends Controller
{
    public function create()
    {
        $technicians = Technician::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('firstname_tech')
            ->get(['id', 'first_name', 'firstname_tech']);

        $tokenCounts = TechnicianPushToken::query()->active()
            ->selectRaw('technician_id, count(*) as c')
            ->groupBy('technician_id')
            ->pluck('c', 'technician_id');

        return view('crm::push.campaign', [
            'technicians' => $technicians,
            'tokenCounts' => $tokenCounts,
            'reachable' => $tokenCounts->filter()->count(),
            'scheduled' => PushLog::query()
                ->where('status', PushLog::STATUS_SCHEDULED)
                ->whereNull('event_key')
                ->latest('created_at')
                ->limit(30)
                ->get(),
            'titleMax' => NajvaPushService::TITLE_MAX,
            'bodyMax' => NajvaPushService::BODY_MAX,
            'appUrl' => rtrim((string) config('services.tech_app.url'), '/'),
        ]);
    }

    public function store(Request $request, NajvaPushService $najva)
    {
        $validated = $request->validate([
            'audience' => 'required|in:all,selected',
            'technician_ids' => 'required_if:audience,selected|array',
            'technician_ids.*' => 'integer',
            'title' => 'required|string|max:'.NajvaPushService::TITLE_MAX,
            'body' => 'required|string|max:'.NajvaPushService::BODY_MAX,
            'click_url' => 'required|url|max:500',
            'ttl' => 'required|integer|min:1|max:720',
            'scheduled_at' => 'nullable|string|max:20',
            'scheduled_time' => 'nullable|string|max:5',
        ], [
            'audience.required' => 'گیرندگان را انتخاب کنید.',
            'technician_ids.required_if' => 'حداقل یک تکنسین را انتخاب کنید.',
            'title.required' => 'عنوان اعلان را وارد کنید.',
            'title.max' => 'عنوان حداکثر '.NajvaPushService::TITLE_MAX.' کاراکتر است.',
            'body.required' => 'متن اعلان را وارد کنید.',
            'body.max' => 'متن حداکثر '.NajvaPushService::BODY_MAX.' کاراکتر است.',
            'click_url.required' => 'لینک مقصد را وارد کنید.',
            'click_url.url' => 'لینک مقصد باید کامل و با https:// باشد.',
        ]);

        $tokens = TechnicianPushToken::query()->active()
            ->when(
                $validated['audience'] === 'selected',
                fn ($q) => $q->whereIn('technician_id', $validated['technician_ids'] ?? [])
            )
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return back()->withInput()->with(
                'error',
                'هیچ مرورگرِ فعالی برای گیرندگانِ انتخاب‌شده ثبت نشده — اعلان جایی برای رفتن ندارد.'
            );
        }

        $scheduledAt = null;
        if (filled($validated['scheduled_at'] ?? null)) {
            $gregorian = \App\Support\JalaliDate::toGregorian((string) $validated['scheduled_at']);

            if ($gregorian === null) {
                return back()->withInput()->with('error', 'تاریخ زمان‌بندی نامعتبر است. قالب درست: ۱۴۰۵/۰۵/۱۲');
            }

            $time = preg_match('/^\d{1,2}:\d{2}$/', (string) ($validated['scheduled_time'] ?? ''))
                ? $validated['scheduled_time'] : '09:00';

            $moment = \Carbon\CarbonImmutable::parse($gregorian.' '.$time, config('app.timezone'));

            if ($moment->isPast()) {
                return back()->withInput()->with('error', 'زمانِ انتخاب‌شده گذشته است.');
            }

            // نجوا RFC3339 می‌خواهد — با آفستِ تهران، نه UTCِ بی‌نشان.
            $scheduledAt = $moment->toRfc3339String();
        }

        $result = $najva->sendToTokens(
            $tokens,
            $validated['title'],
            $validated['body'],
            $validated['click_url'],
            (int) $validated['ttl'],
            ['sent_by' => auth()->id()],
            $scheduledAt,
        );

        if ($result->needsAdminAlarm()) {
            return back()->withInput()->with('error', '✗ '.$result->errorMessage
                .' — تا رفع نشدن این مورد هیچ اعلانی ارسال نمی‌شود.');
        }

        if (! $result->ok() && $result->sent === []) {
            return back()->withInput()->with('error', '✗ ارسال انجام نشد: '
                .($result->errorMessage ?: 'پاسخی از نجوا دریافت نشد.'));
        }

        $message = $scheduledAt !== null
            ? '✓ اعلان برای '.count($result->sent).' مرورگر زمان‌بندی شد.'
            : '✓ اعلان به '.count($result->sent).' مرورگر ارسال شد.';

        if ($result->invalid !== []) {
            $message .= ' '.count($result->invalid).' توکن نامعتبر بود و باطل شد.';
        }
        if ($result->failed !== []) {
            $message .= ' '.count($result->failed).' مورد ناموفق بود — جزئیات در گزارش.';
        }

        return redirect()->route('crm.push.campaign.create')->with('success', $message);
    }

    /** لغوِ یک ارسالِ زمان‌بندی‌شده، تا پیش از رسیدنِ موعد. */
    public function cancel(Request $request, NajvaPushService $najva)
    {
        $validated = $request->validate(['request_id' => 'required|string|max:64']);

        $result = $najva->cancelScheduled([$validated['request_id']]);

        if (! $result['ok']) {
            return back()->with('error', '✗ '.$result['message']);
        }

        // ردیف‌های لاگ باید همان چیزی را بگویند که واقعاً اتفاق افتاده.
        PushLog::query()
            ->where('request_id', $validated['request_id'])
            ->where('status', PushLog::STATUS_SCHEDULED)
            ->update(['status' => PushLog::STATUS_CANCELLED]);

        return back()->with('success', '✓ '.$result['message']);
    }

    /** ارسالِ آزمایشی به یک تکنسین — برای عیب‌یابی (بخش ۵.۵). */
    public function test(Request $request, Technician $technician, NajvaPushService $najva)
    {
        $tokens = TechnicianPushToken::query()->active()
            ->where('technician_id', $technician->id)
            ->pluck('token')->all();

        if ($tokens === []) {
            return back()->with('error', 'این تکنسین هیچ مرورگرِ فعالی ثبت نکرده — اول باید اعلان را در اپ فعال کند.');
        }

        $result = $najva->sendToTokens(
            $tokens,
            'اعلان آزمایشی',
            'این یک پیام آزمایشی از پنل است. اگر آن را می‌بینید، اعلان‌ها درست کار می‌کنند.',
            rtrim((string) config('services.tech_app.url'), '/').'/orders',
            1,
            ['sent_by' => auth()->id()],
        );

        if (! $result->ok() && $result->sent === []) {
            return back()->with('error', '✗ ارسال آزمایشی ناموفق: '.($result->errorMessage ?: 'پاسخی از نجوا نیامد.'));
        }

        return back()->with('success', '✓ اعلان آزمایشی به '.count($result->sent).' مرورگر ارسال شد.');
    }

    /** پنجرهٔ ساعتِ مجاز فقط برای اطلاعِ ادمین در فرم. */
    public function window(): array
    {
        return TechPushPolicy::window();
    }
}
