<?php

namespace Modules\Seo\Services;

/**
 * ساختِ مقدار متاتگ robots از مجموعه پرچم‌ها. خروجی مثل:
 *   "index, follow, max-image-preview:large"
 *   "noindex, nofollow"
 *
 * pure و واحد-تست‌پذیر.
 */
class RobotsBuilder
{
    /**
     * @param  array<string, mixed>  $flags
     * @return list<string>  دایرکتیوها به‌صورت آرایه (برای خروجی ساخت‌یافتهٔ API)
     */
    public function directives(array $flags): array
    {
        $out = [];

        $out[] = ! empty($flags['noindex']) ? 'noindex' : 'index';
        $out[] = ! empty($flags['nofollow']) ? 'nofollow' : 'follow';

        foreach (['noarchive', 'noimageindex', 'nosnippet', 'notranslate'] as $flag) {
            if (! empty($flags[$flag])) {
                $out[] = str_replace('_', '-', $flag);
            }
        }

        if (isset($flags['max_snippet']) && $flags['max_snippet'] !== null && $flags['max_snippet'] !== '') {
            $out[] = 'max-snippet:'.(int) $flags['max_snippet'];
        }
        if (! empty($flags['max_image_preview'])) {
            $out[] = 'max-image-preview:'.$flags['max_image_preview'];
        }
        if (isset($flags['max_video_preview']) && $flags['max_video_preview'] !== null && $flags['max_video_preview'] !== '') {
            $out[] = 'max-video-preview:'.(int) $flags['max_video_preview'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public function build(array $flags): string
    {
        return implode(', ', $this->directives($flags));
    }
}
