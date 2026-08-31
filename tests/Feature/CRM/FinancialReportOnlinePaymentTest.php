<?php

namespace Tests\Feature\CRM;

use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Http\Controllers\Reports\FinancialReportController;
use Tests\TestCase;

/**
 * پرداختِ آنلاینِ مشتری (ورودیِ کیف‌پولِ تکنسین) باید در گزارش مالی
 * دیده شود: هم در فهرستِ اسناد (نوعِ سند) و هم به‌عنوان یک باکسِ جمع‌بندی.
 */
class FinancialReportOnlinePaymentTest extends TestCase
{
    private function invoke(string $method, ...$args)
    {
        $c = app(FinancialReportController::class);
        $ref = new \ReflectionMethod($c, $method);
        $ref->setAccessible(true);

        return $ref->invoke($c, ...$args);
    }

    public function test_online_payment_is_included_in_the_document_rows(): void
    {
        // فیلترِ «همه» باید پرداختِ آنلاین را هم شامل شود.
        $all = $this->invoke('txTypesForDocFilter', '');
        $this->assertContains(WalletTxType::OnlinePayment->value, $all);

        // فیلترِ اختصاصیِ «پرداخت آنلاین مشتری».
        $this->assertSame(
            [WalletTxType::OnlinePayment->value],
            $this->invoke('txTypesForDocFilter', 'online_payment')
        );
    }

    public function test_online_payment_is_a_selectable_document_type(): void
    {
        $options = $this->invoke('docTypeOptions');
        $this->assertArrayHasKey('online_payment', $options);
        $this->assertSame('پرداخت آنلاین مشتری', $options['online_payment']);
    }
}
