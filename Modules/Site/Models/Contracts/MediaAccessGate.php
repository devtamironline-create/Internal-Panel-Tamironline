<?php

namespace Modules\Site\Models\Contracts;

use Modules\Site\Models\Media\Media;

/**
 * مدل‌هایی که این interface را پیاده می‌کنند می‌توانند تصمیم بگیرند
 * چه کاربری به Media خصوصی متصل‌به‌خود دسترسی داشته باشد.
 *
 * مثال: Invoice که فقط صاحب فاکتور باید PDF را ببیند.
 *   class Invoice extends Model implements MediaAccessGate {
 *     public function canUserAccessMedia($user, Media $media): bool {
 *       return $user && $user->id === $this->customer_user_id;
 *     }
 *   }
 *
 * MediaServeController روی همه‌ی mediaUses فایل خصوصی پیمایش می‌کند:
 *   - اگر هر owner این interface را داشت و true برگرداند → 200
 *   - در غیر این صورت permission سراسری (manage-site-media) چک می‌شود
 */
interface MediaAccessGate
{
    public function canUserAccessMedia(?\Illuminate\Contracts\Auth\Authenticatable $user, Media $media): bool;
}
