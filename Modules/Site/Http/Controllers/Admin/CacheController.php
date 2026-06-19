<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Support\FrontendRevalidator;

class CacheController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-settings')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkAccess();

        $config = [
            'revalidate_url'     => SiteSetting::get('revalidate_url'),
            'revalidate_secret'  => SiteSetting::get('revalidate_secret'),
            'revalidate_enabled' => SiteSetting::get('revalidate_enabled'),
        ];

        return view('site::admin.cache.index', compact('config'));
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $this->checkAccess();

        $data = $request->validate([
            'revalidate_url'     => ['nullable', 'url', 'max:300'],
            'revalidate_secret'  => ['nullable', 'string', 'max:200'],
            'revalidate_enabled' => ['nullable', 'in:1'],
        ]);

        $url = isset($data['revalidate_url']) ? trim((string) $data['revalidate_url']) : '';
        SiteSetting::set('revalidate_url', $url !== '' ? $url : null, 'frontend');

        // رمز فقط در صورت ورود مقدار جدید به‌روزرسانی می‌شود تا فیلد خالی، رمز فعلی را پاک نکند.
        $secret = isset($data['revalidate_secret']) ? trim((string) $data['revalidate_secret']) : '';
        if ($secret !== '') {
            SiteSetting::set('revalidate_secret', $secret, 'frontend');
        }

        SiteSetting::set('revalidate_enabled', ($data['revalidate_enabled'] ?? null) === '1' ? '1' : null, 'frontend');

        return back()->with('success', 'تنظیمات اتصال به فرانت ذخیره شد.');
    }

    public function purge(Request $request, FrontendRevalidator $revalidator): RedirectResponse
    {
        $this->checkAccess();

        $data = $request->validate([
            'mode'  => ['required', 'in:all,paths'],
            'paths' => ['nullable', 'string'],
        ]);

        if ($data['mode'] === 'all') {
            $result = $revalidator->purgeAll();
        } else {
            $paths = collect(preg_split('/\r\n|\r|\n/', (string) ($data['paths'] ?? '')))
                ->map(fn ($p) => trim($p))
                ->filter(fn ($p) => $p !== '')
                ->values()
                ->all();

            if ($paths === []) {
                return back()->withErrors(['paths' => 'حداقل یک مسیر وارد کنید.']);
            }

            $result = $revalidator->purgePaths($paths);
        }

        return back()->with('purge_result', $result);
    }

    public function test(FrontendRevalidator $revalidator): RedirectResponse
    {
        $this->checkAccess();

        $result = $revalidator->purge(['/'], [], false);

        return back()->with('purge_result', $result);
    }
}
