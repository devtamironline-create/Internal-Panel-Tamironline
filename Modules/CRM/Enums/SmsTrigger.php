<?php

namespace Modules\CRM\Enums;

enum SmsTrigger: string
{
    case OrderCreated = 'order_created';
    case OrderAssigned = 'order_assigned';           // به مشتری
    case OrderAssignedTech = 'order_assigned_tech';  // به تکنسین
    case OrderInProgress = 'order_in_progress';
    case OrderCompleted = 'order_completed';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'ثبت سفارش',
            self::OrderAssigned => 'تخصیص تکنسین (به مشتری)',
            self::OrderAssignedTech => 'تخصیص سفارش (به تکنسین)',
            self::OrderInProgress => 'شروع انجام',
            self::OrderCompleted => 'تکمیل سفارش',
            self::OrderDelivered => 'تحویل',
            self::OrderCancelled => 'لغو سفارش',
        };
    }

    public static function fromOrderStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            // هم‌ارز WP: وقتی تکنسین (یا اپراتور با دسترسی) سفارش را به
            // «هماهنگ شده» تغییر می‌دهد، مشتری پیامک «تخصیص تکنسین/زمان
            // مراجعه» را دریافت می‌کند — نه هنگام تخصیص اولیه.
            OrderStatus::Coordinated => self::OrderAssigned,
            OrderStatus::Open => self::OrderInProgress,
            OrderStatus::Completed => self::OrderCompleted,
            OrderStatus::Cancelled => self::OrderCancelled,
            default => null,
        };
    }
}
