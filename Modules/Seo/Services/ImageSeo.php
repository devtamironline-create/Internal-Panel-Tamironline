<?php

namespace Modules\Seo\Services;

/**
 * تولید خودکار alt/title تصویر از روی نام فایل یا قالبِ متغیردار.
 * pure و واحد-تست‌پذیر.
 */
class ImageSeo
{
    public function __construct(private readonly VariableRenderer $variables) {}

    /**
     * نام تمیزِ خوانا از روی نام فایل (حذف پسوند، تبدیل -/_ به فاصله).
     */
    public function humanizeFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/[\-_]+/u', ' ', urldecode($base));
        // حذف هش/ابعاد رایج انتهای نام (مثل image-1024x768 یا -scaled)
        $base = preg_replace('/\b\d{2,4}x\d{2,4}\b/u', '', (string) $base);
        $base = preg_replace('/\b(scaled|thumbnail|copy)\b/iu', '', (string) $base);

        return trim(preg_replace('/\s+/u', ' ', (string) $base));
    }

    /**
     * متن alt با اعمال قالب. قالب می‌تواند از %filename% و سایر متغیرها
     * (که در $vars پاس می‌شوند) استفاده کند.
     *
     * @param  array<string, string|null>  $vars
     */
    public function alt(string $filename, ?string $template = null, array $vars = []): string
    {
        $vars['filename'] = $this->humanizeFilename($filename);

        if ($template === null || trim($template) === '') {
            return $vars['filename'];
        }

        return $this->variables->render($template, $vars);
    }
}
