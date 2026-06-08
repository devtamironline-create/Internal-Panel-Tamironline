<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StoreBannerRequest;
use Modules\Site\Models\Banner;
use Modules\Site\Models\BannerZone;
use Modules\Site\Models\Media\Media;

class BannerController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-site-banners') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkAccess();

        $query = Banner::query()->with(['zone', 'media.variants'])
            ->orderBy('zone_id')->orderBy('sort_order')->orderByDesc('created_at');

        if ($zoneId = $request->query('zone_id')) {
            $query->where('zone_id', (int) $zoneId);
        }

        $items = $query->paginate(20)->withQueryString();
        $zones = BannerZone::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('site::admin.banners.index', compact('items', 'zones'));
    }

    public function create(): View
    {
        $this->checkAccess();
        $zones = BannerZone::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return view('site::admin.banners.create', compact('zones'));
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        if (! empty($data['media_id']) && empty($data['image_url'])) {
            if ($media = Media::find($data['media_id'])) {
                $data['image_url'] = $media->url();
            }
        }
        if (empty($data['position']) && ! empty($data['zone_id'])) {
            $data['position'] = optional(BannerZone::find($data['zone_id']))->slug ?? 'home_hero';
        }

        $banner = Banner::create($data);
        if (! empty($data['media_id'])) {
            $banner->attachMedia((int) $data['media_id'], 'image');
        }
        if (! empty($data['media_id_mobile'])) {
            $banner->attachMedia((int) $data['media_id_mobile'], 'image_mobile');
        }

        return redirect()->route('site.admin.banners.index')->with('success', 'بنر ایجاد شد.');
    }

    public function edit(string $id): View
    {
        $this->checkAccess();
        $banner = Banner::with(['media.variants', 'mediaMobile.variants'])->findOrFail($id);
        $zones = BannerZone::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return view('site::admin.banners.edit', compact('banner', 'zones'));
    }

    public function update(StoreBannerRequest $request, string $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        if (! empty($data['media_id']) && empty($data['image_url'])) {
            if ($media = Media::find($data['media_id'])) {
                $data['image_url'] = $media->url();
            }
        }
        if (empty($data['position']) && ! empty($data['zone_id'])) {
            $data['position'] = optional(BannerZone::find($data['zone_id']))->slug ?? $banner->position;
        }

        $banner->update($data);
        $banner->detachAllMedia();
        if (! empty($data['media_id'])) {
            $banner->attachMedia((int) $data['media_id'], 'image');
        }
        if (! empty($data['media_id_mobile'])) {
            $banner->attachMedia((int) $data['media_id_mobile'], 'image_mobile');
        }

        return redirect()->route('site.admin.banners.index')->with('success', 'بنر به‌روز شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkAccess();
        $banner = Banner::findOrFail($id);
        $banner->detachAllMedia();
        $banner->delete();

        return redirect()->route('site.admin.banners.index')->with('success', 'بنر حذف شد.');
    }
}
