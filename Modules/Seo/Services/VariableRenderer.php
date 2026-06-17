<?php

namespace Modules\Seo\Services;

/**
 * موتور رندر متغیرهای سئو (مثل Rank Math): الگوهایی مانند
 * «%title% %sep% %sitename%» را با مقادیر context جایگزین می‌کند.
 *
 * این کلاس عمداً pure و بدون وابستگی به DB است تا واحد-تست‌پذیر بماند؛
 * فراهم‌کردن context (نام سایت، جداکننده، تاریخ و …) وظیفهٔ caller است.
 */
class VariableRenderer
{
    /**
     * @param  array<string, string|null>  $vars  کلیدها بدون % (مثل 'title' => 'یخچال')
     */
    public function render(?string $template, array $vars): string
    {
        if ($template === null || trim($template) === '') {
            return '';
        }

        $separator = (string) ($vars['sep'] ?? '');

        // ۱) جایگزینی توکن‌های شناخته‌شده (حساس‌نبودن به بزرگی/کوچکی حروف)
        $rendered = preg_replace_callback('/%([a-zA-Z0-9_\-]+)%/', function ($m) use ($vars) {
            $key = strtolower($m[1]);

            return array_key_exists($key, $vars) ? (string) ($vars[$key] ?? '') : "\0UNKNOWN\0";
        }, $template);

        // ۲) حذف توکن‌های ناشناخته (مثل Rank Math که placeholderِ بی‌مقدار را پاک می‌کند)
        $rendered = str_replace("\0UNKNOWN\0", '', $rendered);

        // ۳) پاک‌سازی فاصله‌ها و جداکننده‌های اضافه (وقتی یک متغیر خالی بوده)
        return $this->tidy($rendered, $separator);
    }

    /**
     * جمع‌وجور کردن خروجی: فشرده‌سازی فاصله‌ها و حذف جداکننده‌های آویزان/تکراری.
     */
    private function tidy(string $text, string $separator): string
    {
        // فشرده‌سازی فاصله‌های پیاپی
        $text = preg_replace('/[ \t]+/u', ' ', $text);

        if ($separator !== '') {
            $sep = preg_quote($separator, '/');
            // جداکننده‌های پشت‌سرهم → یکی
            $text = preg_replace('/(?:\s*'.$sep.'\s*){2,}/u', ' '.$separator.' ', $text);
            // جداکنندهٔ ابتدا/انتها
            $text = preg_replace('/^\s*'.$sep.'\s*/u', '', $text);
            $text = preg_replace('/\s*'.$sep.'\s*$/u', '', $text);
        }

        return trim($text);
    }
}
