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

        // ثبت فونتِ وزیر (فایل‌های .ttf در storage/fonts) به‌عنوان فونتِ پیش‌فرض
        // تا فاکتور دقیقاً با فونتِ برند رندر شود.
        $fontDirs = (new \Mpdf\Config\ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables)->getDefaults()['fontdata'];

        // اگر حاشیهٔ تذهیب موجود است، حاشیهٔ چپ/راست بازتر می‌شود تا محتوا
        // روی نوارهای تزئینیِ لبه نیفتد.
        $side = is_file(public_path('images/invoice/tazhib.png')) ? 19 : 8;

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'fontDir' => array_merge($fontDirs, [storage_path('fonts')]),
            'fontdata' => $fontData + [
                'vazir' => [
                    'R' => 'Vazir.ttf',
                    'B' => 'Vazir-Bold.ttf',
                    'useOTL' => 0xFF,     // shaping صحیح فارسی/عربی
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'vazir',
            'default_font_size' => 9.5,
            'directionality' => 'rtl',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => $side,
            'margin_right' => $side,
        ]);
        // تصاویرِ راه‌دور (لوگو/مهر) اختیاری‌اند — خطایشان فاکتور را نشکند.
        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        // عناصرِ تزئینی (تذهیبِ دو لبه + هولوگرامِ پایین‌وسط) را با API مستقیمِ
        // mPDF می‌کشیم — مطمئن‌تر از position:absolute در HTML. اختیاری‌اند.
        try {
            $tazhib = public_path('images/invoice/tazhib.png');
            if (is_file($tazhib)) {
                $mpdf->Image($tazhib, 0, 0, 15, 297, 'png', '', true, false);    // لبهٔ چپ
                $mpdf->Image($tazhib, 195, 0, 15, 297, 'png', '', true, false);  // لبهٔ راست
            }
            $holo = public_path('images/invoice/hologram.png');
            if (is_file($holo)) {
                $mpdf->Image($holo, 88, 247, 34, 34, 'png', '', true, false);    // پایین‌وسط
            }
        } catch (\Throwable $e) {
            // عناصرِ تزئینی اختیاری‌اند.
        }

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
