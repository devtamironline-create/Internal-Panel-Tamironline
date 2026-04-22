<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * مدیریت کیف‌پول تکنسین — تنها مسیر مجاز برای تغییر موجودی.
 *
 * موجودی تکنسین به‌صورت denormalized روی جدول crm_technicians نگه داشته می‌شود.
 * برای جلوگیری از race condition و ناسازگاری، همه نوشتن‌ها داخل
 * یک transaction و با قفل روی ردیف تکنسین انجام می‌شود.
 */
class WalletService
{
    /**
     * یک تراکنش ثبت می‌کند و موجودی تکنسین را به‌روزرسانی می‌کند.
     *
     * @param  int  $amount  مبلغ علامت‌دار: + = بستانکار تکنسین، - = بدهکار
     */
    public function recordTransaction(
        Technician $technician,
        WalletTxType $type,
        int $amount,
        ?string $note = null,
        ?Order $order = null,
        ?Invoice $invoice = null,
        ?int $createdBy = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($technician, $type, $amount, $note, $order, $invoice, $createdBy) {
            // قفل ردیف تکنسین برای جلوگیری از race
            $locked = Technician::whereKey($technician->id)->lockForUpdate()->first();

            $newBalance = (int) $locked->wallet_balance + $amount;

            $tx = WalletTransaction::create([
                'technician_id' => $locked->id,
                'order_id' => $order?->id,
                'invoice_id' => $invoice?->id,
                'type' => $type->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'note' => $note,
                'created_by' => $createdBy,
            ]);

            $locked->wallet_balance = $newBalance;
            $locked->save();

            return $tx;
        });
    }
}
