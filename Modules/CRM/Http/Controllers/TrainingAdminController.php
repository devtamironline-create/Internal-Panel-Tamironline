<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\TrainingCategory;
use Modules\CRM\Models\TrainingVideo;

/**
 * مدیریت آموزش‌های ویدیویی تکنسین — دسته‌بندی + ویدیو + توضیحات.
 *
 * - دسته‌بندی فقط یک سطح است (لیست تخت با sort_order).
 * - ویدیو می‌تواند URL خارجی باشد (آپارات/یوتیوب/...) یا فایل لوکال
 *   آپلودی در storage/app/public/crm/training/.
 * - دسترسی: manage-crm-settings (یا یک permission اختصاصی در صورت
 *   نیاز در آینده).
 */
class TrainingAdminController extends Controller
{
    public function categoriesIndex()
    {
        $categories = TrainingCategory::ordered()
            ->withCount(['videos', 'activeVideos'])
            ->get();

        return view('crm::training.admin.categories', compact('categories'));
    }

    public function categoriesStore(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:200|unique:crm_training_categories,name',
            'description' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'نام دسته‌بندی الزامی است.',
            'name.unique'   => 'این نام قبلاً ثبت شده است.',
        ]);

        $maxOrder = TrainingCategory::max('sort_order') ?? 0;
        TrainingCategory::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order'  => $maxOrder + 1,
            'is_active'   => true,
        ]);

        return redirect()
            ->route('crm.training.admin.categories')
            ->with('success', 'دسته‌بندی «' . $validated['name'] . '» اضافه شد.');
    }

    public function categoriesUpdate(Request $request, TrainingCategory $category)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:200|unique:crm_training_categories,name,' . $category->id,
            'description' => 'nullable|string|max:2000',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'required|boolean',
        ]);

        $category->update($validated);

        return redirect()
            ->route('crm.training.admin.categories')
            ->with('success', 'دسته‌بندی «' . $category->name . '» به‌روزرسانی شد.');
    }

    public function categoriesDestroy(TrainingCategory $category)
    {
        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('crm.training.admin.categories')
            ->with('success', 'دسته «' . $name . '» حذف شد. ویدیوهای آن بدون دسته باقی ماندند.');
    }

    // ─── ویدیوها ───────────────────────────────────────────────────

    public function videosIndex(Request $request)
    {
        $categoryId = $request->integer('category_id');

        $videos = TrainingVideo::query()
            ->with('category')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $categories = TrainingCategory::ordered()->get();

        return view('crm::training.admin.videos', compact('videos', 'categories', 'categoryId'));
    }

    public function videosCreate()
    {
        $categories = TrainingCategory::ordered()->get();
        $video = new TrainingVideo(['is_local' => false, 'is_active' => true, 'sort_order' => 0]);

        return view('crm::training.admin.video-form', [
            'video' => $video,
            'categories' => $categories,
            'mode' => 'create',
        ]);
    }

    public function videosStore(Request $request)
    {
        $validated = $this->validateVideoRequest($request);
        $payload = $this->buildVideoPayload($request, $validated, null);

        TrainingVideo::create($payload);

        return redirect()
            ->route('crm.training.admin.videos')
            ->with('success', 'ویدیو «' . $payload['title'] . '» اضافه شد.');
    }

    public function videosEdit(TrainingVideo $video)
    {
        $categories = TrainingCategory::ordered()->get();

        return view('crm::training.admin.video-form', [
            'video' => $video,
            'categories' => $categories,
            'mode' => 'edit',
        ]);
    }

    public function videosUpdate(Request $request, TrainingVideo $video)
    {
        $validated = $this->validateVideoRequest($request);
        $payload = $this->buildVideoPayload($request, $validated, $video);

        $video->update($payload);

        return redirect()
            ->route('crm.training.admin.videos')
            ->with('success', 'ویدیو «' . $payload['title'] . '» به‌روزرسانی شد.');
    }

    public function videosDestroy(TrainingVideo $video)
    {
        // در صورت لوکال بودن، فایل را هم حذف کنیم
        if ($video->is_local && $video->video_url) {
            Storage::disk('public')->delete($video->video_url);
        }
        if ($video->thumbnail) {
            Storage::disk('public')->delete($video->thumbnail);
        }

        $title = $video->title;
        $video->delete();

        return redirect()
            ->route('crm.training.admin.videos')
            ->with('success', 'ویدیو «' . $title . '» حذف شد.');
    }

    // ─── helpers ─────────────────────────────────────────────────

    protected function validateVideoRequest(Request $request): array
    {
        return $request->validate([
            'category_id'      => 'nullable|integer|exists:crm_training_categories,id',
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string|max:5000',
            'source_type'      => 'required|in:url,upload',
            'video_url'        => 'required_if:source_type,url|nullable|url|max:1000',
            'video_file'       => 'required_if:source_type,upload|nullable|file|mimes:mp4,webm,mov|max:204800', // 200MB
            'thumbnail_file'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'duration_seconds' => 'nullable|integer|min:0',
            'sort_order'       => 'nullable|integer|min:0',
            'is_active'        => 'required|boolean',
        ], [
            'title.required'       => 'عنوان ویدیو الزامی است.',
            'video_url.required_if' => 'برای منبع URL، لینک ویدیو الزامی است.',
            'video_url.url'        => 'لینک ویدیو معتبر نیست.',
            'video_file.required_if' => 'برای منبع آپلود، فایل ویدیو الزامی است.',
            'video_file.max'       => 'حجم فایل ویدیو حداکثر ۲۰۰ مگابایت مجاز است.',
            'thumbnail_file.max'   => 'حجم تصویر کاور حداکثر ۵ مگابایت مجاز است.',
        ]);
    }

    /**
     * ساخت payload نهایی — شامل هندل کردن فایل آپلود ویدیو/تامبنیل.
     */
    protected function buildVideoPayload(Request $request, array $validated, ?TrainingVideo $existing): array
    {
        $payload = [
            'category_id'      => $validated['category_id'] ?? null,
            'title'            => $validated['title'],
            'description'      => $validated['description'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'sort_order'       => $validated['sort_order'] ?? 0,
            'is_active'        => (bool) $validated['is_active'],
        ];

        if ($validated['source_type'] === 'upload' && $request->hasFile('video_file')) {
            // اگر قبلاً فایل لوکال داشته، فایل قدیم را حذف کنیم
            if ($existing && $existing->is_local && $existing->video_url) {
                Storage::disk('public')->delete($existing->video_url);
            }
            $path = $request->file('video_file')->store('crm/training/videos', 'public');
            $payload['is_local']  = true;
            $payload['video_url'] = $path;
        } elseif ($validated['source_type'] === 'url') {
            // اگر قبلاً فایل لوکال داشته و الان URL گذاشته، فایل قدیم پاک شود
            if ($existing && $existing->is_local && $existing->video_url) {
                Storage::disk('public')->delete($existing->video_url);
            }
            $payload['is_local']  = false;
            $payload['video_url'] = $validated['video_url'];
        } elseif ($existing) {
            // source_type تغییر نکرده و فایل جدیدی هم نیامده — نگه‌داری مقادیر فعلی
            $payload['is_local']  = $existing->is_local;
            $payload['video_url'] = $existing->video_url;
        }

        if ($request->hasFile('thumbnail_file')) {
            if ($existing && $existing->thumbnail) {
                Storage::disk('public')->delete($existing->thumbnail);
            }
            $payload['thumbnail'] = $request->file('thumbnail_file')->store('crm/training/thumbnails', 'public');
        }

        return $payload;
    }
}
