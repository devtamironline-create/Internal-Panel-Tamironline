<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * سوال‌هایی که پاسخِ تأییدشده دارند ولی resolution_status هنوز «unanswered»
 * است (پاسخ‌های AI قبل از فراخوانیِ recomputeResolution منتشر شده بودند) —
 * برچسبِ «بی‌پاسخ» می‌خوردند. backfill با همان منطقِ recomputeResolution.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('site_forum_answers')
            ->where('status', 'approved')
            ->distinct()
            ->pluck('question_id');

        foreach ($ids as $id) {
            $answers = DB::table('site_forum_answers')
                ->where('question_id', $id)
                ->where('status', 'approved')
                ->get(['is_accepted', 'is_expert_reply']);

            $status = $answers->firstWhere('is_accepted', 1)
                ? 'solved'
                : ($answers->where('is_expert_reply', 1)->isNotEmpty() ? 'expert-answered' : 'answered');

            DB::table('site_forum_questions')->where('id', $id)->update([
                'resolution_status' => $status,
                'answers_count' => $answers->count(),
            ]);
        }
    }

    public function down(): void
    {
        // برگشت لازم نیست — دادهٔ اصلاح‌شده صحیح است.
    }
};
