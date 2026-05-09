<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\TrainingVideo;

/**
 * سرو کردن فایل‌های ویدیو/تامبنیل آموزش از طریق Laravel به‌جای دسترسی
 * مستقیم /storage. روی هاست‌های اشتراکی LiteSpeed/cPanel که symlink
 * یا FollowSymLinks محدود است، asset('storage/...') اغلب ۴۰۳/۴۰۴
 * برمی‌گرداند. این روت دسترسی را مستقیماً از طریق PHP/Storage::disk
 * می‌دهد.
 *
 * احراز هویت: یا کاربر ادمین لاگین است (Auth::check) یا تکنسین از
 * طریق guard tech. در غیر این صورت 403.
 */
class TrainingFileController extends Controller
{
    public function streamVideo(TrainingVideo $video)
    {
        $this->ensureAuthenticated();

        // تکنسین فقط ویدیوهای فعال را می‌بیند؛ ادمین می‌تواند غیرفعال‌ها را
        // برای تست/ویرایش هم پلی کند.
        if (! $video->is_active && ! Auth::check()) {
            abort(404);
        }

        if (! $video->is_local || ! $video->video_url) {
            abort(404);
        }
        if (! Storage::disk('public')->exists($video->video_url)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $video->video_url,
            basename($video->video_url),
            ['Content-Disposition' => 'inline; filename="' . basename($video->video_url) . '"']
        );
    }

    public function streamThumbnail(TrainingVideo $video)
    {
        $this->ensureAuthenticated();

        if (! $video->thumbnail || ! Storage::disk('public')->exists($video->thumbnail)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $video->thumbnail,
            basename($video->thumbnail),
            ['Content-Disposition' => 'inline; filename="' . basename($video->thumbnail) . '"']
        );
    }

    private function ensureAuthenticated(): void
    {
        if (! Auth::check() && ! Auth::guard('tech')->check()) {
            abort(403, 'برای دسترسی به فایل‌های آموزش ابتدا وارد شوید.');
        }
    }
}
