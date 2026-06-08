<?php

namespace Modules\CustomerApp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\CRM\Models\ReviewTag;

/**
 * مدیریت تگ‌های نقاط قوت/ضعف نظرسنجی.
 *
 * این تگ‌ها در فرم نظرسنجی اپ موبایل نمایش داده می‌شوند — کاربر بعد از
 * دادن امتیاز ۱..۵، انتخاب می‌کند که چه نقاط قوتی/ضعفی را در سرویس تجربه
 * کرده. هیچ محدودیتی روی pro/con با rating اعمال نمی‌شود — تصمیم با UX
 * فرانت است (پیشنهاد: 1-2 cons، 3 both، 4-5 pros).
 */
class ReviewTagController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');

        $query = ReviewTag::query()->ordered();
        if ($type && in_array($type, [ReviewTag::TYPE_PRO, ReviewTag::TYPE_CON], true)) {
            $query->where('type', $type);
        }

        $items = $query->paginate(40)->withQueryString();

        $counts = [
            ReviewTag::TYPE_PRO => ReviewTag::query()->pros()->count(),
            ReviewTag::TYPE_CON => ReviewTag::query()->cons()->count(),
        ];

        return view('customerapp::admin.review-tags.index', compact('items', 'counts', 'type'));
    }

    public function create(): View
    {
        return view('customerapp::admin.review-tags.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        ReviewTag::create($data);

        return redirect()->route('customer-app.review-tags.index')
            ->with('success', 'تگ اضافه شد.');
    }

    public function edit(ReviewTag $reviewTag): View
    {
        return view('customerapp::admin.review-tags.edit', ['item' => $reviewTag]);
    }

    public function update(Request $request, ReviewTag $reviewTag): RedirectResponse
    {
        $data = $this->validateData($request, $reviewTag->id);
        $reviewTag->update($data);

        return redirect()->route('customer-app.review-tags.index')
            ->with('success', 'تگ ویرایش شد.');
    }

    public function destroy(ReviewTag $reviewTag): RedirectResponse
    {
        $reviewTag->delete();

        return redirect()->route('customer-app.review-tags.index')
            ->with('success', 'تگ حذف شد.');
    }

    public function toggleActive(ReviewTag $reviewTag): RedirectResponse
    {
        $reviewTag->forceFill(['is_active' => ! $reviewTag->is_active])->save();

        return back()->with('success', $reviewTag->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'slug' => 'nullable|string|max:80|alpha_dash|unique:crm_review_tags,slug'.($id ? ','.$id : ''),
            'label' => 'required|string|max:200',
            'type' => 'required|in:'.ReviewTag::TYPE_PRO.','.ReviewTag::TYPE_CON,
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug(($data['type'] === ReviewTag::TYPE_PRO ? 'pro-' : 'con-').$data['label']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($request->input('is_active') ?? true);

        return $data;
    }
}
