<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Review;
use Modules\Site\Models\ReviewReply;
use Modules\Site\Models\Taxonomy;

/**
 * پنل ادمین واحد برای «نظرات و توصیه‌نامه‌ها».
 *
 *   - audio (testimonial صوتی): ادمین خودش ایجاد می‌کند، فیلدهای audio_url/topic/duration
 *   - text  (نظر متنی): از فرم عمومی صفحه‌ی دستگاه می‌آید، با موقعیت approval workflow
 *
 * هر دو در همین صفحه با تب type و فیلتر status مدیریت می‌شوند.
 */
class ReviewController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('view-site-reviews')
            && ! $u->can('manage-site-reviews')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-reviews')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $type = in_array($request->query('type'), ['audio', 'text'], true)
            ? $request->query('type')
            : null;
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : null;

        $query = Review::query()->with(['reply', 'device:id,name,slug'])->latest('created_at');

        if ($type) {
            $query->where('type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($device = trim((string) $request->query('device', ''))) {
            $query->where('device_slug', $device);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('author_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%")
                    ->orWhere('topic', 'like', "%{$q}%");
            });
        }

        $reviews = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => Review::count(),
            'audio' => Review::audio()->count(),
            'text' => Review::text()->count(),
            'pending' => Review::pending()->count(),
            'approved' => Review::approved()->count(),
            'rejected' => Review::where('status', Review::STATUS_REJECTED)->count(),
        ];

        return view('site::admin.reviews.index', compact('reviews', 'counts', 'type', 'status'));
    }

    public function create(): View
    {
        $this->checkManage();
        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)->ordered()->get();
        $devices = Device::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('site::admin.reviews.create', [
            'review' => null,
            'taxonomies' => $taxonomies,
            'selectedTaxonomies' => [],
            'devices' => $devices,
            'selectedDeviceIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkManage();

        $data = $this->validateAudioReview($request);
        $data['type'] = Review::TYPE_AUDIO;
        $data['status'] = $data['is_published'] ? Review::STATUS_APPROVED : Review::STATUS_PENDING;
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $taxonomyIds = $this->collectTaxonomyIds($request);
        $deviceIds = $this->collectDeviceIds($request);

        $review = Review::create($data);
        $review->taxonomies()->sync($taxonomyIds);
        $review->devices()->sync($deviceIds);

        return redirect()
            ->route('site.admin.reviews.index', ['type' => 'audio'])
            ->with('success', 'توصیه‌نامه‌ی جدید ثبت شد.');
    }

    public function show(string $id): View
    {
        $this->checkView();
        $review = Review::with(['reply', 'device:id,name,slug', 'devices:id,name,slug', 'taxonomies:id,name'])
            ->findOrFail($id);

        return view('site::admin.reviews.show', compact('review'));
    }

    public function edit(string $id): View
    {
        $this->checkManage();
        $review = Review::with(['taxonomies:id', 'devices:id'])->findOrFail($id);

        if ($review->type !== Review::TYPE_AUDIO) {
            return redirect()
                ->route('site.admin.reviews.show', $review->id)
                ->with('error', 'نظرات متنی فقط در صفحه‌ی مشاهده قابل مدیریت هستند.');
        }

        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)->ordered()->get();
        $selectedTaxonomies = $review->taxonomies->pluck('id')->all();
        $devices = Device::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $selectedDeviceIds = $review->devices->pluck('id')->map(fn ($i) => (int) $i)->all();

        return view('site::admin.reviews.edit', compact('review', 'taxonomies', 'selectedTaxonomies', 'devices', 'selectedDeviceIds'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();
        $review = Review::findOrFail($id);

        if ($review->type !== Review::TYPE_AUDIO) {
            abort(404);
        }

        $data = $this->validateAudioReview($request);
        $data['status'] = $data['is_published'] ? Review::STATUS_APPROVED : ($review->status === Review::STATUS_APPROVED ? Review::STATUS_PENDING : $review->status);

        if ($data['is_published'] && ! $review->published_at) {
            $data['published_at'] = now();
        }
        if (! $data['is_published']) {
            $data['published_at'] = null;
        }

        $taxonomyIds = $this->collectTaxonomyIds($request);
        $deviceIds = $this->collectDeviceIds($request);

        $review->update($data);
        $review->taxonomies()->sync($taxonomyIds);
        $review->devices()->sync($deviceIds);

        return redirect()
            ->route('site.admin.reviews.index', ['type' => 'audio'])
            ->with('success', 'توصیه‌نامه به‌روز شد.');
    }

    /**
     * تغییر وضعیت — برای textی‌ها (approve/reject) از پنل moderation.
     */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review = Review::findOrFail($id);
        $review->update([
            'status' => $data['status'],
            'is_verified' => $data['status'] === Review::STATUS_APPROVED,
            'published_at' => $data['status'] === Review::STATUS_APPROVED ? ($review->published_at ?? now()) : null,
            'is_published' => $data['status'] === Review::STATUS_APPROVED,
        ]);

        return back()->with('success', 'وضعیت نظر به‌روز شد.');
    }

    public function togglePublish(string $id): RedirectResponse
    {
        $this->checkManage();

        $review = Review::findOrFail($id);
        $publish = ! $review->is_published;
        $review->update([
            'is_published' => $publish,
            'status' => $publish ? Review::STATUS_APPROVED : ($review->status === Review::STATUS_APPROVED ? Review::STATUS_PENDING : $review->status),
            'published_at' => $publish ? ($review->published_at ?? now()) : null,
        ]);

        return back()->with('success', $publish ? 'منتشر شد.' : 'از انتشار خارج شد.');
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();

        $data = $request->validate([
            'content' => 'required|string|min:5|max:3000',
        ]);

        $review = Review::findOrFail($id);

        ReviewReply::updateOrCreate(
            ['review_id' => $review->id],
            [
                'author_name' => auth()->user()->name ?? 'تیم تعمیرآنلاین',
                'is_expert' => true,
                'content' => trim($data['content']),
            ]
        );

        return back()->with('success', 'پاسخ ثبت شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkManage();
        Review::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.reviews.index')
            ->with('success', 'نظر حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAudioReview(Request $request): array
    {
        $validated = $request->validate([
            'author_name' => 'required|string|max:80',
            'topic' => 'required|string|max:120',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:5000',
            'audio_url' => 'nullable|url|max:500',
            'duration_seconds' => 'nullable|integer|min:1|max:7200',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ], [], [
            'author_name' => 'نام مشتری',
            'topic' => 'موضوع',
            'rating' => 'امتیاز',
            'content' => 'متن نظر',
            'audio_url' => 'لینک فایل صوتی',
            'duration_seconds' => 'مدت زمان',
        ]);

        // حداقل یکی از «متن» یا «فایل صوتی» باید باشد (می‌توانند هر دو باشند).
        if (empty($validated['content']) && empty($validated['audio_url'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'content' => 'حداقل یکی از «متن نظر» یا «فایل صوتی» را وارد کنید.',
            ]);
        }

        $validated['is_published'] = (bool) ($validated['is_published'] ?? false);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    /**
     * @return array<int, int>
     */
    private function collectTaxonomyIds(Request $request): array
    {
        $ids = (array) $request->input('taxonomy_ids', []);
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($v) => $v !== null && $v !== ''))));
        if (empty($ids)) {
            return [];
        }

        return Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function collectDeviceIds(Request $request): array
    {
        $ids = (array) $request->input('device_ids', []);
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($v) => $v !== null && $v !== ''))));
        if (empty($ids)) {
            return [];
        }

        return Device::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($i) => (int) $i)
            ->all();
    }
}
