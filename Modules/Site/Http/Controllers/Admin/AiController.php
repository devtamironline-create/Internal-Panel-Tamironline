<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Site\Models\AiDecisionLog;
use Modules\Site\Models\AiModel;
use Modules\Site\Models\Comment;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Services\AiGatewayService;
use Modules\Site\Services\AiModerationService;

/**
 * مدیریتِ زیرساختِ AI پنل — رجیستریِ مدل‌ها، تنظیماتِ مودریشن، اجرای مودریشن.
 * همه زیرِ دسترسیِ manage-ai.
 */
class AiController extends Controller
{
    private const MODES = ['assist', 'auto', 'hybrid'];

    private function guard(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-ai') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->guard();

        $models = AiModel::query()->orderByDesc('is_default')->orderBy('name')->get();
        $settings = [
            'mode' => SiteSetting::get('ai_moderation_mode', 'assist'),
            'model' => SiteSetting::get('ai_moderation_model', ''),
            'threshold' => (float) SiteSetting::get('ai_moderation_auto_threshold', '0.85'),
            'reply_mode' => SiteSetting::get('ai_reply_mode', 'off'),
            'reply_model' => SiteSetting::get('ai_reply_model', ''),
            'reply_author' => SiteSetting::get('ai_reply_author', 'تیم تعمیرآنلاین'),
        ];
        $logs = AiDecisionLog::query()->with('aiModel:id,name')
            ->latest()->limit(20)->get();

        return view('site::admin.ai.index', compact('models', 'settings', 'logs'));
    }

    // ─── رجیستریِ مدل‌ها ────────────────────────────────────────────

    public function storeModel(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $this->validateModel($request);

        // اولین مدل به‌صورتِ خودکار پیش‌فرض می‌شود.
        if (AiModel::count() === 0) {
            $data['is_default'] = true;
        }
        if (! empty($data['is_default'])) {
            AiModel::query()->update(['is_default' => false]);
        }
        AiModel::create($data);

        return back()->with('success', 'مدل اضافه شد.');
    }

    public function updateModel(Request $request, AiModel $model): RedirectResponse
    {
        $this->guard();
        $data = $this->validateModel($request, $model);

        // کلیدِ خالی = دست‌نخورده بماند (تا هنگام ویرایش پاک نشود).
        if (($data['api_key'] ?? '') === '') {
            unset($data['api_key']);
        }
        if (! empty($data['is_default'])) {
            AiModel::query()->where('id', '!=', $model->id)->update(['is_default' => false]);
        }
        $model->update($data);

        return back()->with('success', 'مدل به‌روزرسانی شد.');
    }

    public function destroyModel(AiModel $model): RedirectResponse
    {
        $this->guard();
        $model->delete();

        return back()->with('success', 'مدل حذف شد.');
    }

    public function testModel(AiModel $model, AiGatewayService $gateway): RedirectResponse
    {
        $this->guard();
        $res = $gateway->testConnection($model);

        return $res['ok']
            ? back()->with('success', "اتصالِ «{$model->name}» موفق بود. پاسخ: ".trim((string) $res['content']))
            : back()->with('error', "تستِ «{$model->name}» ناموفق: ".$res['error']);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'mode' => 'required|in:'.implode(',', self::MODES),
            'model' => 'nullable|string|max:100',
            'threshold' => 'required|numeric|min:0|max:1',
        ]);

        SiteSetting::set('ai_moderation_mode', $data['mode'], 'ai');
        SiteSetting::set('ai_moderation_model', $data['model'] ?? '', 'ai');
        SiteSetting::set('ai_moderation_auto_threshold', (string) $data['threshold'], 'ai');

        return back()->with('success', 'تنظیماتِ مودریشن ذخیره شد.');
    }

    public function saveReplySettings(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'reply_mode' => 'required|in:off,draft,auto',
            'reply_model' => 'nullable|string|max:100',
            'reply_author' => 'nullable|string|max:100',
        ]);

        SiteSetting::set('ai_reply_mode', $data['reply_mode'], 'ai');
        SiteSetting::set('ai_reply_model', $data['reply_model'] ?? '', 'ai');
        SiteSetting::set('ai_reply_author', trim($data['reply_author'] ?? '') ?: 'تیم تعمیرآنلاین', 'ai');

        return back()->with('success', 'تنظیماتِ پاسخِ خودکار ذخیره شد.');
    }

    // ─── اجرای مودریشن ─────────────────────────────────────────────

    public function moderateComment(Comment $comment, AiModerationService $svc): RedirectResponse
    {
        $this->guard();
        // همان زمینه‌ای که مسیرِ خودکار می‌دهد (مقاله/صفحه + زنجیرهٔ گفتگو) —
        // داوریِ «بی‌ربط بودن» هرگز نباید بدونِ context گرفته شود.
        $msg = $this->runModeration($svc, $comment, (string) $comment->content, array_filter([
            'type' => 'نظر (کامنت)',
            'context' => \Modules\Site\Support\AiContext::forComment($comment),
        ]));

        return back()->with($msg[0], $msg[1]);
    }

    public function moderateQuestion(Question $question, AiModerationService $svc): RedirectResponse
    {
        $this->guard();
        $text = trim(($question->title ?? '')."\n".($question->body ?? ''));
        $msg = $this->runModeration($svc, $question, $text, array_filter([
            'type' => 'سوالِ انجمن',
            'title' => $question->title,
            'context' => \Modules\Site\Support\AiContext::forQuestion($question),
        ]));

        return back()->with($msg[0], $msg[1]);
    }

    /**
     * تحلیل + ثبتِ لاگ + اعمالِ تصمیم بر اساسِ حالت. آرایهٔ [flashKey, message].
     *
     * @param  array<string, mixed>  $context
     * @return array{0:string, 1:string}
     */
    private function runModeration(AiModerationService $svc, Model $subject, string $text, array $context): array
    {
        $res = $svc->analyze($text, $context);

        if (! $res['ok']) {
            // لاگِ خطا (با پاسخِ خامِ مدل، در صورتِ وجود) برای عیب‌یابی.
            AiDecisionLog::create([
                'ai_model_id' => $res['model']?->id,
                'task' => 'moderation',
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'decision' => null,
                'reason' => mb_substr($res['error'] ?? '', 0, 500),
                'applied' => false,
                'raw_response' => ['content' => $res['raw'] ?? null],
                'created_by_user_id' => auth()->id(),
            ]);

            return ['error', 'AI: '.$res['error']];
        }

        $mode = SiteSetting::get('ai_moderation_mode', 'assist');
        $threshold = (float) SiteSetting::get('ai_moderation_auto_threshold', '0.85');
        $decision = $res['decision'];
        $confidence = $res['confidence'];

        // آیا خودکار اعمال شود؟
        $apply = $mode === 'auto'
            || ($mode === 'hybrid' && $decision === 'spam' && ($confidence ?? 0) >= $threshold);

        if ($apply) {
            $this->applyDecision($subject, $decision, $res['reason']);
        }

        AiDecisionLog::create([
            'ai_model_id' => $res['model']?->id,
            'task' => 'moderation',
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'decision' => $decision,
            'confidence' => $confidence,
            'reason' => $res['reason'],
            'applied' => $apply,
            'prompt_tokens' => $res['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $res['usage']['completion_tokens'] ?? null,
            'raw_response' => ['content' => $res['raw']],
            'created_by_user_id' => auth()->id(),
        ]);

        $label = ['approve' => 'تأیید', 'reject' => 'رد', 'spam' => 'اسپم'][$decision] ?? $decision;
        $pct = $confidence !== null ? ' ('.round($confidence * 100).'٪)' : '';
        $reason = $res['reason'] ? ' — '.$res['reason'] : '';

        return ['success', ($apply ? '✅ AI اعمال شد: ' : '🤖 پیشنهادِ AI: ')."{$label}{$pct}{$reason}"];
    }

    private function applyDecision(Model $subject, string $decision, ?string $reason = null): void
    {
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'spam' => 'spam'][$decision] ?? null;
        if (! $status) {
            return;
        }

        $data = ['status' => $status];
        // علتِ ردِ قابلِ‌نمایش به کاربر — روی خودِ رکورد تا فرانت نشانش دهد.
        if (in_array('rejection_reason', $subject->getFillable(), true)) {
            $data['rejection_reason'] = in_array($status, ['rejected', 'spam'], true)
                ? (mb_substr((string) $reason, 0, 500) ?: null)
                : null;
        }
        if ($status === 'approved') {
            $data['approved_at'] = now();
            if (in_array('approved_by_user_id', $subject->getFillable(), true)) {
                $data['approved_by_user_id'] = auth()->id();
            }
            // سوالِ انجمن بدونِ published_at در لیست‌های عمومی نمی‌آید (scopeApproved).
            if (in_array('published_at', $subject->getFillable(), true)) {
                $data['published_at'] = $subject->getAttribute('published_at') ?? now();
            }
        }
        $subject->update($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateModel(Request $request, ?AiModel $model = null): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:150',
            'endpoint' => 'required|url|max:500',
            'api_key' => ($model ? 'nullable' : 'required').'|string|max:2000',
            'model_name' => 'required|string|max:150',
            'max_tokens' => 'nullable|integer|min:1|max:100000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        return [
            'name' => $v['name'],
            'slug' => $model?->slug ?? $this->uniqueSlug($v['name']),
            'endpoint' => rtrim($v['endpoint'], '/'),
            'api_key' => $v['api_key'] ?? '',
            'model_name' => $v['model_name'],
            'max_tokens' => (int) ($v['max_tokens'] ?? 1000),
            'temperature' => (float) ($v['temperature'] ?? 0.2),
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'model';
        $slug = $base;
        $i = 2;
        while (AiModel::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
