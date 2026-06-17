<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\Invoice;

/**
 * رندرِ رسیدِ فاکتور (crm::invoices.print) به‌صورت فایلِ واقعیِ PDF با mPDF.
 * هم در لینکِ عمومی رسید و هم در API اپ مشتری استفاده می‌شود تا خروجی
 * یکسانِ application/pdf بدهند.
 */
final class InvoicePdf
{
    /**
     * @return string  محتوای باینریِ PDF
     */
    public static function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['order.items', 'customer']);

        $html = view('crm::invoices.print', compact('invoice'))->render();

        $tmp = storage_path('app/mpdf-tmp');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',  // پشتیبانی از اسکریپت فارسی/عربی
            'default_font_size' => 10,
            'directionality' => 'rtl',
            'tempDir' => $tmp,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);
        // تصاویرِ راه‌دور (لوگو/مهر) اختیاری‌اند — خطایشان فاکتور را نشکند.
        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }
}
