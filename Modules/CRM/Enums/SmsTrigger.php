<?php

namespace Modules\CRM\Enums;

enum SmsTrigger: string
{
    // ── سفارش (مشتری/تکنسین) ─────────────────────────────────
    case OrderCreated = 'order_created';
    case OrderAssigned = 'order_assigned';           // به مشتری
    case OrderAssignedTech = 'order_assigned_tech';  // به تکنسین
    case OrderInProgress = 'order_in_progress';
    case OrderCompleted = 'order_completed';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';

    // ── تکنسین (مالی/کنسل/فاکتور) ────────────────────────────
    case TechWalletCharged = 'tech_wallet_charged';
    case TechPaymentReceived = 'tech_payment_received';
    case TechPenaltyApplied = 'tech_penalty_applied';
    case TechOrderCancelled = 'tech_order_cancelled';
    case TechInvoiceIssued = 'tech_invoice_issued';

    // ── مشتری (فاکتور/Happy Call) ────────────────────────────
    case CustomerInvoiceIssued = 'customer_invoice_issued';
    case CustomerHappyCall = 'customer_happy_call';
    case CustomerInvoicePayLink = 'customer_invoice_pay_link';
    case CustomerProformaIssued = 'customer_proforma_issued';  // پیش‌فاکتور

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
            self::TechWalletCharged => 'شارژ کیف‌پول (تکنسین)',
            self::TechPaymentReceived => 'پرداخت/حقوق (تکنسین)',
            self::TechPenaltyApplied => 'جریمه (تکنسین)',
            self::TechOrderCancelled => 'کنسل سفارش (تکنسین)',
            self::TechInvoiceIssued => 'صدور فاکتور (تأیید تکنسین)',
            self::CustomerInvoiceIssued => 'صدور فاکتور (مشتری)',
            self::CustomerHappyCall => 'رضایت‌سنجی (مشتری)',
            self::CustomerInvoicePayLink => 'لینک پرداخت فاکتور (مشتری)',
            self::CustomerProformaIssued => 'صدور پیش‌فاکتور (مشتری)',
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
            // تکمیل عمداً اینجا نیست: موقعِ تکمیل، فاکتور ساخته می‌شود و
            // پیامکِ «صدور فاکتور (مشتری)» با لینکِ امنِ public_token می‌رود.
            // اگر order_completed هم اینجا برگردد، مشتری پیامکِ تکراری
            // (و با لینکِ ناامن/قابل‌حدس) می‌گیرد. پس فقط همان یک پیامک.
            OrderStatus::Cancelled => self::OrderCancelled,
            default => null,
        };
    }
}
