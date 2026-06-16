<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoNotFound;

/**
 * مانیتور ۴۰۴ در پنل — فهرست پربازدیدترین مسیرهای یافت‌نشده و امکان ساخت
 * ریدایرکت مستقیم از روی هر رکورد.
 */
class NotFoundController extends Controller
{
    public function index()
    {
        $logs = SeoNotFound::query()
            ->orderByDesc('hits')
            ->orderByDesc('last_seen_at')
            ->paginate(40);

        return view('seo::not-found.index', compact('logs'));
    }

    public function destroy(SeoNotFound $notFound)
    {
        $notFound->delete();

        return back()->with('success', 'رکورد ۴۰۴ حذف شد.');
    }
}
