<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;

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
}
