<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Support\UploadLimits;
use Symfony\Component\HttpFoundation\Response;

/**
 * وقتی کلِ درخواست از `post_max_size` رد می‌شود، PHP بدنه را *کامل* دور
 * می‌اندازد: هم `$_POST` خالی می‌شود هم `$_FILES`.
 *
 * نتیجه‌اش برای تکنسین بدترین حالتِ ممکن است — فرم را کامل پر کرده و
 * عکس هم گرفته، ولی خطاهایی می‌بیند که می‌گویند «توضیحات الزامی است»،
 * «وضعیت الزامی است». هیچ‌کدام درست نیست و هیچ‌کدام نمی‌گوید مشکل از
 * حجمِ عکس بوده.
 *
 * این میان‌افزار همان‌جا جلویش را می‌گیرد و ۴۱۳ با پیامِ درست برمی‌گرداند.
 */
class RejectOversizedUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! UploadLimits::requestExceededPostMax()) {
            return $next($request);
        }

        $message = 'حجم اطلاعات ارسالی بیشتر از حد مجاز سرور است — معمولاً یعنی عکس بزرگ بوده. '
            .'حداکثر حجم مجاز '.UploadLimits::humanMax().' است؛ عکس را با کیفیت کمتر بگیرید و دوباره تلاش کنید.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['device_img1' => [$message]],
            ], 413);
        }

        return back()->withInput()->withErrors(['device_img1' => $message]);
    }
}
