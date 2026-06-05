<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Support\ForumActivityLogger;

/**
 * تنظیمات سراسری انجمن. در site_settings با group='forum' ذخیره می‌شوند.
 */
class SettingsController extends Controller
{
    private const GROUP = 'forum';

    private const KEYS = [
        // notification
        'forum_notify_author_email' => ['type' => 'bool', 'default' => '1'],
        // moderation
        'forum_min_question_length' => ['type' => 'int', 'default' => '10'],
        'forum_min_answer_length' => ['type' => 'int', 'default' => '10'],
        'forum_max_question_length' => ['type' => 'int', 'default' => '100000'],
        'forum_max_answer_length' => ['type' => 'int', 'default' => '50000'],
        'forum_require_email' => ['type' => 'bool', 'default' => '0'],
        'forum_auto_approve_expert' => ['type' => 'bool', 'default' => '1'],
        'forum_auto_approve_trusted' => ['type' => 'bool', 'default' => '0'],
        // rate limits (سفت‌شده، فقط نمایش)
        'forum_questions_per_hour' => ['type' => 'int', 'default' => '3'],
        'forum_answers_per_minute' => ['type' => 'int', 'default' => '5'],
    ];

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkManage();
        $values = [];
        $current = SiteSetting::group(self::GROUP);
        foreach (self::KEYS as $key => $def) {
            $values[$key] = $current[$key] ?? $def['default'];
        }

        return view('site::admin.forum.settings', [
            'values' => $values,
            'schema' => self::KEYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->checkManage();

        $rules = [];
        foreach (self::KEYS as $key => $def) {
            $rules[$key] = match ($def['type']) {
                'bool' => 'nullable|in:0,1',
                'int' => 'nullable|integer|min:0|max:1000000',
                default => 'nullable|string|max:500',
            };
        }
        $data = $request->validate($rules);

        $changed = [];
        foreach (self::KEYS as $key => $def) {
            $value = $data[$key] ?? ($def['type'] === 'bool' ? '0' : $def['default']);
            $old = SiteSetting::get($key);
            SiteSetting::set($key, (string) $value, self::GROUP);
            if ((string) $old !== (string) $value) {
                $changed[$key] = ['from' => $old, 'to' => $value];
            }
        }

        if ($changed) {
            ForumActivityLogger::log('settings:update', meta: $changed);
        }

        return back()->with('success', 'تنظیمات انجمن ذخیره شد.');
    }
}
