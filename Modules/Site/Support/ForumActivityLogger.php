<?php

namespace Modules\Site\Support;

use Modules\Site\Models\Forum\ActivityLog;

/**
 * Helper یک‌خطی برای ثبت action ها در audit log انجمن.
 *
 *   ForumActivityLogger::log('approve', questionId: 12);
 *   ForumActivityLogger::log('bulk-delete', meta: ['ids' => [1,2,3], 'count' => 3]);
 *
 * هیچ خطایی throw نمی‌کند — اگر ثبت log fail شد، عملیات اصلی متوقف نمی‌شود.
 */
final class ForumActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function log(
        string $action,
        ?int $questionId = null,
        ?int $answerId = null,
        ?array $meta = null,
    ): void {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'question_id' => $questionId,
                'answer_id' => $answerId,
                'meta' => $meta,
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // intentionally swallow — log shouldn't break write path
        }
    }
}
