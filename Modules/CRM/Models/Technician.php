<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * تکنسین — هم‌سو با libs/technician.php در CRM وردپرسی.
 *
 * فیلدهای مرجع WP:
 *   first_name, firstname_tech, technician_id, national_code, mobile,
 *   phone, phone_force, address, description, percent, max_order,
 *   max_price, status, type_tech, cart_img, province, specialty,
 *   ready_for_delivery (در WP: ready_for_derliver), type_of_calc_tech,
 *   tech_per_of_all, img_personal (در WP: img_Personal)
 */
class Technician extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'crm_technicians';

    protected $fillable = [
        'wp_id',
        'user_id',

        // مشخصات
        'first_name',
        'firstname_tech',
        'technician_id',
        'national_code',
        'mobile',
        'phone',
        'phone_force',
        'password',

        // آدرس
        'province',
        'address',

        // تخصص و توضیحات
        'specialty',
        'type_tech',
        'service_types',
        'description',

        // تصاویر
        'img_personal',
        'cart_img',

        // قوانین مالی/کاری
        'percent',
        'satisfaction_score',
        'tech_per_of_all',
        'max_order',
        'max_price',
        'type_of_calc_tech',

        // وضعیت
        'status',
        'ready_for_delivery',
        'exclude_from_suggestions',

        // جهت سینک (per-technician) — برای جلوگیری از overwrite ناخواسته بین WP↔Laravel
        'order_sync_direction',
        'wallet_sync_direction',

        'training_completed_at',
    ];

    /**
     * مقادیر مجاز برای جهت سینک. شامل توضیح فارسی برای استفاده در UI.
     *
     * پیش‌فرض‌ها:
     *   - سفارش: both (WP می‌سازد، Laravel ویرایش می‌کند، WP cron با
     *     فیلتر laravelManaged از overwrite جلوگیری می‌کند)
     *   - کیف‌پول: wp_to_laravel (برای جلوگیری از overwrite شارژ WP)
     */
    public const SYNC_DIRECTIONS = [
        'both' => 'دو طرفه',
        'wp_to_laravel' => 'فقط از WP به پنل',
        'laravel_to_wp' => 'فقط از پنل به WP',
        'none' => 'قطع سینک',
    ];

    /** آیا تغییری از سمت Laravel به WP push شود؟ */
    public function canPushOrder(): bool
    {
        return in_array($this->order_sync_direction ?? 'both', ['both', 'laravel_to_wp'], true);
    }

    public function canPushWallet(): bool
    {
        return in_array($this->wallet_sync_direction ?? 'wp_to_laravel', ['both', 'laravel_to_wp'], true);
    }

    /** آیا inbound از WP پذیرفته شود؟ */
    public function canReceiveOrderFromWp(): bool
    {
        return in_array($this->order_sync_direction ?? 'both', ['both', 'wp_to_laravel'], true);
    }

    public function canReceiveWalletFromWp(): bool
    {
        return in_array($this->wallet_sync_direction ?? 'wp_to_laravel', ['both', 'wp_to_laravel'], true);
    }

    protected $casts = [
        'wp_id' => 'integer',
        'percent' => 'integer',
        'satisfaction_score' => 'decimal:1',
        'tech_per_of_all' => 'integer',
        'max_order' => 'integer',
        'max_price' => 'integer',
        'ready_for_delivery' => 'boolean',
        'exclude_from_suggestions' => 'boolean',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'training_completed_at' => 'datetime',
        'service_types' => 'array',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * نام نمایشی — برای استفاده در رابط‌های اپ موبایل و فاکتور.
     * اولویت: firstname_tech (نام رسمی برای WP) → first_name + last_name → mobile.
     */
    public function getDisplayNameAttribute(): string
    {
        $firstnameTech = trim((string) ($this->attributes['firstname_tech'] ?? ''));
        if ($firstnameTech !== '') {
            return $firstnameTech;
        }

        $combined = trim(
            (string) ($this->attributes['first_name'] ?? '').' '.
            (string) ($this->attributes['last_name'] ?? '')
        );

        return $combined !== '' ? $combined : (string) ($this->attributes['mobile'] ?? 'تکنسین');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * اپراتورهای پشتیبانی تخصیص‌داده‌شده (چند-به-چند).
     * همگی در thread چت تکنسین پیام می‌بینند و می‌فرستند.
     */
    public function operators(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_technician_operators', 'technician_id', 'user_id')
            ->withPivot('assigned_at');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(TechChatMessage::class)->orderBy('created_at');
    }

    /** تغییراتِ زمان‌دارِ درصدِ کمیسیون (تاریخچه + برنامه‌ریزی‌شده‌ها). */
    public function percentChanges(): HasMany
    {
        return $this->hasMany(TechnicianPercentChange::class)->orderBy('effective_from');
    }

    /**
     * درصدهای مؤثرِ این تکنسین در یک لحظهٔ مشخص.
     *
     * برای هر فیلد، آخرین تغییرِ زمان‌داری که effective_from آن <= $at و مقدارِ
     * همان فیلد را دارد برنده است؛ اگر هیچ تغییری نبود، ستون‌های پایهٔ خودِ
     * تکنسین. تغییراتِ آینده (effective_from > $at) هیچ اثری ندارند — پس
     * برنامه‌ریزیِ «از ۱ خرداد درصد ۲۵» تا رسیدنِ آن لحظه، محاسبات را عوض نمی‌کند
     * و بعد از آن هم محاسباتِ قبلی (با تاریخِ تکمیلِ قدیمی) دست‌نخورده می‌مانند.
     *
     * @return array{percent:int, tech_per_of_all:int}
     */
    public function percentProfileAt(\Carbon\CarbonInterface $at): array
    {
        // reorder(): ترتیبِ پیش‌فرضِ رابطه (صعودی) را پاک می‌کند — این‌جا جدیدترین
        // تغییرِ اعمال‌شده باید اول باشد.
        $rows = $this->percentChanges()
            ->where('effective_from', '<=', $at)
            ->reorder()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['percent', 'tech_per_of_all', 'effective_from']);

        $percent = $rows->first(fn ($r) => $r->percent !== null)?->percent;
        $techPerOfAll = $rows->first(fn ($r) => $r->tech_per_of_all !== null)?->tech_per_of_all;

        return [
            'percent' => (int) ($percent ?? $this->percent ?? 0),
            'tech_per_of_all' => (int) ($techPerOfAll ?? $this->tech_per_of_all ?? 0),
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** شهرهای فعال — برای فیلتر منطقه‌ای در سیستم پیشنهاد تخصیص. */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'crm_technician_cities');
    }

    /**
     * مناطق پوشش — اختیاری. اگر برای یک شهر تکنسین هیچ منطقه‌ای
     * انتخاب نکرده، پیش‌فرض «همه مناطق آن شهر را می‌پوشاند».
     *
     * منطقه = ردیف فرزندِ crm_cities (district). pivot یکپارچهٔ
     * crm_technician_districts. (سیستم crm_regions بازنشسته شده است.)
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'crm_technician_districts', 'technician_id', 'district_id');
    }

    /** برندهای تخصصی. */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'crm_technician_brands');
    }

    /** دستگاه‌های قابل انجام. */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'crm_technician_devices');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * ویدیوهایی که این تکنسین مشاهده کرده — برای training gate.
     */
    public function watchedVideos(): BelongsToMany
    {
        return $this->belongsToMany(TrainingVideo::class, 'crm_technician_video_views', 'technician_id', 'video_id')
            ->withPivot('watched_at');
    }

    /** آیا آموزش این تکنسین تمام شده؟ */
    public function isTrainingCompleted(): bool
    {
        return $this->training_completed_at !== null;
    }

    /**
     * گزارش پیشرفت آموزش — تعداد دیده‌شده/کل، درصد، باقی‌مانده.
     *
     * @return array{watched:int, total:int, remaining:int, percent:int}
     */
    public function trainingProgress(): array
    {
        $total = TrainingVideo::active()->count();
        if ($total === 0) {
            // اگر هیچ ویدیویی فعال نیست، آموزش به‌صورت پیش‌فرض «تمام» است
            return ['watched' => 0, 'total' => 0, 'remaining' => 0, 'percent' => 100];
        }
        $watched = $this->watchedVideos()
            ->wherePivotNotNull('watched_at')
            ->where('crm_training_videos.is_active', true)
            ->count();
        $remaining = max(0, $total - $watched);

        return [
            'watched' => $watched,
            'total' => $total,
            'remaining' => $remaining,
            'percent' => (int) round($watched / $total * 100),
        ];
    }

    /** علامت‌گذاری یک ویدیو به‌عنوان دیده‌شده + بستن گیت اگر همه دیده شد. */
    public function markVideoWatched(TrainingVideo $video): void
    {
        $this->watchedVideos()->syncWithoutDetaching([
            $video->id => ['watched_at' => now()],
        ]);

        $progress = $this->refresh()->trainingProgress();
        if ($progress['remaining'] === 0 && $this->training_completed_at === null) {
            $this->forceFill(['training_completed_at' => now()])->saveQuietly();
        }
    }

    /**
     * مجموع سهم شرکت از فاکتورهای این تکنسین — بدهی تکنسین به شرکت.
     * این عدد مستقل از تراکنش‌های wallet ذخیره می‌شود (در crm_invoices).
     *
     * ⚠ فاکتورهایی که in_wallet=true دارند، کمیسیون‌شان قبلاً در
     * wallet_transactions ثبت شده — اگر اینجا هم شمرده شوند double-count
     * می‌شود و true_balance اشتباه (بیشتر منفی از واقع) برمی‌گردد.
     *
     * بنابراین:
     *   - fallback (شاخه دوم) on-demand فقط in_wallet=false را می‌شمرد.
     *   - اگر preloaded است (شاخه اول)، caller باید withSum/loadSum را با
     *     constraint `->where('in_wallet', false)` صدا زده باشد، یعنی:
     *     ->withSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share')
     */
    public function getInvoiceDebtAttribute(): int
    {
        if (array_key_exists('invoices_sum_company_share', $this->attributes)) {
            return (int) ($this->attributes['invoices_sum_company_share'] ?? 0);
        }

        return (int) $this->invoices()->where('in_wallet', false)->sum('company_share');
    }

    /**
     * مانده نهایی کیف‌پول = wallet_balance - invoice_debt.
     *
     * wallet_balance running sum تراکنش‌های wallet (شارژ/جایزه/جریمه/...)
     * را نگه می‌دارد ولی سهم شرکت از فاکتورها به‌صورت رخداد wallet ثبت
     * نمی‌شود (هم‌ارز جریان WP). برای دیدن مانده واقعی، سهم شرکت کل
     * فاکتورها از wallet_balance کسر می‌شود.
     *
     * + = شرکت به تکنسین بدهکار / − = تکنسین به شرکت بدهکار.
     */
    public function getTrueBalanceAttribute(): int
    {
        return (int) $this->wallet_balance - $this->invoice_debt;
    }

    /**
     * نام نمایشی — اولویت با firstname_tech (نام تجاری/کامل WP)،
     * fallback به first_name + last_name (داده‌ی legacy لاراولی)،
     * در نهایت موبایل.
     */
    public function getFullNameAttribute(): string
    {
        $tech = trim((string) ($this->firstname_tech ?? ''));
        if ($tech !== '') {
            return $tech;
        }

        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : (string) $this->mobile;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'active')->where('ready_for_delivery', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('firstname_tech', 'like', "%{$term}%")
                ->orWhere('technician_id', 'like', "%{$term}%")
                ->orWhere('national_code', 'like', "%{$term}%")
                ->orWhere('specialty', 'like', "%{$term}%");
        });
    }

    /** Push تغییرات به WP — همانند Order. */
    protected static function booted(): void
    {
        $push = function (self $t) {
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
                return;
            }
            if (app()->bound('crm.suppress_outbound_push')) {
                return;
            }
            try {
                app(\Modules\CRM\Services\WpPushService::class)->pushTechnician($t);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('crm.wp_push.technician_failed', [
                    'id' => $t->id, 'error' => $e->getMessage(),
                ]);
            }
        };
        static::created($push);
        static::updated($push);
    }
}
