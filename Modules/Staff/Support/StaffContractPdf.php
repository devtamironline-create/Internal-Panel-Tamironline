<?php

namespace Modules\Staff\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Staff\Models\StaffContract;

/**
 * تولیدِ نسخهٔ PDF نهاییِ قرارداد (فارسی/RTL با فونت وزیر) شاملِ امضای
 * الکترونیکِ کارمند و مهر و امضای مدیریت.
 *
 * متنِ قرارداد از همان Blade ای می‌آید که در پیش‌نمایشِ وب استفاده می‌شود،
 * پس نسخهٔ امضاشده و نسخهٔ نمایش‌داده‌شده هرگز واگرا نمی‌شوند.
 */
final class StaffContractPdf
{
    /** تولید و ذخیرهٔ PDF روی دیسک public؛ مسیرِ ذخیره‌شده برمی‌گردد. */
    public static function store(StaffContract $contract): string
    {
        $binary = self::render($contract);
        $path = 'staff-contracts/'.$contract->id.'/contract-'.$contract->contract_number.'.pdf';

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /** خروجی باینریِ PDF. */
    public static function render(StaffContract $contract): string
    {
        $html = self::html($contract);

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
            'default_font_size' => 9,
            'directionality' => 'rtl',
            'margin_top' => 12,
            'margin_bottom' => 14,
            'margin_left' => 12,
            'margin_right' => 12,
        ]);

        // تصویرِ مهر/امضا اختیاری است — خطای آن نباید تولیدِ قرارداد را بشکند.
        $mpdf->showImageErrors = false;
        $mpdf->SetHTMLFooter('<div style="text-align:center; font-size:7.5pt; color:#777;">
            قرارداد '.e($contract->contract_number).' — صفحه {PAGENO} از {nbpg}</div>');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** HTML کاملِ قرارداد (با استایلِ چاپ) برای mPDF. */
    private static function html(StaffContract $contract): string
    {
        $body = view('staff::contracts._document', [
            'contract' => $contract,
            'party1' => ContractSettings::all(),
            'forPdf' => true,
            'stampSrc' => self::dataUri(ContractSettings::stampFile()),
            'signatureSrc' => self::dataUri(self::diskPath($contract->signature_path)),
        ])->render();

        $css = '
            body { font-family: vazir, sans-serif; font-size: 9pt; line-height: 1.85; color: #111; }
            h1 { font-size: 14pt; }
            h2 { font-size: 10.5pt; margin: 12px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #ddd; }
            p { margin: 0 0 5px; text-align: justify; }
            table { font-size: 8.5pt; }
        ';

        return '<html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    /** مسیرِ مطلقِ فایل روی دیسک public (یا null). */
    private static function diskPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }
        $full = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($full) ? $full : null;
    }

    /**
     * تبدیل فایل تصویر به data-uri — mPDF نباید برای خواندنِ تصویر به
     * شبکه/وب‌سرور وابسته باشد (روی سرورهایی که storage symlink ندارند
     * لینکِ عمومی جواب نمی‌دهد).
     */
    private static function dataUri(?string $absolutePath): ?string
    {
        if (! $absolutePath || ! is_file($absolutePath)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
        if (! $mime) {
            return null;
        }

        $data = @file_get_contents($absolutePath);

        return $data === false ? null : 'data:'.$mime.';base64,'.base64_encode($data);
    }
}
