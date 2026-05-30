<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\Media\Media;
use Modules\Site\Models\Media\MediaTag;
use Modules\Site\Support\MediaStorage;

/**
 * مدیریت مخزن مدیا — grid، upload، edit metadata، delete، tags.
 */
class MediaController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('view-site-media') && ! $u->can('upload-site-media') && ! $u->can('manage-site-media') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    private function checkUpload(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('upload-site-media') && ! $u->can('manage-site-media') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-site-media') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = Media::query()->with(['variants', 'tags'])->latest('created_at');

        if ($kind = $request->query('kind')) {
            $query->where('kind', $kind);
        }
        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tag));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('filename', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%")
                ->orWhere('alt', 'like', "%{$q}%")
                ->orWhere('caption', 'like', "%{$q}%"));
        }

        $media = $query->paginate(48)->withQueryString();
        $tags = MediaTag::orderBy('name')->get();
        $counts = [
            'all' => Media::count(),
            'image' => Media::where('kind', 'image')->count(),
            'video' => Media::where('kind', 'video')->count(),
            'document' => Media::where('kind', 'document')->count(),
            'audio' => Media::where('kind', 'audio')->count(),
        ];

        return view('site::admin.media.index', compact('media', 'tags', 'counts'));
    }

    /**
     * POST /admin/site/media/upload — async upload با fetch، JSON response.
     */
    public function upload(Request $request): JsonResponse
    {
        $this->checkUpload();

        $request->validate([
            'file' => 'required|file|max:20480',   // 20 MB
        ]);

        $media = MediaStorage::store($request->file('file'), auth()->id());

        return response()->json([
            'ok' => true,
            'media' => $media->fresh(['variants'])->toApiArray(),
        ]);
    }

    public function show(int $id): View
    {
        $this->checkView();
        $media = Media::with(['variants', 'tags', 'uses'])->findOrFail($id);
        $tags = MediaTag::orderBy('name')->get();

        return view('site::admin.media.show', compact('media', 'tags'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'title' => 'nullable|string|max:250',
            'alt' => 'nullable|string|max:250',
            'caption' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:5000',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:site_media_tags,id',
        ]);

        $media = Media::findOrFail($id);
        $media->update([
            'title' => $data['title'] ?? $media->title,
            'alt' => $data['alt'] ?? null,
            'caption' => $data['caption'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        $media->tags()->sync($data['tag_ids'] ?? []);

        return back()->with('success', 'metadata به‌روز شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkManage();
        $media = Media::findOrFail($id);
        MediaStorage::delete($media);

        return redirect()->route('site.admin.media.index')->with('success', 'فایل حذف شد.');
    }

    /**
     * POST /admin/site/media/{id}/rebuild-variants — بازسازی thumbs.
     */
    public function rebuildVariants(int $id): RedirectResponse
    {
        $this->checkManage();
        $media = Media::findOrFail($id);
        MediaStorage::buildVariants($media);

        return back()->with('success', 'variants بازسازی شدند.');
    }

    // ─── Tags CRUD سبک ───────────────────────────────────────────

    public function storeTag(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
        ]);
        MediaTag::create([
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);

        return back()->with('success', 'تگ افزوده شد.');
    }

    public function destroyTag(int $id): RedirectResponse
    {
        $this->checkManage();
        MediaTag::findOrFail($id)->delete();

        return back()->with('success', 'تگ حذف شد.');
    }

    /**
     * JSON picker endpoint برای استفاده از مخزن در فرم‌های دیگر (banner/blog/...).
     * GET /admin/site/media/picker?kind=image&q=&page=
     */
    public function picker(Request $request): JsonResponse
    {
        $this->checkView();
        $query = Media::query()->with('variants')->latest('created_at');
        if ($kind = $request->query('kind')) {
            $query->where('kind', $kind);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('filename', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $items->map(fn (Media $m) => $m->toApiArray())->values(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }
}
