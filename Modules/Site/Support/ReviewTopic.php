<?php

namespace Modules\Site\Support;

/**
 * جایگزینیِ placeholderهای متنیِ «موضوع» (topic) یک نظر/توصیه‌نامه با مقادیرِ
 * واقعیِ دستگاه/برند، هنگام سرو در یک صفحه‌ی مشخص.
 *
 * مثال: «تعمیر {device}» روی صفحه‌ی لباسشویی → «تعمیر لباسشویی».
 * placeholderهای پشتیبانی‌شده: {device}، {device_slug}، {device_label}،
 * {brand}، {brand_slug}.
 */
final class ReviewTopic
{
    /**
     * @param  array<string, string|null>  $context  مثل ['device' => 'لباسشویی', 'device_slug' => 'washing-machine']
     */
    public static function fill(?string $topic, array $context): ?string
    {
        if ($topic === null || $topic === '' || ! str_contains($topic, '{')) {
            return $topic;
        }

        $map = [];
        foreach ($context as $key => $value) {
            $map['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($topic, $map);
    }
}
