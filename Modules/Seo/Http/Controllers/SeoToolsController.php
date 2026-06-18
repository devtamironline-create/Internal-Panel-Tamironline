<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoableRegistry;

/**
 * ابزارها: Import/Export کل تنظیمات سئو + نمایش audit log تغییرات.
 */
class SeoToolsController extends Controller
{
    public function index()
    {
        $logs = SeoChangeLog::query()->latest('id')->paginate(40);

        return view('seo::tools.index', compact('logs'));
    }

    public function export(): StreamedResponse
    {
        $payload = [
            'version' => 1,
            'exported_at' => now()->toAtomString(),
            'settings' => SeoSetting::query()->get(['key', 'value', 'group'])->toArray(),
            'redirects' => SeoRedirect::query()->get([
                'source', 'target', 'status_code', 'match_type', 'is_active',
            ])->toArray(),
            'meta' => SeoMeta::query()->get()->makeHidden(['id', 'created_at', 'updated_at'])->toArray(),
        ];

        return response()->streamDownload(
            fn () => print(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
            'seo-export-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt']);

        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($data)) {
            return back()->with('error', 'فایل نامعتبر است.');
        }

        $counts = ['settings' => 0, 'redirects' => 0, 'meta' => 0];

        foreach ($data['settings'] ?? [] as $row) {
            if (! empty($row['key'])) {
                SeoSetting::set($row['key'], $row['value'] ?? null, $row['group'] ?? 'general');
                $counts['settings']++;
            }
        }

        foreach ($data['redirects'] ?? [] as $row) {
            if (! empty($row['source'])) {
                SeoRedirect::updateOrCreate(
                    ['source' => $row['source'], 'match_type' => $row['match_type'] ?? 'exact'],
                    [
                        'target' => $row['target'] ?? null,
                        'status_code' => $row['status_code'] ?? 301,
                        'is_active' => $row['is_active'] ?? true,
                    ]
                );
                $counts['redirects']++;
            }
        }

        foreach ($data['meta'] ?? [] as $row) {
            if (! empty($row['seoable_type']) && ! empty($row['seoable_id'])) {
                SeoMeta::updateOrCreate(
                    ['seoable_type' => $row['seoable_type'], 'seoable_id' => $row['seoable_id']],
                    collect($row)->except(['seoable_type', 'seoable_id'])->all()
                );
                $counts['meta']++;
            }
        }

        SeoChangeLog::record('imported', 'settings', "ورود داده: {$counts['settings']} تنظیم، {$counts['redirects']} ریدایرکت، {$counts['meta']} متا.");

        return back()->with('success', "وارد شد: {$counts['settings']} تنظیم، {$counts['redirects']} ریدایرکت، {$counts['meta']} متا.");
    }

    /** ستون‌های فایل CSVِ متاها (ترتیب ثابت). */
    private const CSV_COLUMNS = [
        'type', 'slug', 'url', 'seo_title', 'meta_description', 'canonical',
        'index', 'follow', 'og_title', 'og_description', 'focus_keyword', 'notes',
    ];

    /**
     * خروجی CSV متاهای صفحات (همهٔ آیتم‌های نوع‌های واقعی، حتی بدون متا).
     * استریم‌شده و chunkـشده تا کل کالکشن در حافظه بار نشود.
     */
    public function exportCsv(SeoableRegistry $registry): StreamedResponse
    {
        $hasNotes = Schema::hasColumn('seo_meta', 'notes');

        return response()->streamDownload(function () use ($registry, $hasNotes) {
            $out = fopen('php://output', 'w');
            // BOM برای نمایش صحیح فارسی در Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_COLUMNS);

            foreach ($registry->all() as $type => $cfg) {
                // نوع‌های مجازی/resolver (مثل brand_device) ردیف DB ندارند → رد.
                if (! empty($cfg['resolver']) || empty($cfg['model'])) {
                    continue;
                }

                /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
                $modelClass = $cfg['model'];
                if (! method_exists($modelClass, 'seoMeta')) {
                    continue;
                }

                $slugColumn = $cfg['slug'] ?? 'slug';

                $modelClass::query()
                    ->with('seoMeta')
                    ->chunk(200, function ($models) use ($out, $type, $slugColumn, $registry, $hasNotes) {
                        foreach ($models as $model) {
                            $meta = $model->seoMeta;
                            $focus = $meta && is_array($meta->focus_keywords) && ! empty($meta->focus_keywords)
                                ? (string) $meta->focus_keywords[0]
                                : '';

                            fputcsv($out, [
                                $type,
                                (string) $model->getAttribute($slugColumn),
                                $registry->pathFor($type, $model),
                                $meta->title ?? '',
                                $meta->description ?? '',
                                $meta->canonical ?? '',
                                ($meta && $meta->robots_noindex) ? 'noindex' : 'index',
                                ($meta && $meta->robots_nofollow) ? 'nofollow' : 'follow',
                                $meta->og_title ?? '',
                                $meta->og_description ?? '',
                                $focus,
                                ($hasNotes && $meta) ? ($meta->notes ?? '') : '',
                            ]);
                        }
                    });
            }

            fclose($out);
        }, 'seo-metas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * ورود CSV متاها: هر ردیف با (type, slug) به مدل نگاشت و seoMeta آن upsert می‌شود.
     * کل عملیات در یک تراکنش؛ ردیف‌های نامعتبر فقط شمرده و رد می‌شوند.
     */
    public function importCsv(Request $request, SeoableRegistry $registry)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:8192']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'فایل قابل خواندن نیست.');
        }

        // حذف BOM احتمالی از ابتدای فایل.
        $first = fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first);
        $header = str_getcsv($first);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        if (! in_array('type', $header, true) || ! in_array('slug', $header, true)) {
            fclose($handle);

            return back()->with('error', 'سرستون فایل نامعتبر است (ستون‌های type و slug الزامی است).');
        }

        $index = array_flip($header);
        $hasNotes = Schema::hasColumn('seo_meta', 'notes');

        $counts = ['updated' => 0, 'created' => 0, 'skipped' => 0];

        try {
            DB::transaction(function () use ($handle, $index, $registry, $hasNotes, &$counts) {
                while (($row = fgetcsv($handle)) !== false) {
                    // ردیف کاملاً خالی → رد بی‌صدا.
                    if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                        continue;
                    }

                    $get = function (string $col) use ($row, $index): string {
                        $i = $index[$col] ?? null;

                        return $i !== null && isset($row[$i]) ? trim((string) $row[$i]) : '';
                    };

                    $type = $get('type');
                    $slug = $get('slug');

                    if ($type === '' || $slug === '') {
                        $counts['skipped']++;
                        continue;
                    }

                    $model = $registry->resolveBySlug($type, $slug);
                    if (! $model || ! method_exists($model, 'seoMeta')) {
                        $counts['skipped']++;
                        continue;
                    }

                    /** @var SeoMeta $meta */
                    $meta = $model->seoMeta()->firstOrNew([]);
                    $isNew = ! $meta->exists;

                    if (($v = $get('seo_title')) !== '') {
                        $meta->title = $v;
                    }
                    if (($v = $get('meta_description')) !== '') {
                        $meta->description = $v;
                    }
                    if (($v = $get('canonical')) !== '') {
                        $meta->canonical = $v;
                    }
                    if (($v = $get('og_title')) !== '') {
                        $meta->og_title = $v;
                    }
                    if (($v = $get('og_description')) !== '') {
                        $meta->og_description = $v;
                    }
                    if ($hasNotes && ($v = $get('notes')) !== '') {
                        $meta->notes = $v;
                    }
                    if (($v = $get('focus_keyword')) !== '') {
                        $meta->focus_keywords = [$v];
                    }

                    $meta->robots_noindex = strtolower($get('index')) === 'noindex';
                    $meta->robots_nofollow = strtolower($get('follow')) === 'nofollow';

                    $model->seoMeta()->save($meta);

                    $isNew ? $counts['created']++ : $counts['updated']++;
                }
            });
        } catch (\Throwable $e) {
            fclose($handle);

            return back()->with('error', 'ورود ناموفق بود؛ تغییری اعمال نشد: '.$e->getMessage());
        }

        fclose($handle);

        $summary = "{$counts['updated']} به‌روز، {$counts['created']} ساخته، {$counts['skipped']} نامعتبر";
        SeoChangeLog::record('imported', 'meta_csv', $summary);

        return back()->with('success', $summary);
    }
}
