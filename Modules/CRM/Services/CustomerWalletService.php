<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\CustomerWalletTxType;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerWalletTransaction;
use Modules\CRM\Models\Order;

/**
 * مدیریتِ کیف‌پولِ مشتری — تنها مسیرِ مجاز برای تغییرِ موجودی.
 *
 * موجودی به‌صورتِ denormalized روی crm_customers.wallet_balance نگه داشته
 * می‌شود؛ هر نوشتن داخلِ یک transaction و با قفلِ ردیفِ مشتری انجام می‌شود تا
 * race و ناسازگاری رخ ندهد. موجودی هرگز منفی نمی‌شود (گاردِ کسری).
 */
class CustomerWalletService
{
    /**
     * یک تراکنش ثبت و موجودیِ مشتری را به‌روزرسانی می‌کند.
     *
     * @param  int  $amount  مبلغِ علامت‌دار: + = بستانکارِ مشتری، − = کسر
     *
     * @throws ValidationException وقتی کسر بیش از موجودیِ فعلی باشد.
     */
    public function record(
        Customer $customer,
        CustomerWalletTxType $type,
        int $amount,
        ?string $note = null,
        ?Order $order = null,
        ?int $invoiceId = null,
        ?int $createdBy = null,
        ?array $meta = null,
    ): CustomerWalletTransaction {
        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => 'مبلغِ تراکنش نمی‌تواند صفر باشد.']);
        }

        return DB::transaction(function () use ($customer, $type, $amount, $note, $order, $invoiceId, $createdBy, $meta) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->first();

            $newBalance = (int) $locked->wallet_balance + $amount;
            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'موجودیِ کیف‌پول کافی نیست.',
                ]);
            }

            $tx = CustomerWalletTransaction::create([
                'customer_id' => $locked->id,
                'order_id' => $order?->id,
                'invoice_id' => $invoiceId,
                'type' => $type->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'note' => $note,
                'meta' => $meta,
                'created_by' => $createdBy,
            ]);

            $locked->wallet_balance = $newBalance;
            $locked->save();

            return $tx;
        });
    }

    /** واریز (مبلغِ مثبت). */
    public function credit(Customer $customer, CustomerWalletTxType $type, int $amount, array $opts = []): CustomerWalletTransaction
    {
        return $this->record($customer, $type, abs($amount), ...$this->opts($opts));
    }

    /** برداشت/کسر (مبلغِ منفی). */
    public function debit(Customer $customer, CustomerWalletTxType $type, int $amount, array $opts = []): CustomerWalletTransaction
    {
        return $this->record($customer, $type, -abs($amount), ...$this->opts($opts));
    }

    /** @return array{0:?string,1:?Order,2:?int,3:?int,4:?array} */
    private function opts(array $o): array
    {
        return [
            $o['note'] ?? null,
            $o['order'] ?? null,
            $o['invoice_id'] ?? null,
            $o['created_by'] ?? null,
            $o['meta'] ?? null,
        ];
    }
}
