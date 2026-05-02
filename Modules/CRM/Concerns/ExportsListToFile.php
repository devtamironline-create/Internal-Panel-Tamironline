<?php

namespace Modules\CRM\Concerns;

use Closure;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trait مشترک خروجی Excel/CSV برای لیست‌های CRM.
 *
 * هر controller index کافی است یک متد export($format) داشته باشد که
 * با this->streamSpreadsheet() قالب فایل را برمی‌گرداند:
 *
 *   $headers = ['کد', 'نام', ...];
 *   $rowsCallback = function () use ($query) {
 *       foreach ($query->lazy(500) as $item) {
 *           yield [$item->code, $item->name, ...];
 *       }
 *   };
 *   return $this->streamSpreadsheet('orders-' . date('Ymd-His'), $format, $headers, $rowsCallback);
 *
 * نکات مهم برای لیست‌های بزرگ (۷۴k+ ردیف):
 * - lazy(500) به‌جای cursor() — با eager loading سازگار است.
 * - افزایش memory_limit و حذف time_limit در ابتدای request export.
 * - CSV مستقیم به output stream با flush مرتب می‌نویسد (memory ثابت).
 * - XLSX از PhpSpreadsheet استفاده می‌کند که کل ردیف‌ها را در RAM
 *   نگه می‌دارد. برای لیست‌های بسیار بزرگ توصیه می‌شود از CSV استفاده
 *   شود — UI هشدار خواهد داد.
 */
trait ExportsListToFile
{
    /**
     * @param  array<int,string>  $headers
     * @param  Closure():iterable  $rowsCallback
     */
    protected function streamSpreadsheet(string $baseFilename, string $format, array $headers, Closure $rowsCallback): StreamedResponse
    {
        $format = strtolower($format);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404, 'Format must be csv or xlsx.');

        // برای لیست‌های بزرگ
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        ignore_user_abort(true);

        $filename = $baseFilename . '.' . $format;

        if ($format === 'csv') {
            return $this->streamCsv($filename, $headers, $rowsCallback);
        }

        return $this->streamXlsx($filename, $headers, $rowsCallback);
    }

    /**
     * CSV — مستقیم به output با flush مرتب. حافظهٔ ثابت حتی روی صدها هزار ردیف.
     *
     * @param  array<int,string>  $headers
     * @param  Closure():iterable  $rowsCallback
     */
    protected function streamCsv(string $filename, array $headers, Closure $rowsCallback): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rowsCallback) {
            // پاک‌سازی هرگونه output buffer قبلی تا header های HTTP خراب نشوند
            while (ob_get_level()) {
                @ob_end_clean();
            }

            // BOM برای UTF-8 تا Excel فارسی را درست نشان دهد
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            $i = 0;
            foreach ($rowsCallback() as $row) {
                fputcsv($out, array_map(fn ($v) => $this->stringifyCell($v), $row));
                $i++;
                // هر ۵۰۰ ردیف flush تا خروجی streaming واقعی باشد
                if ($i % 500 === 0) {
                    @fflush($out);
                    @flush();
                }
            }
            @fflush($out);
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'X-Accel-Buffering'   => 'no',  // disable nginx buffering
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * XLSX — از PhpSpreadsheet. روی لیست‌های بزرگ کنترل خطا انجام می‌دهد
     * تا اگر memory exhaust شد، پیام مفیدی به جای ۵۰۰ خام برگردد.
     *
     * @param  array<int,string>  $headers
     * @param  Closure():iterable  $rowsCallback
     */
    protected function streamXlsx(string $filename, array $headers, Closure $rowsCallback): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rowsCallback) {
            while (ob_get_level()) {
                @ob_end_clean();
            }

            try {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setRightToLeft(true);

                $colCount = count($headers);
                $lastCol = Coordinate::stringFromColumnIndex($colCount);

                // هدر
                foreach ($headers as $i => $h) {
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->setCellValue($col . '1', (string) $h);
                }
                $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $row = 2;
                foreach ($rowsCallback() as $values) {
                    $colIdx = 1;
                    foreach ($values as $v) {
                        $col = Coordinate::stringFromColumnIndex($colIdx++);
                        $sheet->setCellValueExplicit(
                            $col . $row,
                            $this->stringifyCell($v),
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    }
                    $row++;
                }

                // autoSize برای لیست‌های خیلی بزرگ کند است — فقط اگر < 10k ردیف
                if ($row < 10000) {
                    for ($i = 1; $i <= $colCount; $i++) {
                        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                    }
                }

                $writer = new XlsxWriter($spreadsheet);
                $writer->save('php://output');

                // آزادسازی حافظه
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $writer);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('XLSX export failed', [
                    'filename' => $filename,
                    'error'    => $e->getMessage(),
                ]);
                // در stream نمی‌توان وضعیت HTTP عوض کرد؛ پیام در body
                echo "خطا در ساخت فایل اکسل: " . $e->getMessage() . "\n"
                    . "برای لیست‌های بزرگ از خروجی CSV استفاده کنید.";
            }
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Accel-Buffering'   => 'no',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** تبدیل مقدار به string برای cell — تاریخ‌ها به شمسی، آرایه به json. */
    protected function stringifyCell($value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            try {
                return \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d H:i');
            } catch (\Throwable $e) {
                return $value->format('Y-m-d H:i');
            }
        }
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $value;
    }
}
