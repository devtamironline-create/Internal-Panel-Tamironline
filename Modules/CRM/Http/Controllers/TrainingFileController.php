<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\TrainingVideo;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * سرو کردن فایل‌های ویدیو/تامبنیل آموزش از طریق Laravel به‌جای دسترسی
 * مستقیم /storage. روی هاست‌های اشتراکی LiteSpeed/cPanel که symlink
 * یا FollowSymLinks محدود است، asset('storage/...') اغلب ۴۰۳/۴۰۴
 * برمی‌گرداند. این روت دسترسی را مستقیماً از طریق PHP/Storage::disk
 * می‌دهد.
 *
 * استریم ویدیو HTTP Range (206) را کامل پشتیبانی می‌کند — لازمهٔ seek
 * و از همه مهم‌تر «ادامه از همان‌جا» روی نتِ ضعیف: با هر قطعی، مرورگر
 * فقط بایت‌های باقی‌مانده را می‌خواهد، نه کل فایل را از اول.
 *
 * احراز هویت: یا کاربر ادمین لاگین است (Auth::check) یا تکنسین از
 * طریق guard tech. در غیر این صورت 403.
 */
class TrainingFileController extends Controller
{
    /** اندازهٔ هر تکهٔ خواندن/فلاش — کوچک، تا اتصالِ کند هم جریان بگیرد. */
    private const CHUNK_BYTES = 65536; // 64KB

    public function streamVideo(Request $request, TrainingVideo $video)
    {
        $this->ensureAuthenticated();

        // تکنسین فقط ویدیوهای فعال را می‌بیند؛ ادمین می‌تواند غیرفعال‌ها را
        // برای تست/ویرایش هم پلی کند.
        if (! $video->is_active && ! Auth::check()) {
            abort(404);
        }

        // ?q=low → نسخهٔ کم‌حجم برای نت ضعیف (اگر آپلود شده باشد).
        $relative = $request->query('q') === 'low' && $video->video_low_url
            ? $video->video_low_url
            : $video->video_url;

        if (! $video->is_local || ! $relative) {
            abort(404);
        }
        if (! Storage::disk('public')->exists($relative)) {
            abort(404);
        }

        return $this->rangeStream($request, $relative);
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
            ['Content-Disposition' => 'inline; filename="'.basename($video->thumbnail).'"']
        );
    }

    /**
     * پاسخ استریم با پشتیبانی کامل Range/206.
     *
     *   بدون Range   → 200 + کل فایل (chunked)
     *   bytes=a-b    → 206 + همان بازه
     *   bytes=a-     → 206 از a تا انتها
     *   بازهٔ نامعتبر → 416 با هدر Content-Range کل‌اندازه
     */
    private function rangeStream(Request $request, string $relative): StreamedResponse
    {
        $disk = Storage::disk('public');
        $size = (int) $disk->size($relative);
        $mime = $disk->mimeType($relative) ?: 'video/mp4';
        $name = basename($relative);

        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = (string) $request->header('Range', '');
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            if ($m[1] === '' && $m[2] === '') {
                abort(416, '', ['Content-Range' => 'bytes */'.$size]);
            }

            if ($m[1] === '') {
                // bytes=-N → N بایتِ آخر
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min((int) $m[2], $size - 1);
                }
            }

            if ($start > $end || $start >= $size) {
                abort(416, '', ['Content-Range' => 'bytes */'.$size]);
            }

            $status = 206;
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            // فایل آموزش تغییر نمی‌کند — کشِ مرورگر دانلود دوباره را کم می‌کند.
            'Cache-Control' => 'private, max-age=604800',
            'X-Accel-Buffering' => 'no',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
        }

        // HEAD: فقط هدرها — پلیرها برای گرفتن اندازه استفاده می‌کنند.
        if ($request->isMethod('HEAD')) {
            return response()->stream(fn () => null, $status, $headers);
        }

        return response()->stream(function () use ($disk, $relative, $start, $length) {
            $stream = $disk->readStream($relative);
            if ($stream === null) {
                return;
            }

            if ($start > 0) {
                fseek($stream, $start);
            }

            $remaining = $length;
            while ($remaining > 0 && ! feof($stream) && connection_aborted() === 0) {
                $chunk = fread($stream, min(self::CHUNK_BYTES, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
                $remaining -= strlen($chunk);
            }

            fclose($stream);
        }, $status, $headers);
    }

    private function ensureAuthenticated(): void
    {
        if (! Auth::check() && ! Auth::guard('tech')->check()) {
            abort(403, 'برای دسترسی به فایل‌های آموزش ابتدا وارد شوید.');
        }
    }
}
