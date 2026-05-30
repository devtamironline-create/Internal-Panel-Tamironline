<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Site\Models\Forum\Expert;

class ExpertController extends Controller
{
    private function check(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-forum-experts') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->check();
        $query = Expert::query()->withCount('answers');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"));
        }
        $experts = $query->ranked()->paginate(20)->withQueryString();

        return view('site::admin.forum.experts.index', compact('experts'));
    }

    public function create(): View
    {
        $this->check();

        return view('site::admin.forum.experts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request);
        $this->applyDefaults($data, true);
        $expert = Expert::create($data);

        // ثبت Media use برای reverse-lookup
        $expert->detachAllMedia();
        if (! empty($expert->avatar_media_id)) {
            $expert->attachMedia((int) $expert->avatar_media_id, 'avatar');
        }

        return redirect()->route('site.admin.forum.experts.index')->with('success', 'کارشناس افزوده شد.');
    }

    public function edit(Expert $expert): View
    {
        $this->check();

        return view('site::admin.forum.experts.edit', compact('expert'));
    }

    public function update(Request $request, Expert $expert): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request, $expert->id);
        $this->applyDefaults($data, false);
        $expert->update($data);

        // ثبت Media use برای reverse-lookup
        $expert->detachAllMedia();
        if (! empty($expert->avatar_media_id)) {
            $expert->attachMedia((int) $expert->avatar_media_id, 'avatar');
        }

        return redirect()->route('site.admin.forum.experts.index')->with('success', 'کارشناس به‌روز شد.');
    }

    public function destroy(Expert $expert): RedirectResponse
    {
        $this->check();
        $expert->delete();

        return redirect()->route('site.admin.forum.experts.index')->with('success', 'کارشناس حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|unique:site_forum_experts,slug'.($id ? ','.$id : ''),
            'title' => 'nullable|string|max:200',
            'avatar' => 'nullable|string|max:500',
            'avatar_media_id' => 'nullable|integer|exists:site_media,id',
            'bio' => 'nullable|string|max:5000',
            'user_id' => 'nullable|integer|exists:users,id',
            'rating' => 'nullable|numeric|min:0|max:5',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function applyDefaults(array &$data, bool $isNew): void
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $data['slug'])) {
            throw ValidationException::withMessages(['slug' => 'اسلاگ باید English kebab-case باشد.']);
        }
        $data['rating'] = $data['rating'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? ($isNew ? true : false));

        // اگر media انتخاب شده، URL آن را در avatar بگذار
        if (! empty($data['avatar_media_id'])) {
            $m = \Modules\Site\Models\Media\Media::find($data['avatar_media_id']);
            if ($m) {
                $data['avatar'] = $m->url();
            }
        }
    }
}
