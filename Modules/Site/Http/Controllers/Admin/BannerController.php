<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StoreBannerRequest;
use Modules\Site\Models\Banner;

class BannerController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-banners')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkAccess();

        $query = Banner::query()
            ->orderBy('position')
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if ($position = $request->query('position')) {
            $query->where('position', $position);
        }

        $items = $query->paginate(20)->withQueryString();
        $positions = Banner::query()->distinct()->pluck('position')->all();

        return view('site::admin.banners.index', compact('items', 'positions'));
    }

    public function create(): View
    {
        $this->checkAccess();
        return view('site::admin.banners.create');
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        Banner::create($data);

        return redirect()
            ->route('site.admin.banners.index')
            ->with('success', 'بنر ایجاد شد.');
    }

    public function edit(string $id): View
    {
        $this->checkAccess();
        $banner = Banner::findOrFail($id);
        return view('site::admin.banners.edit', compact('banner'));
    }

    public function update(StoreBannerRequest $request, string $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        $banner->update($data);

        return redirect()
            ->route('site.admin.banners.index')
            ->with('success', 'بنر به‌روز شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkAccess();
        Banner::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.banners.index')
            ->with('success', 'بنر حذف شد.');
    }
}
