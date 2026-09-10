<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Support\MobileNumber;
use Modules\SMS\Services\KavenegarService;
use Morilog\Jalali\Jalalian;

/**
 * هشدارِ پیامکیِ حذفِ موجودیت‌های عمومیِ سایت (برند/دستگاه/صفحه).
 *
 * گیرنده‌ها:
 *   ۱) اگر کلیدِ crm_settings: seo_delete_alert_mobiles مقدار داشته باشد، همان
 *      شماره‌ها (با , یا خطِ جدید جدا شده).
 *   ۲) در غیرِ این‌صورت، موبایلِ همهٔ کاربرانی که دسترسیِ delete-seo-content
 *      را دارند (مستقیم یا از طریقِ نقش).
 *
 * سرویس هیچ‌وقت throw نمی‌کند؛ خطا فقط لاگ می‌شود تا حذف مختل نشود.
 */
class SeoDeletionAlert
{
    public const PERMISSION = 'delete-seo-content';

    public const RECIPIENTS_SETTING = 'seo_delete_alert_mobiles';

    public function notify(Model $model): void
    {
        $d = method_exists($model, 'seoDeletionDescriptor')
            ? $model->seoDeletionDescriptor()
            : ['type' => 'محتوا', 'name' => (string) $model->getKey(), 'slug' => null];

        $actor = auth()->user()?->name ?: (auth()->user()?->mobile ?: 'کنسول/سیستم');
        $when = Jalalian::now()->format('Y/m/d H:i');

        $lines = [
            '🗑 حذفِ محتوای سایت',
            'نوع: '.($d['type'] ?? 'محتوا'),
            'عنوان: '.($d['name'] ?? '—'),
        ];
        if (! empty($d['slug'])) {
            $lines[] = 'مسیر: '.$d['slug'];
        }
        $lines[] = 'توسط: '.$actor;
        $lines[] = 'زمان: '.$when;
        $lines[] = 'بازگردانی: پنل › سطل بازیافت';
        $message = implode("\n", $lines);

        $recipients = $this->recipients();

        // رویداد همیشه لاگ می‌شود، حتی اگر گیرنده‌ای تنظیم نشده باشد.
        Log::warning('SEO content soft-deleted', [
            'type' => $d['type'] ?? null,
            'name' => $d['name'] ?? null,
            'slug' => $d['slug'] ?? null,
            'model' => get_class($model),
            'id' => $model->getKey(),
            'actor' => $actor,
            'recipients' => $recipients,
        ]);

        if (empty($recipients)) {
            return;
        }

        // در تست، ارسالِ واقعی (شبکه) انجام نمی‌شود.
        if (app()->runningUnitTests()) {
            return;
        }

        $sms = app(KavenegarService::class);
        foreach ($recipients as $mobile) {
            try {
                $result = $sms->send($mobile, $message);
                $this->log($mobile, $message, (bool) ($result['success'] ?? false), $result['message'] ?? null);
            } catch (\Throwable $e) {
                $this->log($mobile, $message, false, $e->getMessage());
            }
        }
    }

    /**
     * فهرستِ موبایل‌های گیرنده (نرمال‌شده، یکتا، غیرخالی).
     *
     * @return array<int, string>
     */
    public function recipients(): array
    {
        $override = (string) (CrmSetting::get(self::RECIPIENTS_SETTING) ?? '');
        if (trim($override) !== '') {
            $raw = preg_split('/[,\n\r]+/', $override) ?: [];
        } else {
            $raw = $this->permissionHolderMobiles();
        }

        $out = [];
        foreach ($raw as $m) {
            $n = MobileNumber::normalize((string) $m);
            if ($n !== '' && preg_match('/^09\d{9}$/', $n)) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }

    /**
     * موبایلِ کاربرانی که دسترسیِ delete-seo-content دارند (مستقیم یا از نقش).
     *
     * @return array<int, string>
     */
    private function permissionHolderMobiles(): array
    {
        try {
            return User::permission(self::PERMISSION)
                ->whereNotNull('mobile')
                ->pluck('mobile')
                ->all();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند یا permission وجود ندارد.
            return [];
        }
    }

    private function log(string $mobile, string $body, bool $success, ?string $error): void
    {
        try {
            SmsLog::create([
                'order_id' => null,
                'trigger_key' => 'seo_delete_alert',
                'recipient_mobile' => $mobile,
                'recipient_role' => 'admin',
                'body' => $body,
                'status' => $success ? 'success' : 'failed',
                'response' => $success ? null : $error,
                'sent_by' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SEO delete alert SmsLog failed', ['error' => $e->getMessage()]);
        }
    }
}
