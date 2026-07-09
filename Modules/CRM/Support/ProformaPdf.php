<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\Proforma;

/**
 * رندرِ رسیدِ پیش‌فاکتور به فایلِ واقعیِ PDF.
 *
 * برخلافِ فاکتور، این خروجی عمداً **بدونِ مهر، امضا، QR و هولوگرام** است و
 * یک واترمارکِ پس‌زمینه «غیرقابل استناد» دارد (پیش‌فاکتور سندِ رسمی نیست).
 */
final class ProformaPdf
{
    /**
     * @return string محتوای باینریِ PDF
     */
    public static function render(Proforma $proforma): string
    {
        @ini_set('memory_limit', '512M');

        $proforma->loadMissing(['order.city', 'order.province']);

        // ۱) مسیرِ اصلی: Browsershot (Chrome) با همان HTMLِ تمیزِ رسید
        // (واترمارکِ «غیرقابل استناد» به‌صورتِ CSS داخلِ خودِ قالب است).
        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            try {
                $html = view('crm::proformas.print', ['proforma' => $proforma, 'provider' => self::provider(), 'pdfMode' => true])->render();

                $shot = \Spatie\Browsershot\Browsershot::html($html)
                    ->format('A4')
                    ->showBackground()
                    ->margins(6, 6, 6, 6)
                    ->noSandbox();

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
                \Illuminate\Support\Facades\Log::warning('Proforma Browsershot failed; fallback to mPDF: '.$e->getMessage());
            }
        }

        // ۲) fallback: mPDF — قالبِ ساده‌ی سازگار + واترمارکِ متنی (بدونِ هیچ مهر/هولوگرام).
        return self::renderMpdf($proforma);
    }

    private static function renderMpdf(Proforma $proforma): string
    {
        $html = view('crm::proformas.print-pdf', ['proforma' => $proforma])->render();

        $tmp = storage_path('app/mpdf-tmp');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $fontDirs = (new \Mpdf\Config\ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables)->getDefaults()['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'fontDir' => array_merge($fontDirs, [storage_path('fonts')]),
            'fontdata' => $fontData + [
                'vazir' => [
                    'R' => 'Vazir.ttf',
                    'B' => 'Vazir-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'vazir',
            'default_font_size' => 9.5,
            'directionality' => 'rtl',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
        ]);
        $mpdf->showImageErrors = false;

        // واترمارکِ متنیِ «غیرقابل استناد» — پس‌زمینه، بدونِ هیچ مهر/هولوگرام.
        $mpdf->SetWatermarkText('غیرقابل استناد');
        $mpdf->showWatermarkText = true;
        $mpdf->watermarkTextAlpha = 0.08;
        $mpdf->watermark_font = 'vazir';

        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * @return array<string, mixed>
     */
    private static function provider(): array
    {
        return [
            'name' => \Modules\CRM\Models\CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین'),
            'tagline' => \Modules\CRM\Models\CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی'),
            'phone' => \Modules\CRM\Models\CrmSetting::get('invoice_provider_phone', ''),
            'address' => \Modules\CRM\Models\CrmSetting::get('invoice_provider_address', ''),
        ];
    }
}
