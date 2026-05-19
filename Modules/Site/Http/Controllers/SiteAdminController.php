<?php

namespace Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;

class SiteAdminController extends Controller
{
    private function checkAccess(): void
    {
        if (!auth()->user()->can('manage-site') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }
    }

    /**
     * داشبورد مدیریت سایت
     */
    public function dashboard()
    {
        $this->checkAccess();

        return view('site::admin.dashboard');
    }
}
