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
        'contract_number', 'version', 'user_id',
        'party_title', 'party_name', 'party_father_name', 'party_national_code',
        'party_address', 'party_phone',
        'contract_date', 'start_date', 'end_date', 'service_description',
        'monthly_wage', 'holiday_hourly_rate', 'promissory_amount', 'promissory_serial',
        'v2_daily_wage', 'v2_daily_seniority', 'v2_monthly_benefits', 'v2_marriage_allowance',
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
        'version' => 'integer',
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
        'v2_daily_wage' => 'integer',
        'v2_daily_seniority' => 'integer',
        'v2_monthly_benefits' => 'integer',
        'v2_marriage_allowance' => 'integer',
        'non_solicit_months' => 'integer',
        'payment_grace_days' => 'integer',
        'confidentiality_years' => 'integer',
    ];

    /** نسخه‌های قالبِ قرارداد و برچسب فارسی (۱۴۰۵/۰۶/۰۳). */
    public const VERSIONS = [
        1 => 'نسخه ۱ — قرارداد مشاوره‌ای/پروژه‌ای',
        2 => 'نسخه ۲ — قرارداد کار با مدت معین (کارمند)',
    ];

    /** نامِ Blade متنِ قرارداد بر اساس نسخه. */
    public function documentView(): string
    {
        return (int) $this->version === 2
            ? 'staff::contracts._document_v2'
            : 'staff::contracts._document';
    }

    /**
     * سه عددِ پایهٔ جدولِ حقوقِ نسخه ۲ (به ریال): مقدارِ snapshotِ رکورد،
     * وگرنه پیش‌فرضِ مجموعه (ContractSettings).
     *
     * @return array{daily_wage:int, daily_seniority:int, monthly_benefits:int}
     */
    public function v2SalaryBase(): array
    {
        $get = fn (?int $col, string $key, int $fallback) => $col !== null && $col > 0
            ? $col
            : ((int) (\Modules\Staff\Support\ContractSettings::int($key) ?? $fallback));

        $dailyWage = $get($this->v2_daily_wage, 'contract_v2_daily_wage', 5541850);

        return [
            'daily_wage' => $dailyWage,
            'daily_seniority' => $get($this->v2_daily_seniority, 'contract_v2_daily_seniority', 166667),
            'monthly_benefits' => $get($this->v2_monthly_benefits, 'contract_v2_monthly_benefits', 52000000),
            // حق تأهلِ ماهانه — مبلغِ قابلِ تنظیمِ مجموعه (پیش‌فرض ۵٬۰۰۰٬۰۰۰).
            'marriage_allowance' => $get($this->v2_marriage_allowance, 'contract_v2_marriage_allowance', 5000000),
            // حق اولادِ یک فرزند = ۳ برابرِ دستمزدِ روزانه (طبق قانونِ کار،
            // معادلِ ۳ روزِ حداقلِ دستمزد). مشتق است، تنظیمِ جدا ندارد.
            'child_allowance' => $dailyWage * 3,
        ];
    }

    /**
     * جدولِ کاملِ حقوقِ نسخه ۲ (۱۲ ردیف، مطابقِ متنِ رسمیِ قرارداد):
     * دستمزد/سنوات + جمع‌های ۳۰ و ۳۱ روزه + مزایا + حق تأهل + جمع کل +
     * حق اولاد + جمع ناخالص با حق اولاد.
     *
     * @return array<int, array{no:int, label:string, amount:int, bold:bool}>
     */
    public function v2SalaryTable(): array
    {
        $b = $this->v2SalaryBase();
        $dailyTotal = $b['daily_wage'] + $b['daily_seniority'];
        $m30 = $dailyTotal * 30;
        $m31 = $dailyTotal * 31;
        $benefits = $b['monthly_benefits'];
        $marriage = $b['marriage_allowance'];
        $child = $b['child_allowance'];

        // جمعِ کلِ ماهانه = دستمزدِ ماهانه + مزایای مشمول + حق تأهل.
        $gross30 = $m30 + $benefits + $marriage;
        $gross31 = $m31 + $benefits + $marriage;

        return [
            ['no' => 1, 'label' => 'دستمزد روزانه مبنای قرارداد و بیمه', 'amount' => $b['daily_wage'], 'bold' => false],
            ['no' => 2, 'label' => 'پایه سنوات روزانه', 'amount' => $b['daily_seniority'], 'bold' => false],
            ['no' => 3, 'label' => 'جمع دستمزد روزانه با پایه سنوات', 'amount' => $dailyTotal, 'bold' => false],
            ['no' => 4, 'label' => 'جمع دستمزد ۳۰ روزه با پایه سنوات', 'amount' => $m30, 'bold' => false],
            ['no' => 5, 'label' => 'جمع دستمزد ۳۱ روزه با پایه سنوات', 'amount' => $m31, 'bold' => false],
            ['no' => 6, 'label' => 'مزایای ماهانه مشمول شامل بن، حق مسکن و مزایای رفاهی قانونی', 'amount' => $benefits, 'bold' => false],
            ['no' => 7, 'label' => 'حق تأهل ماهانه', 'amount' => $marriage, 'bold' => false],
            ['no' => 8, 'label' => 'جمع کل حقوق و مزایای ماهانه ۳۰ روزه', 'amount' => $gross30, 'bold' => true],
            ['no' => 9, 'label' => 'حق اولاد یک فرزند (در صورت احراز شرایط قانونی)', 'amount' => $child, 'bold' => false],
            ['no' => 10, 'label' => 'جمع ناخالص ماهانه ۳۰ روزه با حق اولاد', 'amount' => $gross30 + $child, 'bold' => false],
            ['no' => 11, 'label' => 'جمع کل حقوق و مزایای ماهانه ۳۱ روزه', 'amount' => $gross31, 'bold' => true],
            ['no' => 12, 'label' => 'جمع ناخالص ماهانه ۳۱ روزه با حق اولاد', 'amount' => $gross31 + $child, 'bold' => false],
        ];
    }

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
     * همهٔ فایل‌های این قرارداد روی دیسک — مدارک، امضا، ویدیوی احراز و PDF نهایی.
     *
     * فقط جای حذف به‌کار می‌رود: پاک‌کردنِ رکورد بدونِ این‌ها یعنی کارت ملی و
     * شناسنامهٔ یک نفر روی سرور می‌ماند بی‌آنکه از پنل دیده شود.
     *
     * @return list<string>
     */
    public function allFilePaths(): array
    {
        $paths = [];
        foreach (array_keys(self::DOCUMENTS) as $column) {
            $paths[] = $this->{$column};
        }
        $paths[] = $this->signature_path;
        $paths[] = $this->video_path;
        $paths[] = $this->final_pdf_path;

        return array_values(array_filter($paths, fn ($p) => filled($p)));
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
