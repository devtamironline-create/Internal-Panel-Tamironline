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
        $invoice->loadMissing([
            'order.items', 'order.device', 'order.brand', 'order.city', 'order.province', 'customer',
        ]);

        // QR اعتبارسنجی (لینک عمومی رسید). اگر پکیج mpdf/qrcode نصب نباشد یا
        // خطایی رخ دهد، QR حذف می‌شود اما PDF بدون خطا رندر می‌گردد.
        $qrDataUri = self::qr(route('crm.invoice.public', $invoice->invoice_code));

        // نسخهٔ مخصوص PDF — CSSِ ساده و سازگار با mPDF (بدون flex/float/
        // transform/writing-mode/calc که در نسخهٔ HTML باعث runaway صفحات می‌شد).
        $html = view('crm::invoices.print-pdf', compact('invoice', 'qrDataUri'))->render();

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

    /**
     * تولید QR به‌صورت data-URI (PNG). در صورت نبودِ پکیج یا خطا، null.
     */
    private static function qr(string $text): ?string
    {
        try {
            if (! class_exists(\Mpdf\QrCode\QrCode::class)) {
                return null;
            }
            $qr = new \Mpdf\QrCode\QrCode($text);
            $png = (new \Mpdf\QrCode\Output\Png)->output($qr, 180, [255, 255, 255], [0, 0, 0]);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
