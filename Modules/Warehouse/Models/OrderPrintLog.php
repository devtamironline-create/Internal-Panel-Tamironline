<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPrintLog extends Model
{
    protected $fillable = [
        'woo_order_id',
        'user_id',
        'print_type',
        'ip_address',
        'user_agent',
        'was_duplicate',
        'manager_notified',
    ];

    protected $casts = [
        'was_duplicate' => 'boolean',
        'manager_notified' => 'boolean',
    ];

    const TYPE_INVOICE = 'invoice';
    const TYPE_AMADAST = 'amadast';

    public static function getPrintTypes(): array
    {
        return [
            self::TYPE_INVOICE => 'فاکتور سفارش',
            self::TYPE_AMADAST => 'برچسب آمادست',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class, 'woo_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPrintTypeLabelAttribute(): string
    {
        return self::getPrintTypes()[$this->print_type] ?? $this->print_type;
    }

    /**
     * Get count of prints by user for a specific order
     */
    public static function getUserPrintCount(int $orderId, int $userId, ?string $printType = null): int
    {
        $query = self::where('woo_order_id', $orderId)
            ->where('user_id', $userId);

        if ($printType) {
            $query->where('print_type', $printType);
        }

        return $query->count();
    }

    /**
     * Check if this is a duplicate print for user
     */
    public static function isDuplicateForUser(int $orderId, int $userId, ?string $printType = null): bool
    {
        return self::getUserPrintCount($orderId, $userId, $printType) > 0;
    }
}
