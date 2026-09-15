<?php

namespace Modules\CRM\Enums;

/**
 * نوعِ تراکنشِ کیف‌پولِ مشتری. جدا از WalletTxTypeِ تکنسین است چون دامنهٔ
 * متفاوتی دارد (شارژ، پرداختِ فاکتور، پاداشِ معرف، برداشت).
 */
enum CustomerWalletTxType: string
{
    case Topup = 'topup';                        // شارژ از درگاه (+)
    case Payment = 'payment';                    // پرداختِ فاکتور از کیف‌پول (-)
    case ReferralReward = 'referral_reward';     // پاداشِ معرف (+)
    case Withdrawal = 'withdrawal';              // برداشتِ وجه (-)
    case WithdrawalRefund = 'withdrawal_refund'; // بازگشتِ برداشتِ ردشده (+)
    case Adjustment = 'adjustment';              // تعدیلِ دستیِ ادمین (+/-)

    public function label(): string
    {
        return match ($this) {
            self::Topup => 'شارژ کیف‌پول',
            self::Payment => 'پرداخت فاکتور',
            self::ReferralReward => 'پاداش معرف',
            self::Withdrawal => 'برداشت وجه',
            self::WithdrawalRefund => 'بازگشت برداشت',
            self::Adjustment => 'تعدیل',
        };
    }

    /** علامتِ قراردادی — برای Adjustment علامت را خودِ مبلغ تعیین می‌کند. */
    public function sign(): int
    {
        return match ($this) {
            self::Topup, self::ReferralReward, self::WithdrawalRefund => +1,
            self::Payment, self::Withdrawal => -1,
            self::Adjustment => 0,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Topup => 'bg-teal-100 text-teal-800',
            self::ReferralReward => 'bg-emerald-100 text-emerald-800',
            self::WithdrawalRefund => 'bg-green-100 text-green-800',
            self::Payment => 'bg-sky-100 text-sky-800',
            self::Withdrawal => 'bg-amber-100 text-amber-800',
            self::Adjustment => 'bg-gray-100 text-gray-800',
        };
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }
}
