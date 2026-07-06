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

        $query = BanlistEntry::query()->with('bannedBy:id,first_name,last_name')->orderByDesc('created_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('value', 'like', "%{$q}%");
        }

        $entries = $query->paginate(50)->withQueryString();
        $counts = [
            'phone' => BanlistEntry::where('type', BanlistEntry::TYPE_PHONE)->count(),
            'email' => BanlistEntry::where('type', BanlistEntry::TYPE_EMAIL)->count(),
            'ip' => BanlistEntry::where('type', BanlistEntry::TYPE_IP)->count(),
        ];

        return view('site::admin.forum.banlist', compact('entries', 'counts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'type' => 'required|in:ip,email,phone',
            'value' => 'required|string|max:250',
            'reason' => 'nullable|string|max:250',
            'expires_at_jalali' => 'nullable|string|max:25',
        ]);

        // نرمالایز مقدار بر اساس نوع
        $value = match ($data['type']) {
            BanlistEntry::TYPE_EMAIL => mb_strtolower(trim($data['value'])),
            BanlistEntry::TYPE_PHONE => BanlistEntry::normalizePhone($data['value']) ?? '',
            default => trim($data['value']),
        };

        if ($value === '') {
            return back()->withErrors(['value' => 'مقدار نامعتبر است.'])->withInput();
        }

        if ($data['type'] === BanlistEntry::TYPE_EMAIL && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['value' => 'ایمیل نامعتبر است.'])->withInput();
        }
        if ($data['type'] === BanlistEntry::TYPE_IP && ! filter_var($value, FILTER_VALIDATE_IP)) {
            return back()->withErrors(['value' => 'IP نامعتبر است.'])->withInput();
        }
        if ($data['type'] === BanlistEntry::TYPE_PHONE && ! preg_match('/^09\d{9}$/', $value)) {
            return back()->withErrors(['value' => 'شماره‌ی موبایل نامعتبر است (فرمت 09xxxxxxxxx).'])->withInput();
        }

        // تبدیل تاریخ شمسی → Carbon
        $expiresAt = $this->jalaliToCarbon($data['expires_at_jalali'] ?? null);

        $entry = BanlistEntry::updateOrCreate(
            ['type' => $data['type'], 'value' => $value],
            [
                'reason' => $data['reason'] ?? null,
                'banned_by_user_id' => auth()->id(),
                'expires_at' => $expiresAt,
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

    /**
     * تبدیل تاریخ شمسی (مثلاً 1405/03/15 14:30) به Carbon. ارقام فارسی/عربی پشتیبانی می‌شوند.
     * بازگشت null اگر ورودی خالی یا غیرقابل parse باشد.
     */
    private function jalaliToCarbon(?string $jalali): ?\Carbon\Carbon
    {
        if (! is_string($jalali) || trim($jalali) === '') {
            return null;
        }
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $val = trim(str_replace($fa, $en, str_replace($ar, $en, $jalali)));

        try {
            if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}\s+\d{1,2}:\d{2}$/', $val)) {
                return \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $val)->toCarbon();
            }
            if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $val)) {
                return \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $val)->toCarbon();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
