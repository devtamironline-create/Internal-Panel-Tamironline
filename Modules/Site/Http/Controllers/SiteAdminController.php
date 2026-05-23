<?php

namespace Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Site\Models\Banner;
use Modules\Site\Models\ContactMessage;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Page;
use Modules\Site\Models\Review;

class SiteAdminController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u) {
            abort(403);
        }
        $permitted = [
            'manage-site', 'manage-permissions',
            'view-site-contact-messages', 'manage-site-contact-messages',
            'manage-site-reviews', 'view-site-reviews',
            'manage-site-testimonials', 'manage-site-device-reviews', 'view-site-device-reviews',
            'manage-site-faqs',
            'manage-site-pages', 'manage-site-banners', 'manage-site-settings',
        ];
        foreach ($permitted as $p) {
            if ($u->can($p)) {
                return;
            }
        }
        abort(403);
    }

    public function dashboard()
    {
        $this->checkAccess();

        $stats = [
            'contact_new' => ContactMessage::where('status', 'new')->count(),
            'contact_total' => ContactMessage::count(),
            'pages' => Page::count(),
            'banners' => Banner::where('is_published', true)->count(),
            'testimonials' => Review::audio()->where('is_published', true)->count(),
            'reviews_pending' => Review::text()->pending()->count(),
            'faqs' => Faq::where('is_published', true)->count(),
        ];

        return view('site::admin.dashboard', compact('stats'));
    }
}
