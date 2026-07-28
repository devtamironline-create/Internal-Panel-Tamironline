<?php

namespace Modules\Staff\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * قرارداد یک کارمند (پرسنل). مشخصات طرف دوم در زمان صدور snapshot می‌شود
 * تا تغییرِ بعدیِ پروفایلِ کاربر، سندِ امضاشده را تغییر ندهد.
 */
class StaffContract extends Model
{
    use SoftDeletes;

    protected $table = 'staff_contracts';

    protected $fillable = [
        'contract_number', 'user_id',
        'party_title', 'party_name', 'party_father_name', 'party_national_code',
        'party_address', 'party_phone',
        'contract_date', 'start_date', 'end_date', 'service_description',
        'monthly_wage', 'holiday_hourly_rate', 'promissory_amount', 'promissory_serial',
        'non_solicit_months', 'payment_grace_days', 'confidentiality_years',
        'status', 'reject_reason',
        'doc_national_card_front', 'doc_national_card_back',
        'doc_birth_certificate_p1', 'doc_birth_certificate_p2',
        'doc_promissory_note', 'doc_address_proof', 'documents_submitted_at',
        'signature_path', 'signed_at', 'signed_ip',
        'video_path', 'video_recorded_at',
        'approved_by', 'approved_at', 'final_pdf_path',
        'created_by', 'admin_notes',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'documents_submitted_at' => 'datetime',
        'signed_at' => 'datetime',
        'video_recorded_at' => 'datetime',
        'approved_at' => 'datetime',
        'monthly_wage' => 'integer',
        'holiday_hourly_rate' => 'integer',
        'promissory_amount' => 'integer',
        'non_solicit_months' => 'integer',
        'payment_grace_days' => 'integer',
        'confidentiality_years' => 'integer',
    ];

    /** وضعیت‌ها و برچسب فارسی. */
    public const STATUSES = [
        'awaiting_staff' => 'در انتظار تکمیل کارمند',
        'submitted' => 'ارسال‌شده برای بررسی',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
    ];

    /** مدارک الزامی: کلید ستون → برچسب فارسی. */
    public const DOCUMENTS = [
        'doc_national_card_front' => 'کارت ملی (روی کارت)',
        'doc_national_card_back' => 'کارت ملی (پشت کارت)',
        'doc_birth_certificate_p1' => 'شناسنامه (صفحه اول)',
        'doc_birth_certificate_p2' => 'شناسنامه (صفحه دوم)',
        'doc_promissory_note' => 'تصویر سفته ضمانت',
        'doc_address_proof' => 'مدرک احراز نشانی محل سکونت',
    ];

    /** توضیح کمکی هر مدرک در فرم بارگذاری. */
    public const DOCUMENT_HINTS = [
        'doc_address_proof' => 'تأییدیه کد پستی، قبض برق، کپی سند مالکیت یا اجاره‌نامه',
    ];

    /** حداکثر حجم مجاز هر فایل (کیلوبایت) — ۲۰ مگابایت. */
    public const MAX_UPLOAD_KB = 20480;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** مدارکِ بارگذاری‌شده: کلید → مسیر. */
    public function uploadedDocuments(): array
    {
        $out = [];
        foreach (array_keys(self::DOCUMENTS) as $key) {
            if (filled($this->{$key})) {
                $out[$key] = $this->{$key};
            }
        }

        return $out;
    }

    /** مدارکِ هنوز بارگذاری‌نشده: کلید → برچسب. */
    public function missingDocuments(): array
    {
        $out = [];
        foreach (self::DOCUMENTS as $key => $label) {
            if (blank($this->{$key})) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    public function documentsComplete(): bool
    {
        return $this->missingDocuments() === [];
    }

    public function hasSignature(): bool
    {
        return filled($this->signature_path);
    }

    public function hasVideo(): bool
    {
        return filled($this->video_path);
    }

    /** آیا همهٔ الزامات برای ارسال به بررسیِ ادمین کامل است؟ */
    public function readyToSubmit(): bool
    {
        return $this->documentsComplete() && $this->hasSignature() && $this->hasVideo();
    }

    /** درصد پیشرفتِ تکمیل توسط کارمند (۶ مدرک + امضا + ویدیو). */
    public function completionPercent(): int
    {
        $total = count(self::DOCUMENTS) + 2;
        $done = count($this->uploadedDocuments())
            + ($this->hasSignature() ? 1 : 0)
            + ($this->hasVideo() ? 1 : 0);

        return (int) round($done / $total * 100);
    }

    /** آیا کارمند هنوز اجازهٔ ویرایش دارد؟ (تأییدشده قفل است) */
    public function editableByStaff(): bool
    {
        return in_array($this->status, ['awaiting_staff', 'rejected'], true);
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    /**
     * آمارِ قراردادهای یک کاربر برای سایدبار — به‌صورتِ «امن».
     *
     * سایدبار روی همهٔ صفحات رندر می‌شود؛ اگر جدول هنوز migrate نشده باشد
     * (فاصلهٔ بینِ git pull و artisan migrate) یا دیتابیس خطا بدهد، کلِ پنل
     * نباید بشکند. پس هر استثنا به صفر تبدیل می‌شود.
     *
     * @return array{total: int, todo: int}
     */
    public static function sidebarCounts(?int $userId): array
    {
        $empty = ['total' => 0, 'todo' => 0];
        if (! $userId) {
            return $empty;
        }

        try {
            $rows = static::query()
                ->where('user_id', $userId)
                ->selectRaw('count(*) as total, sum(case when status in (?, ?) then 1 else 0 end) as todo', ['awaiting_staff', 'rejected'])
                ->first();

            return [
                'total' => (int) ($rows->total ?? 0),
                'todo' => (int) ($rows->todo ?? 0),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /** شماره قرارداد یکتا: TOL-{سال}-{عدد ترتیبی}. */
    public static function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('TOL-%d-%04d', $year, $count);
    }
}
