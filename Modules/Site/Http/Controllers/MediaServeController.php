<?php

namespace Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Site\Models\Media\Media;
use Modules\Site\Models\Media\MediaVariant;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public route برای serve فایل‌های مدیا.
 *
 *   GET /media/{id}/{name?}                  ← فایل اصلی
 *   GET /media/{id}/v/{variant}/{name?}     ← variant
 *
 * مزایا:
 *   - مستقل از symlink (`storage:link` لازم نیست)
 *   - permissions check برای visibility='private'
 *   - ETag + Cache-Control: immutable برای cache بلندمدت (نام = hash → immutable)
 *   - 304 conditional برای صرفه‌جویی bandwidth
 *   - Range support خودکار (BinaryFileResponse)
 */
class MediaServeController extends Controller
{
    public function serve(Request $request, int $id): Response
    {
        $media = Media::find($id);
        if (! $media) {
            abort(404);
        }
        $this->authorizeAccess($request, $media);

        return $this->fileResponse($request, $media->path, $media->mime, $media->hash);
    }

    public function serveVariant(Request $request, int $id, string $variant): Response
    {
        $media = Media::find($id);
        if (! $media) {
            abort(404);
        }
        $this->authorizeAccess($request, $media);

        $v = MediaVariant::where('media_id', $id)->where('variant', $variant)->first();
        if (! $v) {
            return $this->fileResponse($request, $media->path, $media->mime, $media->hash);
        }

        return $this->fileResponse($request, $v->path, $v->mime, $media->hash.'-'.$variant);
    }

    private function authorizeAccess(Request $request, Media $media): void
    {
        if ($media->visibility !== Media::VIS_PRIVATE) {
            return;
        }
        $user = $request->user();

        // ۱) Signed URL — برای download یک‌بارمصرف ایمیل/SMS
        if ($request->hasValidSignature(false)) {
            return;
        }

        // ۲) چک per-entity: روی هر useable از media می‌رویم و اگر
        //    آن مدل MediaAccessGate پیاده کرده باشد و اجازه دهد، عبور می‌کنیم
        $uses = $media->uses()->with('useable')->get();
        foreach ($uses as $use) {
            $owner = $use->useable;
            if ($owner instanceof \Modules\Site\Models\Contracts\MediaAccessGate) {
                if ($owner->canUserAccessMedia($user, $media)) {
                    return;
                }
            }
        }

        // ۳) Fallback به permission سراسری ادمین (محرمانه‌های داخلی)
        if ($user && (
            $user->can('view-site-media')
            || $user->can('manage-site-media')
            || $user->can('manage-site')
            || $user->can('manage-permissions')
        )) {
            return;
        }

        abort(403);
    }

    private function fileResponse(Request $request, string $relativePath, string $mime, string $etagSource): Response
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }
        $absolute = $disk->path($relativePath);
        $etag = '"'.substr($etagSource, 0, 32).'"';

        // Conditional GET
        if (trim($request->headers->get('If-None-Match', '')) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=31536000, immutable');
        }

        $response = new BinaryFileResponse($absolute, 200, [
            'Content-Type' => $mime,
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
        // X-Sendfile / X-Accel-Redirect در صورت پشتیبانی وب‌سرور
        BinaryFileResponse::trustXSendfileTypeHeader();

        return $response;
    }
}
