<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ActivityLog\ActivityLogReader;
use App\Support\ActivityLog\ActivityRegistry;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * گزارشِ فعالیت — نمایش و فیلترِ اکشن‌های ثبت‌شده در فایل‌های روزانه.
 * فقط مدیرِ کل (manage-permissions) دسترسی دارد.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityLogReader $reader)
    {
        $filters = $this->filters($request);

        $result = $reader->search($filters, (int) $request->integer('page', 1), 50);

        return view('admin.activity-log.index', [
            'filters' => $filters,
            'items' => $result['items'],
            'stats' => $result['stats'],
            'truncated' => $result['truncated'],
            'scanned' => $result['scanned'],
            'actions' => ActivityRegistry::ACTIONS,
            'entities' => ActivityRegistry::labelsForFilter(),
            'users' => $reader->knownUsers(),
            'availableDates' => $reader->availableDates(),
            'retentionDays' => (int) config('activity-log.retention_days', 15),
            'totalSize' => $reader->totalSize(),
        ]);
    }

    /** خروجی CSV از همان نتیجهٔ فیلترشده (سازگار با Excel فارسی). */
    public function export(Request $request, ActivityLogReader $reader): StreamedResponse
    {
        $filters = $this->filters($request);
        // برای خروجی، همهٔ ردیف‌های منطبق در یک صفحهٔ بزرگ گرفته می‌شوند.
        $result = $reader->search($filters, 1, max(1, (int) config('activity-log.max_scan', 5000)));

        $filename = 'activity-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM برای نمایش صحیح فارسی در Excel
            fputcsv($out, ['تاریخ', 'ساعت', 'کاربر', 'نوع کاربر', 'اقدام', 'نوع رکورد', 'شناسه', 'عنوان', 'تغییرات', 'IP', 'مسیر']);

            foreach ($result['items'] as $row) {
                $changes = [];
                foreach ($row['changes'] as $field => $pair) {
                    $changes[] = $field.': '.($pair['old'] ?? '—').' → '.($pair['new'] ?? '—');
                }
                fputcsv($out, [
                    $row['at'] ? Jalalian::fromDateTime($row['at']->toDateTime())->format('Y/m/d') : '',
                    $row['at']?->format('H:i:s'),
                    $row['user_name'],
                    $row['user_type'],
                    ActivityRegistry::ACTIONS[$row['action']] ?? $row['action'],
                    $row['entity_label'],
                    $row['entity_id'],
                    $row['entity_title'] ?? $row['description'],
                    implode(' | ', $changes),
                    $row['ip'],
                    $row['path'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'from' => trim($request->string('from')->toString()),
            'to' => trim($request->string('to')->toString()),
            'action' => trim($request->string('action')->toString()),
            'entity' => trim($request->string('entity')->toString()),
            'user_id' => trim($request->string('user_id')->toString()),
            'ip' => trim($request->string('ip')->toString()),
            'q' => trim($request->string('q')->toString()),
        ];
    }
}
