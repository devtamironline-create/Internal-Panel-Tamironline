<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\RedirectMatcher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مدیریت ریدایرکت‌ها در پنل.
 */
class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $redirects = SeoRedirect::query()
            ->when($q !== '', fn ($query) => $query
                ->where('source', 'like', "%{$q}%")
                ->orWhere('target', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // پیش‌پرکردن مبدأ/مقصد هنگام «ساخت ریدایرکت از ۴۰۴» (مقصدِ پیشنهادی).
        $prefillSource = (string) $request->query('source', '');
        $prefillTarget = (string) $request->query('target', '');

        // شناسایی زنجیره‌ها: ریدایرکت‌های فعالی که مقصدشان باز هم ریدایرکت می‌شود.
        $chains = $this->detectChains();

        return view('seo::redirects.index', compact('redirects', 'q', 'prefillSource', 'prefillTarget', 'chains'));
    }

    /**
     * فهرست ریدایرکت‌های فعالی که مقصدشان باز هم به جای دیگری ریدایرکت می‌شود.
     *
     * @return array<int,array{redirect:SeoRedirect,final:string,hops:int,looped:bool}>
     */
    private function detectChains(int $limit = 50): array
    {
        $matcher = new RedirectMatcher;

        $active = SeoRedirect::query()
            ->active()
            ->whereNotNull('target')
            ->where('target', '!=', '')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $chains = [];
        foreach ($active as $r) {
            $chain = $matcher->resolveChain((string) $r->target);
            if ($chain['hops'] > 0) {
                $chains[] = [
                    'redirect' => $r,
                    'final' => $chain['final'],
                    'hops' => $chain['hops'],
                    'looped' => $chain['looped'],
                ];
            }
        }

        return $chains;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source' => 'required|string|max:2048',
            'target' => 'nullable|string|max:2048|required_unless:status_code,410,451',
            'status_code' => ['required', 'integer', Rule::in(SeoRedirect::STATUS_CODES)],
            'match_type' => ['required', 'string', Rule::in(SeoRedirect::MATCH_TYPES)],
            'is_active' => 'nullable|boolean',
        ]);

        // برای 410/451 مقصد بی‌معناست.
        if (in_array((int) $validated['status_code'], [410, 451], true)) {
            $validated['target'] = null;
        }
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $matcher = new RedirectMatcher;

        // تشخیص حلقه برای ریدایرکت‌های exact با مقصد.
        if ($validated['match_type'] === 'exact' && ! empty($validated['target'])
            && $matcher->wouldLoop($validated['source'], $validated['target'])) {
            return back()
                ->withInput()
                ->withErrors(['target' => 'این ریدایرکت یک حلقه می‌سازد.']);
        }

        $redirect = SeoRedirect::create($validated);

        SeoChangeLog::record('created', 'redirect', $redirect->source.' → '.($redirect->target ?? (string) $redirect->status_code));

        // پیشنهاد جمع‌کردن زنجیره اگر مقصد خودش باز هم ریدایرکت می‌شود.
        if (! empty($validated['target'])) {
            $chain = $matcher->resolveChain($validated['target']);
            if ($chain['hops'] > 0 && ! $chain['looped']) {
                return back()->with('success', 'ریدایرکت اضافه شد.')
                    ->with('info', 'زنجیره: بهتر است مستقیم به '.$chain['final'].' ریدایرکت شود.');
            }
        }

        return back()->with('success', 'ریدایرکت اضافه شد.');
    }

    public function toggle(SeoRedirect $redirect)
    {
        $redirect->forceFill(['is_active' => ! $redirect->is_active])->save();

        return back()->with('success', $redirect->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    public function destroy(SeoRedirect $redirect)
    {
        $redirect->delete();

        return back()->with('success', 'ریدایرکت حذف شد.');
    }

    /**
     * خروجی CSV از همهٔ ریدایرکت‌ها.
     */
    public function export(): StreamedResponse
    {
        $headers = ['source', 'target', 'status_code', 'match_type', 'is_active'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            // BOM برای نمایش درست UTF-8 در اکسل.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            SeoRedirect::query()
                ->orderByDesc('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->source,
                            $r->target,
                            $r->status_code,
                            $r->match_type,
                            $r->is_active ? 1 : 0,
                        ]);
                    }
                });

            fclose($out);
        }, 'seo-redirects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * ورود CSV ریدایرکت‌ها (upsert بر اساس source + match_type).
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);

        $path = $request->file('file')->getRealPath();
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return back()->with('error', 'فایل نامعتبر است.');
        }

        $matcher = new RedirectMatcher;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $headerSkipped = false;

        DB::transaction(function () use ($handle, $matcher, &$created, &$updated, &$skipped, &$headerSkipped) {
            while (($row = fgetcsv($handle)) !== false) {
                // ردیف خالی.
                if ($row === [null] || $row === false || count(array_filter($row, fn ($c) => $c !== null && $c !== '')) === 0) {
                    continue;
                }

                // حذف BOM احتمالی از سلول اول.
                $row[0] = isset($row[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) : $row[0];

                // رد کردن سطر سرستون.
                if (! $headerSkipped && strtolower(trim((string) ($row[0] ?? ''))) === 'source') {
                    $headerSkipped = true;

                    continue;
                }
                $headerSkipped = true;

                $source = trim((string) ($row[0] ?? ''));
                $target = isset($row[1]) ? trim((string) $row[1]) : '';
                $statusCode = (int) ($row[2] ?? 0);
                $matchType = trim((string) ($row[3] ?? ''));
                $isActiveRaw = isset($row[4]) ? trim((string) $row[4]) : '1';
                $isActive = in_array(strtolower($isActiveRaw), ['1', 'true', 'yes', 'on'], true);

                $isGone = in_array($statusCode, [410, 451], true);

                // اعتبارسنجی ردیف.
                $valid = $source !== ''
                    && in_array($statusCode, SeoRedirect::STATUS_CODES, true)
                    && in_array($matchType, SeoRedirect::MATCH_TYPES, true)
                    && ($isGone || $target !== '');

                if (! $valid) {
                    $skipped++;

                    continue;
                }

                if ($isGone) {
                    $target = null;
                }

                // رد کردن ردیف‌های حلقه‌ساز (فقط exact با مقصد).
                if (! $isGone && $matchType === 'exact' && $target !== null
                    && $matcher->wouldLoop($source, $target)) {
                    $skipped++;

                    continue;
                }

                $existing = SeoRedirect::query()
                    ->where('source', $source)
                    ->where('match_type', $matchType)
                    ->first();

                if ($existing) {
                    $existing->forceFill([
                        'target' => $target,
                        'status_code' => $statusCode,
                        'is_active' => $isActive,
                    ])->save();
                    $updated++;
                } else {
                    SeoRedirect::create([
                        'source' => $source,
                        'target' => $target,
                        'status_code' => $statusCode,
                        'match_type' => $matchType,
                        'is_active' => $isActive,
                    ]);
                    $created++;
                }
            }
        });

        fclose($handle);

        SeoChangeLog::record('imported', 'redirect', "ورود CSV ریدایرکت: {$created} ساخته، {$updated} به‌روز، {$skipped} نامعتبر/حلقه.");

        return back()->with('success', "{$created} ساخته، {$updated} به‌روز، {$skipped} نامعتبر/حلقه.");
    }
}
