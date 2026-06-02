<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\Forum\BanlistEntry;
use Modules\Site\Support\ForumActivityLogger;

/**
 * مدیریت banlist انجمن — مسدودسازی IP یا ایمیل از submit سوال/پاسخ.
 */
class BanlistController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('view-forum') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('moderate-forum-questions') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = BanlistEntry::query()->with('bannedBy:id,name')->orderByDesc('created_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('value', 'like', "%{$q}%");
        }

        $entries = $query->paginate(50)->withQueryString();
        $counts = [
            'ip' => BanlistEntry::where('type', 'ip')->count(),
            'email' => BanlistEntry::where('type', 'email')->count(),
        ];

        return view('site::admin.forum.banlist', compact('entries', 'counts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'type' => 'required|in:ip,email',
            'value' => 'required|string|max:250',
            'reason' => 'nullable|string|max:250',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $value = $data['type'] === 'email'
            ? mb_strtolower(trim($data['value']))
            : trim($data['value']);

        // اعتبارسنجی ساده‌ی IP/email
        if ($data['type'] === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['value' => 'ایمیل نامعتبر است.'])->withInput();
        }
        if ($data['type'] === 'ip' && ! filter_var($value, FILTER_VALIDATE_IP)) {
            return back()->withErrors(['value' => 'IP نامعتبر است.'])->withInput();
        }

        $entry = BanlistEntry::updateOrCreate(
            ['type' => $data['type'], 'value' => $value],
            [
                'reason' => $data['reason'] ?? null,
                'banned_by_user_id' => auth()->id(),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        ForumActivityLogger::log(
            'banlist:add',
            meta: ['type' => $entry->type, 'value' => $entry->value, 'reason' => $entry->reason, 'expires_at' => $entry->expires_at?->toIso8601String()],
        );

        return back()->with('success', "«{$value}» به لیست بن اضافه شد.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkManage();
        $entry = BanlistEntry::findOrFail($id);
        ForumActivityLogger::log('banlist:remove', meta: ['type' => $entry->type, 'value' => $entry->value]);
        $entry->delete();

        return back()->with('success', 'مورد از لیست بن حذف شد.');
    }
}
