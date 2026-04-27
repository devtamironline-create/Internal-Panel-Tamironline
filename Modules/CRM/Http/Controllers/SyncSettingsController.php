<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\CRM\Models\CrmSetting;

/**
 * تنظیمات سینک با CRM وردپرسی — مدیریت توکن Bearer.
 */
class SyncSettingsController extends Controller
{
    public function index(): View
    {
        $token = CrmSetting::get('wp_sync_token');

        if (empty($token)) {
            $token = Str::random(64);
            CrmSetting::set('wp_sync_token', $token);
        }

        return view('crm::sync.settings', [
            'token' => $token,
            'baseUrl' => url('/api/crm/sync'),
            'pingUrl' => route('crm.sync.ping'),
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        $token = Str::random(64);
        CrmSetting::set('wp_sync_token', $token);

        return back()->with('success', 'توکن جدید ساخته شد. لطفاً آن را در پلاگین وردپرس به‌روزرسانی کنید.');
    }
}
