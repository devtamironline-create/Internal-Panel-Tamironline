<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoNotFound;
use Modules\Seo\Services\SeoableRegistry;

/**
 * مانیتور ۴۰۴ در پنل — فهرست پربازدیدترین مسیرهای یافت‌نشده، فیلتر و مرتب‌سازی،
 * نادیده‌گرفتن، حذف گروهی، خروجی CSV و پیشنهادِ ریدایرکت بر اساس رجیستری نوع‌محتوا.
 */
class NotFoundController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filteredQuery($request)->paginate(40)->withQueryString();

        // پیشنهادِ ریدایرکت فقط برای ردیف‌های همین صفحه محاسبه می‌شود.
        $suggestions = [];
        foreach ($logs->items() as $log) {
            $suggestions[$log->id] = $this->suggestRedirect($log->uri);
        }

        $filters = [
            'q' => (string) $request->query('q', ''),
            'sort' => (string) $request->query('sort', 'hits'),
            'bot' => (string) $request->query('bot', 'all'),
            'show' => (string) $request->query('show', 'active'),
        ];

        return view('seo::not-found.index', compact('logs', 'suggestions', 'filters'));
    }

    public function ignore(SeoNotFound $notFound)
    {
        $notFound->ignored_at = now();
        $notFound->save();

        SeoChangeLog::record('ignored', 'not_found', $notFound->uri);

        return back()->with('success', 'مسیر ۴۰۴ نادیده گرفته شد.');
    }

    public function unignore(SeoNotFound $notFound)
    {
        $notFound->ignored_at = null;
        $notFound->save();

        SeoChangeLog::record('unignored', 'not_found', $notFound->uri);

        return back()->with('success', 'مسیر ۴۰۴ بازگردانده شد.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $count = SeoNotFound::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', $count.' رکورد ۴۰۴ حذف شد.');
    }

    public function export(Request $request): StreamedResponse
    {
        $registry = app(SeoableRegistry::class);
        $query = $this->filteredQuery($request);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="seo-404.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM برای نمایش درست فارسی در اکسل.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['uri', 'hits', 'is_bot', 'first_seen_at', 'last_seen_at', 'referrer', 'suggested_redirect']);

            $query->orderByDesc('hits')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->uri,
                        $row->hits,
                        $row->is_bot ? '1' : '0',
                        optional($row->first_seen_at)->format('Y-m-d H:i:s'),
                        optional($row->last_seen_at)->format('Y-m-d H:i:s'),
                        $row->referrer,
                        $this->suggestRedirect($row->uri),
                    ]);
                }
            });

            fclose($out);
        }, 'seo-404.csv', $headers);
    }

    public function destroy(SeoNotFound $notFound)
    {
        $notFound->delete();

        return back()->with('success', 'رکورد ۴۰۴ حذف شد.');
    }

    /**
     * کوئریِ پایه با اعمالِ فیلترهای query string (q/sort/bot/show).
     */
    private function filteredQuery(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'hits');
        $bot = (string) $request->query('bot', 'all');
        $show = (string) $request->query('show', 'active');

        $query = SeoNotFound::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('uri', 'like', "%{$q}%")
                    ->orWhere('referrer', 'like', "%{$q}%");
            }));

        // فیلترِ ربات.
        if ($bot === 'exclude') {
            $query->where('is_bot', false);
        } elseif ($bot === 'only') {
            $query->where('is_bot', true);
        }

        // فیلترِ نمایش (نادیده‌گرفته‌شده/فعال/همه).
        if ($show === 'ignored') {
            $query->ignored();
        } elseif ($show === 'all') {
            // بدون محدودیت
        } else {
            $query->notIgnored();
        }

        // مرتب‌سازی.
        if ($sort === 'last_seen') {
            $query->orderByDesc('last_seen_at');
        } else {
            $query->orderByDesc('hits')->orderByDesc('last_seen_at');
        }

        return $query;
    }

    /**
     * پیشنهادِ مقصدِ ریدایرکت: آخرین سگمنتِ مسیرِ ۴۰۴ را برمی‌دارد و در هر
     * نوع‌محتوای رجیستری بر اساس ستونِ slug جستجو می‌کند؛ با اولین تطبیق،
     * مسیرِ عمومی را از روی الگوی url می‌سازد و برمی‌گرداند.
     */
    private function suggestRedirect(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        if (empty($segments)) {
            return null;
        }
        $needle = rawurldecode(end($segments));
        if ($needle === '') {
            return null;
        }

        foreach ($this->seoTypes() as $cfg) {
            $modelClass = $cfg['model'] ?? null;
            // فقط مدل‌های واقعی Eloquent با ستونِ slug — انواعِ resolver-دار را رد می‌کنیم.
            if (! $modelClass || ! empty($cfg['resolver']) || ! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $slugColumn = $cfg['slug'] ?? 'slug';
            /** @var Model|null $match */
            $match = $modelClass::query()->where($slugColumn, $needle)->first();
            if (! $match) {
                continue;
            }

            $pattern = $cfg['url'] ?? '/{slug}';

            return $this->buildPath($pattern, $match, $slugColumn);
        }

        return null;
    }

    /** کشِ پیکربندیِ انواعِ رجیستری در طولِ یک درخواست. */
    private function seoTypes(): array
    {
        static $types = null;
        if ($types === null) {
            $types = app(SeoableRegistry::class)->all();
        }

        return $types;
    }

    /** جایگذاریِ توکن‌های {…} در الگوی url با مقادیرِ مدل. */
    private function buildPath(string $pattern, Model $model, string $slugColumn): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($model, $slugColumn) {
            $key = $m[1];
            if ($key === 'slug') {
                return (string) $model->getAttribute($slugColumn);
            }

            return (string) ($model->getAttribute($key) ?? '');
        }, $pattern);
    }
}
