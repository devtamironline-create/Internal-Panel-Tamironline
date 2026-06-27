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
     * @return string محتوای باینریِ PDF
     */
    public static function render(Invoice $invoice): string
    {
        // پردازشِ تصاویرِ تزئینیِ بزرگ (تذهیب/هولوگرام) می‌تواند حافظه‌بر باشد.
        @ini_set('memory_limit', '1024M');

        $invoice->loadMissing([
            'order.items', 'order.device', 'order.brand', 'order.city', 'order.province', 'customer',
        ]);

        // QR اعتبارسنجی (لینک عمومی رسید).
        $qrDataUri = self::qr(route('crm.invoice.public', $invoice->public_token));

        // ۱) رندرِ دقیقِ طرح با Browsershot (Chrome) — همان HTML/CSS کامل
        // (flex/gradient/…). اگر Chrome/Node در دسترس نباشد، به mPDF می‌افتیم.
        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            try {
                $html = view('crm::invoices.print-browser', compact('invoice', 'qrDataUri'))->render();

                $shot = \Spatie\Browsershot\Browsershot::html($html)
                    ->format('A4')
                    ->showBackground()
                    ->margins(0, 0, 0, 0)
                    ->noSandbox();

                // زیرِ کاربرِ وب (php-fpm) معمولاً node/chrome در PATH نیستند؛
                // اگر مسیرشان در .env تعریف شده باشد، صراحتاً ست می‌کنیم تا
                // Browsershot به‌جای fallback، واقعاً اجرا شود.
                if ($node = env('BROWSERSHOT_NODE_BINARY')) {
                    $shot->setNodeBinary($node);
                }
                if ($npm = env('BROWSERSHOT_NPM_BINARY')) {
                    $shot->setNpmBinary($npm);
                }
                if ($chrome = env('BROWSERSHOT_CHROME_PATH')) {
                    $shot->setChromePath($chrome);
                }
                if ($include = env('BROWSERSHOT_INCLUDE_PATH')) {
                    $shot->setIncludePath($include);
                }

                $pdf = base64_decode((string) $shot->base64pdf());
                if ($pdf !== '') {
                    return $pdf;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Invoice Browsershot failed; fallback to mPDF: '.$e->getMessage());
            }
        }

        // ۲) fallback: mPDF.
        return self::renderMpdf($invoice, $qrDataUri);
    }

    /**
     * fallback با mPDF — نسخهٔ سازگار (بدون flex/gradient).
     */
    private static function renderMpdf(Invoice $invoice, ?string $qrDataUri): string
    {
        // نسخهٔ مخصوص PDF — CSSِ ساده و سازگار با mPDF.
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

        // عناصرِ تزئینی را با API مستقیمِ mPDF می‌کشیم (تذهیبِ دو لبه + هولوگرامِ
        // پایین‌وسط). هرکدام در try/catch مستقل تا خطای یک تصویر، بقیه و کلِ
        // فاکتور را نشکند. [rel, x, y, w, h] برحسب میلی‌متر.
        foreach ([
            ['images/invoice/tazhib.png',   0,   0, 15, 297],
            ['images/invoice/tazhib.png', 195,   0, 15, 297],
            ['images/invoice/hologram.png', 90, 244, 30, 30],
        ] as [$rel, $x, $y, $w, $h]) {
            try {
                $p = public_path($rel);
                if (is_file($p)) {
                    $mpdf->Image($p, $x, $y, $w, $h, '', '', true, false);
                }
            } catch (\Throwable $e) {
                // تصاویرِ تزئینی اختیاری‌اند.
            }
        }

        return (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * تولید QR به‌صورت data-URI (PNG). در صورت نبودِ پکیج یا خطا، null.
     */
    public static function qr(string $text): ?string
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
