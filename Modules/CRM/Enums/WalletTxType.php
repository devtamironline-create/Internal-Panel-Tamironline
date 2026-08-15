<?php

namespace Modules\CRM\Enums;

enum WalletTxType: string
{
    case Commission = 'commission';   // کمیسیون از سفارش (+)
    case Reward = 'reward';           // جایزه (+)
    case WalletCharge = 'wallet_charge'; // شارژ کیف‌پول (+) — هم‌ارز wallet=1 در WP
    // پرداخت آنلاین مشتری (+): کل مبلغ فاکتور که مشتری از درگاه پرداخته.
    // چون هنگام صدور فاکتور −سهم شرکت کم شده، برآیندِ این دو دقیقاً
    // +سهم تکنسین می‌شود — تکنسینی که پولی از مشتری دستش نیست.
    case OnlinePayment = 'online_payment';
    case Penalty = 'penalty';         // جریمه (-)
    case Payout = 'payout';           // پرداخت به تکنسین (-)
    case Credit = 'credit';           // واریز دستی تکنسین به شرکت (-)
    case Adjustment = 'adjustment';   // تعدیل دستی (+/-)

    public function label(): string
    {
        return match ($this) {
            self::Commission => 'کمیسیون',
            self::Reward => 'جایزه',
            self::WalletCharge => 'شارژ کیف‌پول',
            self::OnlinePayment => 'پرداخت آنلاین مشتری',
            self::Penalty => 'جریمه',
            self::Payout => 'پرداخت به تکنسین',
            self::Credit => 'واریز تکنسین',
            self::Adjustment => 'تعدیل',
        };
    }

    public function sign(): int
    {
        return match ($this) {
            self::Commission, self::Reward, self::WalletCharge, self::OnlinePayment => +1,
            self::Penalty, self::Payout, self::Credit => -1,
            self::Adjustment => 0, // علامت توسط خود کاربر تعیین می‌شود
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Commission => 'bg-green-100 text-green-800',
            self::Reward => 'bg-emerald-100 text-emerald-800',
            self::WalletCharge => 'bg-teal-100 text-teal-800',
            self::OnlinePayment => 'bg-cyan-100 text-cyan-800',
            self::Penalty => 'bg-red-100 text-red-800',
            self::Payout => 'bg-blue-100 text-blue-800',
            self::Credit => 'bg-indigo-100 text-indigo-800',
            self::Adjustment => 'bg-gray-100 text-gray-800',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $c) {
            $options[$c->value] = $c->label();
        }

        return $options;
    }
}
