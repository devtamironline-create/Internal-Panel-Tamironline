<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\Article;
use Modules\Site\Models\Comment;

/**
 * پنل مدیریت موشکافانه‌ی کامنت‌ها — moderation فوق‌حرفه‌ای:
 *   - فیلتر بر اساس وضعیت/نوع owner/owner-id/جستجو
 *   - bulk approve/reject/spam/delete
 *   - reply مستقیم admin (با is_admin_reply=true)
 *   - مشاهده‌ی thread کامل
 *   - شمارش هر وضعیت برای dashboard inline
 */
class CommentController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('view-site-comments')
            && ! $u->can('manage-site-comments')
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
            ! $u->can('manage-site-comments')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = Comment::query()->with(['parent:id,author_name,content', 'commentable']);

        // فیلتر وضعیت
        if ($status = $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'spam'], true)) {
                $query->where('status', $status);
            }
        }

        // فیلتر owner type — مثل ?owner=article یا ?owner=device
        if ($ownerKey = $request->query('owner')) {
            $cls = $this->ownerKeyToClass($ownerKey);
            if ($cls) {
                $query->where('commentable_type', $cls);
            }
        }

        // فیلتر slug owner — برای محدود کردن به یک مقاله/دستگاه خاص
        if ($ownerSlug = trim((string) $request->query('owner_slug', ''))) {
            $ids = $this->resolveOwnerIdsBySlug($ownerSlug);
            if (! empty($ids)) {
                $query->whereIn('commentable_id', $ids);
            } else {
                $query->whereRaw('1 = 0'); // پیدا نشد → خالی
            }
        }

        // جستجو
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('author_name', 'like', "%{$q}%")
                ->orWhere('author_email', 'like', "%{$q}%")
                ->orWhere('content', 'like', "%{$q}%"));
        }

        $comments = $query->latest('created_at')->paginate(20)->withQueryString();

        $counts = [
            'all' => Comment::count(),
            'pending' => Comment::where('status', Comment::STATUS_PENDING)->count(),
            'approved' => Comment::where('status', Comment::STATUS_APPROVED)->count(),
            'rejected' => Comment::where('status', Comment::STATUS_REJECTED)->count(),
            'spam' => Comment::where('status', Comment::STATUS_SPAM)->count(),
        ];

        return view('site::admin.comments.index', compact('comments', 'counts'));
    }

    public function show(int $id): View
    {
        $this->checkView();
        $comment = Comment::with(['parent.commentable', 'commentable', 'replies'])->findOrFail($id);

        // thread کامل (همه‌ی کامنت‌های هم‌ریشه)
        $rootId = $comment->root_id ?? $comment->id;
        $thread = Comment::query()
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('root_id', $rootId);
            })
            ->orderBy('created_at')
            ->get();

        return view('site::admin.comments.show', compact('comment', 'thread'));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected,spam',
        ]);

        $comment = Comment::findOrFail($id);
        $comment->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === Comment::STATUS_APPROVED ? now() : null,
            'approved_by_user_id' => $data['status'] === Comment::STATUS_APPROVED ? auth()->id() : null,
        ]);

        return back()->with('success', 'وضعیت کامنت به‌روز شد.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'action' => 'required|in:approve,reject,spam,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:site_comments,id',
        ]);

        $ids = $data['ids'];
        $count = 0;

        switch ($data['action']) {
            case 'approve':
                $count = Comment::whereIn('id', $ids)->update([
                    'status' => Comment::STATUS_APPROVED,
                    'approved_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                ]);
                break;
            case 'reject':
                $count = Comment::whereIn('id', $ids)->update(['status' => Comment::STATUS_REJECTED, 'approved_at' => null]);
                break;
            case 'spam':
                $count = Comment::whereIn('id', $ids)->update(['status' => Comment::STATUS_SPAM, 'approved_at' => null]);
                break;
            case 'delete':
                $count = Comment::whereIn('id', $ids)->delete();
                break;
        }

        return back()->with('success', "اقدام «{$data['action']}» روی {$count} کامنت اعمال شد.");
    }

    /**
     * پاسخ ادمین به یک کامنت — به‌صورت reply با is_admin_reply=true و
     * status=approved (نیاز به moderation ندارد).
     */
    public function reply(Request $request, int $id): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'content' => 'required|string|min:3|max:3000',
        ]);

        $parent = Comment::findOrFail($id);
        $rootId = $parent->root_id ?? $parent->id;
        $user = auth()->user();

        Comment::create([
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
            'parent_id' => $parent->id,
            'root_id' => $rootId,
            'user_id' => $user?->id,
            'author_name' => $user?->name ?? 'تیم تعمیرآنلاین',
            'is_admin_reply' => true,
            'content' => trim($data['content']),
            'status' => Comment::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $user?->id,
        ]);

        return back()->with('success', 'پاسخ ثبت و منتشر شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkManage();
        Comment::findOrFail($id)->delete();

        return redirect()->route('site.admin.comments.index')->with('success', 'کامنت حذف شد.');
    }

    /**
     * تبدیل ?owner=article|device|brand به FQCN مدل.
     */
    private function ownerKeyToClass(string $key): ?string
    {
        return match ($key) {
            'article' => Article::class,
            'device' => \Modules\CRM\Models\Device::class,
            'brand' => \Modules\CRM\Models\Brand::class,
            default => null,
        };
    }

    /**
     * @return array<int, int>
     */
    private function resolveOwnerIdsBySlug(string $slug): array
    {
        $ids = [];
        $ids = array_merge($ids, Article::query()->where('slug', $slug)->pluck('id')->all());
        $ids = array_merge($ids, \Modules\CRM\Models\Device::query()->where('slug', $slug)->pluck('id')->all());
        $ids = array_merge($ids, \Modules\CRM\Models\Brand::query()->where('slug', $slug)->pluck('id')->all());

        return array_values(array_unique($ids));
    }
}
