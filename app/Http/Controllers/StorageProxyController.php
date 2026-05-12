<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proxy عمومی برای سرو فایل‌های storage/app/public از طریق PHP.
 * جایگزین asset('storage/...') که روی هاست‌های cPanel/LiteSpeed بدون
 * symlink ۴۰۴ می‌شود. فایل‌های این disk عمدتاً avatar تکنسین،
 * عکس دستگاه، لوگوی سایت، صداهای اعلان و … هستند — همه‌شان
 * عمومی‌اند، پس public بودن این endpoint مشکل امنیتی ایجاد نمی‌کند.
 */
class StorageProxyController extends Controller
{
    public function serve(Request $request, string $path): StreamedResponse
    {
        // امنیت: جلوگیری از path traversal
        $clean = ltrim($path, '/');
        if (Str::contains($clean, ['..', '\\']) || Str::startsWith($clean, ['/', '.'])) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($clean)) {
            abort(404);
        }

        // Stream با header های مناسب — image/audio inline در مرورگر باز شود
        $mime = $disk->mimeType($clean) ?: 'application/octet-stream';
        $size = $disk->size($clean);

        return response()->stream(function () use ($disk, $clean) {
            $stream = $disk->readStream($clean);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'inline; filename="' . basename($clean) . '"',
            'Cache-Control'  => 'public, max-age=3600',
        ]);
    }
}
