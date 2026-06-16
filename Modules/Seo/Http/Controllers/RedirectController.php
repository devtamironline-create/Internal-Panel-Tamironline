<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Seo\Models\SeoRedirect;

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

        // پیش‌پرکردن مبدأ هنگام «ساخت ریدایرکت از ۴۰۴».
        $prefillSource = (string) $request->query('source', '');

        return view('seo::redirects.index', compact('redirects', 'q', 'prefillSource'));
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

        SeoRedirect::create($validated);

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
}
