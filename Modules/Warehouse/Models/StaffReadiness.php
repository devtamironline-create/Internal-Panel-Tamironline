<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffReadiness extends Model
{
    protected $table = 'warehouse_staff_readiness';

    protected $fillable = [
        'user_id',
        'date',
        'is_ready',
        'ready_at',
    ];

    protected $casts = [
        'date' => 'date',
        'is_ready' => 'boolean',
        'ready_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * کاربران آماده امروز
     */
    public static function todayReadyUserIds(): array
    {
        return self::where('date', today())
            ->where('is_ready', true)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * آیا کاربر امروز آماده است؟
     */
    public static function isUserReadyToday(int $userId): bool
    {
        return self::where('user_id', $userId)
            ->where('date', today())
            ->where('is_ready', true)
            ->exists();
    }

    /**
     * ثبت آمادگی کاربر برای امروز
     */
    public static function markReady(int $userId): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'date' => today()],
            ['is_ready' => true, 'ready_at' => now()]
        );
    }

    /**
     * لغو آمادگی کاربر برای امروز
     */
    public static function markNotReady(int $userId): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'date' => today()],
            ['is_ready' => false]
        );
    }

    /**
     * لیست کاربران آماده امروز با اطلاعات کاربر
     */
    public static function todayReadyStaff()
    {
        return self::with('user')
            ->where('date', today())
            ->where('is_ready', true)
            ->get();
    }
}
