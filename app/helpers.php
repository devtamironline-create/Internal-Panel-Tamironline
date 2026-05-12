<?php

if (! function_exists('storage_url')) {
    /**
     * URL یک فایل در public disk — جایگزین asset('storage/...') که روی
     * هاست‌های بدون symlink ۴۰۴ می‌شود. این تابع همیشه از route Laravel
     * استفاده می‌کند که فایل را با PHP استریم می‌کند.
     *
     * @param  string|null  $path  مسیر نسبی داخل storage/app/public
     * @return string|null
     */
    function storage_url(?string $path): ?string
    {
        if (! $path) return null;
        return route('storage.proxy', ['path' => $path]);
    }
}
