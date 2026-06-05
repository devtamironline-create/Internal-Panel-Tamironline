<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StoreAboutStatRequest;
use Modules\Site\Models\AboutStat;

class AboutStatController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-settings')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkAccess();

        $items = AboutStat::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('site::admin.about-stats.index', compact('items'));
    }

    public function create(): View
    {
        $this->checkAccess();
        return view('site::admin.about-stats.create');
    }

    public function store(StoreAboutStatRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        AboutStat::create($data);

        return redirect()
            ->route('site.admin.about-stats.index')
            ->with('success', 'آمار ایجاد شد.');
    }

    public function edit(int $id): View
    {
        $this->checkAccess();
        $stat = AboutStat::findOrFail($id);
        return view('site::admin.about-stats.edit', compact('stat'));
    }

    public function update(StoreAboutStatRequest $request, int $id): RedirectResponse
    {
        $stat = AboutStat::findOrFail($id);
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        $stat->update($data);

        return redirect()
            ->route('site.admin.about-stats.index')
            ->with('success', 'آمار به‌روز شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkAccess();
        AboutStat::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.about-stats.index')
            ->with('success', 'آمار حذف شد.');
    }
}
