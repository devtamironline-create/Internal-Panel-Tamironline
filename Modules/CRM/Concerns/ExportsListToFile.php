<?php

namespace Modules\CRM\Concerns;

use Closure;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
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
 *       foreach ($query->cursor() as $item) {
 *           yield [$item->code, $item->name, ...];
 *       }
 *   };
 *   return $this->streamSpreadsheet('orders-' . date('Ymd-His'), $format, $headers, $rowsCallback);
 *
 * - cursor() برای جلوگیری از حافظهٔ زیاد روی لیست‌های بزرگ.
 * - راست‌چین‌سازی هدر و فعال‌کردن BOM روی CSV (تا اکسل فارسی درست بخواند).
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

        $filename = $baseFilename . '.' . $format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rowsCallback) {
                // BOM برای UTF-8 تا Excel فارسی را درست نشان دهد
                echo "\xEF\xBB\xBF";
                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);
                foreach ($rowsCallback() as $row) {
                    fputcsv($out, array_map(fn ($v) => $this->stringifyCell($v), $row));
                }
                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        // XLSX
        return response()->streamDownload(function () use ($headers, $rowsCallback) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setRightToLeft(true);

            // هدر
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i); // A, B, C...
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')
                ->getFont()->setBold(true);
            $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row = 2;
            foreach ($rowsCallback() as $values) {
                foreach ($values as $i => $v) {
                    $col = chr(65 + $i);
                    $sheet->setCellValue($col . $row, $this->stringifyCell($v));
                }
                $row++;
            }

            // عرض ستون auto
            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension(chr(65 + $i))->setAutoSize(true);
            }

            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
