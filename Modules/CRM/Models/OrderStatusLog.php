<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Enums\OrderStatus;

/**
 * تاریخچهٔ وضعیت سفارش — append-only.
 *
 * نامِ عاملِ رویداد (actor_name/actor_role/actor_technician_id) در لحظهٔ
 * ثبت snapshot می‌شود، نه اینکه هنگام نمایش از رابطه خوانده شود. دلیل:
 * سفارش ممکن است بعداً به تکنسین دیگری داده شود یا پروفایل تکنسینِ قبلی
 * تغییر/حذف شود؛ تاریخچه باید همچنان بگوید کارِ قبلی را چه کسی انجام
 * داده.
 */
class OrderStatusLog extends Model
{
    protected $table = 'crm_order_status_logs';

    // Log is append-only; only created_at is tracked
    public $timestamps = false;

    /** برچسبِ فارسیِ نقشِ عامل. */
    public const ROLE_LABELS = [
        'technician' => 'تکنسین',
        'operator' => 'اپراتور',
        'customer' => 'مشتری',
        'system' => 'سیستم',
    ];

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
        'actor_name',
        'actor_role',
        'actor_technician_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** نگاشتِ user_id ← تکنسین، برای پرهیز از کوئری تکراری در یک درخواست. */
    private static array $technicianByUser = [];

    protected static function booted(): void
    {
        // همهٔ نقاطِ ساختِ لاگ در ماژول‌ها از این هوک عبور می‌کنند، پس هیچ
        // مسیری بدون نامِ عامل ثبت نمی‌شود و لازم نیست ۲۴ فراخوانی
        // پراکنده را تک‌تک تغییر دهیم.
        static::creating(function (self $log) {
            if (filled($log->actor_name)) {
                return;
            }

            $actor = self::resolveActor($log->changed_by ? (int) $log->changed_by : null);

            $log->actor_name = $actor['name'];
            $log->actor_role = $actor['role'];
            $log->actor_technician_id = $actor['technician_id'];
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actorTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'actor_technician_id');
    }

    public function actorRoleLabel(): ?string
    {
        return $this->actor_role ? (self::ROLE_LABELS[$this->actor_role] ?? $this->actor_role) : null;
    }

    /**
     * نامِ نمایشیِ عامل — snapshot اگر موجود باشد، وگرنه fallback به رابطه
     * (برای رکوردهایی که به هر دلیل backfill نشده‌اند).
     */
    public function actorLabel(): ?string
    {
        if (filled($this->actor_name)) {
            return $this->actor_name;
        }

        return $this->changer?->full_name;
    }

    public function isByTechnician(): bool
    {
        return $this->actor_role === 'technician';
    }

    public function fromLabel(): ?string
    {
        if (! $this->from_status) {
            return null;
        }

        return OrderStatus::tryFrom($this->from_status)?->label() ?? $this->from_status;
    }

    public function toLabel(): string
    {
        return OrderStatus::tryFrom($this->to_status)?->label() ?? $this->to_status;
    }

    /** پاک‌کردنِ کشِ درون-درخواستی (برای تست‌ها). */
    public static function flushActorCache(): void
    {
        self::$technicianByUser = [];
    }

    /**
     * تشخیصِ عامل: اول از روی changed_by، بعد از روی گاردِ فعال.
     *
     * @return array{name:string, role:string, technician_id:?int}
     */
    private static function resolveActor(?int $changedBy): array
    {
        if ($changedBy) {
            $technician = self::technicianOfUser($changedBy);
            if ($technician) {
                return [
                    'name' => self::technicianName($technician),
                    'role' => 'technician',
                    'technician_id' => (int) $technician->id,
                ];
            }

            $user = User::query()->find($changedBy);
            $name = $user ? trim((string) $user->full_name) : '';

            return [
                'name' => $name !== '' ? $name : 'کاربر #'.$changedBy,
                'role' => 'operator',
                'technician_id' => null,
            ];
        }

        // بدون changed_by — ممکن است از پنل/اپ تکنسین آمده باشد.
        $technician = self::currentTechnician();
        if ($technician) {
            return [
                'name' => self::technicianName($technician),
                'role' => 'technician',
                'technician_id' => (int) $technician->id,
            ];
        }

        $user = self::currentUser();
        if ($user) {
            $name = trim((string) $user->full_name);

            return [
                'name' => $name !== '' ? $name : 'کاربر #'.$user->id,
                'role' => 'operator',
                'technician_id' => null,
            ];
        }

        // سینک ووکامرس/وردپرس، کران، کامندها و پخشِ خودکار.
        return ['name' => 'سیستم', 'role' => 'system', 'technician_id' => null];
    }

    private static function technicianOfUser(int $userId): ?Technician
    {
        if (array_key_exists($userId, self::$technicianByUser)) {
            return self::$technicianByUser[$userId];
        }

        return self::$technicianByUser[$userId] = Technician::query()
            ->where('user_id', $userId)
            ->first(['id', 'first_name', 'firstname_tech', 'user_id']);
    }

    private static function currentTechnician(): ?Technician
    {
        try {
            $tech = Auth::guard('tech')->user();
            if ($tech instanceof Technician) {
                return $tech;
            }

            // API تکنسین روی گاردِ پیش‌فرض/سنکتوم می‌نشیند.
            $any = Auth::user();

            return $any instanceof Technician ? $any : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function currentUser(): ?User
    {
        try {
            $user = Auth::user();

            return $user instanceof User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function technicianName(Technician $technician): string
    {
        $name = trim((string) ($technician->firstname_tech ?: $technician->first_name));

        return $name !== '' ? $name : 'تکنسین #'.$technician->id;
    }
}
