<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Site\Models\Article;
use Modules\Site\Models\Comment;
use Modules\Site\Support\MediaUrl;

/**
 * API عمومی کامنت — لیست/ثبت/لایک. polymorphic؛ الان فقط روی Article
 * فعال است (روت‌های /v1/blog/articles/{slug}/comments). برای Device/Brand
 * در آینده resolveOwner() را گسترش دهید.
 */
class CommentController extends Controller
{
    /**
     * GET /v1/blog/articles/{slug}/comments?page=N&limit=M
     * فقط کامنت‌های approved + ساختار threaded (top-level + replies).
     */
    public function indexForArticle(Request $request, string $slug): JsonResponse
    {
        $article = Article::query()->published()->where('slug', $slug)->first(['id']);
        if (! $article) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return $this->buildList($request, Article::class, (int) $article->id);
    }

    /**
     * POST /v1/blog/articles/{slug}/comments
     */
    public function storeForArticle(Request $request, string $slug): JsonResponse
    {
        $article = Article::query()->published()->where('slug', $slug)->first(['id']);
        if (! $article) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return $this->createComment($request, Article::class, (int) $article->id);
    }

    /**
     * POST /v1/comments/{id}/like
     */
    public function like(Request $request, int $id): JsonResponse
    {
        $reaction = $request->input('reaction', 'like');
        if (! in_array($reaction, ['like', 'dislike'], true)) {
            return response()->json(['message' => 'invalid reaction'], 422);
        }

        $comment = Comment::query()->approved()->find($id);
        if (! $comment) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $ip = $request->ip();

        // INSERT IGNORE → اگر همین IP قبلاً واکنش داده، duplicate صرف‌نظر می‌شود
        $inserted = false;
        try {
            DB::table('site_comment_likes')->insert([
                'comment_id' => $comment->id,
                'ip' => $ip,
                'reaction' => $reaction,
                'created_at' => now(),
            ]);
            $inserted = true;
        } catch (\Illuminate\Database\QueryException $e) {
            // duplicate
        }

        if ($inserted) {
            $col = $reaction === 'like' ? 'likes_count' : 'dislikes_count';
            $comment->increment($col);
            $comment->refresh();
        }

        return response()->json([
            'likes' => (int) $comment->likes_count,
            'dislikes' => (int) $comment->dislikes_count,
        ]);
    }

    private function buildList(Request $request, string $ownerType, int $ownerId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = max(1, min((int) $request->query('limit', 20), 100));

        $base = Comment::query()
            ->approved()
            ->where('commentable_type', $ownerType)
            ->where('commentable_id', $ownerId);

        $topLevelTotal = (clone $base)->topLevel()->count();

        // top-level paginated
        $topLevel = (clone $base)->topLevel()
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        // replies برای این top-level ها — یک کوئری اضافه‌تر
        $rootIds = $topLevel->pluck('id')->all();
        $replies = empty($rootIds) ? collect() : Comment::query()
            ->approved()
            ->where('commentable_type', $ownerType)
            ->where('commentable_id', $ownerId)
            ->whereIn('parent_id', $rootIds)
            ->orderBy('created_at')
            ->get();

        $repliesByParent = $replies->groupBy('parent_id');

        $data = $topLevel->map(fn (Comment $c) => array_merge($this->shape($c), [
            'replies' => ($repliesByParent->get($c->id) ?? collect())
                ->map(fn (Comment $r) => $this->shape($r))->values(),
        ]))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $topLevelTotal,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $topLevelTotal > 0 ? (int) ceil($topLevelTotal / $limit) : 1,
            ],
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    private function createComment(Request $request, string $ownerType, int $ownerId): JsonResponse
    {
        $data = $request->validate([
            'author_name' => 'required|string|max:80',
            'author_email' => 'nullable|email|max:120',
            'content' => 'required|string|min:5|max:3000',
            'parent_id' => ['nullable', 'integer', Rule::exists('site_comments', 'id')->where(fn ($q) => $q
                ->where('commentable_type', $ownerType)
                ->where('commentable_id', $ownerId)
                ->where('status', Comment::STATUS_APPROVED)
            )],
        ]);

        $rootId = null;
        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->find($data['parent_id']);
            $rootId = $parent?->root_id ?? $parent?->id;
        }

        $comment = Comment::create([
            'commentable_type' => $ownerType,
            'commentable_id' => $ownerId,
            'parent_id' => $data['parent_id'] ?? null,
            'root_id' => $rootId,
            'author_name' => trim($data['author_name']),
            'author_email' => isset($data['author_email']) ? strtolower(trim($data['author_email'])) : null,
            'content' => trim($data['content']),
            'status' => Comment::STATUS_PENDING,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'id' => (int) $comment->id,
            'status' => $comment->status,
            'message' => 'نظر شما دریافت شد و پس از تأیید نمایش داده می‌شود.',
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Comment $c): array
    {
        return [
            'id' => (int) $c->id,
            'author_name' => $c->author_name,
            'author_avatar' => MediaUrl::resolve($c->author_avatar),
            'is_admin_reply' => (bool) $c->is_admin_reply,
            'content' => $c->content,
            'likes' => (int) $c->likes_count,
            'dislikes' => (int) $c->dislikes_count,
            'created_at' => $c->created_at?->utc()->toIso8601ZuluString(),
            'parent_id' => $c->parent_id ? (int) $c->parent_id : null,
        ];
    }
}
