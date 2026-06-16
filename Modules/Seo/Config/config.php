<?php

/**
 * پیکربندی ماژول سئو.
 *
 * این فایل «منبع حقیقت» برای الگوهای URL، متغیرهای پشتیبانی‌شده و
 * قالب‌های پیش‌فرضِ عنوان/توضیح به‌ازای هر نوع‌محتواست. ساختار URL سایت
 * (مثلاً تغییر به /services/{device}/{brand}) فقط از همین‌جا کنترل می‌شود
 * تا تغییر مسیرها نیازی به دست‌زدن به کد نداشته باشد.
 */
return [

    // جداکنندهٔ پیش‌فرض عنوان (مثل Rank Math %sep%). در تنظیمات سراسری
    // قابل override است (کلید seo_settings: separator).
    'separator' => '–',

    /*
    |--------------------------------------------------------------------------
    | رجیستری نوع‌محتوا (Seoable types)
    |--------------------------------------------------------------------------
    | کلید = شناسهٔ نوع که فرانت در ?type= می‌فرستد.
    |   model        : کلاس مدل
    |   slug         : ستون slug برای واکشی با ?slug=
    |   url          : الگوی مسیر عمومی روی فرانت Next.js (برای canonical/sitemap)
    |   title_attr   : ستونی که %title% از آن خوانده می‌شود
    |   excerpt_attr : ستونی که %excerpt% از آن خوانده می‌شود (اختیاری)
    |   published    : ستون تاریخ انتشار برای تشخیص index/noindex (اختیاری)
    |   sitemap      : آیا در sitemap بیاید (فاز ۲)
    |   default_schema: نوع schema پیش‌فرض (فاز ۳)
    */
    'types' => [
        'page' => [
            'model' => \Modules\Site\Models\Page::class,
            'slug' => 'slug',
            'url' => '/{slug}',
            'title_attr' => 'title',
            'excerpt_attr' => null,
            'published' => 'published_at',
            'sitemap' => true,
            'default_schema' => 'WebPage',
        ],
        'article' => [
            'model' => \Modules\Site\Models\Article::class,
            'slug' => 'slug',
            'url' => '/blog/{slug}',
            'title_attr' => 'title',
            'excerpt_attr' => 'excerpt',
            'published' => 'published_at',
            'sitemap' => true,
            'default_schema' => 'BlogPosting',
        ],
        'blog_topic' => [
            'model' => \Modules\Site\Models\BlogTopic::class,
            'slug' => 'slug',
            'url' => '/blog/topic/{slug}',
            'title_attr' => 'name',
            'excerpt_attr' => null,
            'published' => null,
            'sitemap' => true,
            'default_schema' => 'CollectionPage',
        ],
        'brand' => [
            'model' => \Modules\CRM\Models\Brand::class,
            'slug' => 'slug',
            'url' => '/services/brands/{slug}',
            'title_attr' => 'name',
            'excerpt_attr' => null,
            'published' => null,
            'sitemap' => true,
            'default_schema' => 'WebPage',
        ],
        'device' => [
            'model' => \Modules\CRM\Models\Device::class,
            'slug' => 'slug',
            'url' => '/services/{slug}',
            'title_attr' => 'name',
            'excerpt_attr' => null,
            'published' => null,
            'sitemap' => true,
            'default_schema' => 'WebPage',
        ],
        'taxonomy' => [
            'model' => \Modules\Site\Models\Taxonomy::class,
            'slug' => 'slug',
            'url' => '/faq/{slug}',
            'title_attr' => 'name',
            'excerpt_attr' => null,
            'published' => null,
            'sitemap' => true,
            'default_schema' => 'CollectionPage',
        ],
        'forum_question' => [
            'model' => \Modules\Site\Models\Forum\Question::class,
            'slug' => 'slug',
            'url' => '/forum/{slug}',
            'title_attr' => 'title',
            'excerpt_attr' => null,
            'published' => 'published_at',
            'sitemap' => true,
            'default_schema' => 'QAPage',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | قالب‌های پیش‌فرضِ عنوان/توضیح به‌ازای هر نوع
    |--------------------------------------------------------------------------
    | سطح‌بندی resolver: override آیتم ← این قالب نوع ← قالب سراسری.
    | از متغیرهای %...% استفاده می‌کنند.
    */
    'templates' => [
        'global' => [
            'title' => '%title% %sep% %sitename%',
            'description' => '%excerpt%',
        ],
        'page' => [
            'title' => '%title% %sep% %sitename%',
            'description' => '%excerpt%',
        ],
        'article' => [
            'title' => '%title% %sep% %sitename%',
            'description' => '%excerpt%',
        ],
        'brand' => [
            'title' => 'خدمات و تعمیر %title% %sep% %sitename%',
            'description' => 'نمایندگی و خدمات پس از فروش %title% — رزرو آنلاین تعمیرکار.',
        ],
        'device' => [
            'title' => 'تعمیر %title% %sep% %sitename%',
            'description' => 'تعمیر تخصصی %title% در محل با گارانتی — رزرو آنلاین.',
        ],
        'forum_question' => [
            'title' => '%title% %sep% %sitename%',
            'description' => '%excerpt%',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | متغیرهای پشتیبانی‌شده (برای راهنمای پنل و رندر)
    |--------------------------------------------------------------------------
    | کلید بدون % است؛ value توضیح فارسیِ نمایش در پنل.
    */
    'variables' => [
        'title' => 'عنوان آیتم',
        'sitename' => 'نام سایت',
        'sitedesc' => 'توضیح سایت',
        'sep' => 'جداکننده',
        'excerpt' => 'خلاصه/چکیده',
        'term' => 'نام ترم/برچسب',
        'category' => 'دستهٔ اصلی',
        'date' => 'تاریخ انتشار',
        'modified' => 'تاریخ به‌روزرسانی',
        'page' => 'شمارهٔ صفحه (صفحه‌بندی)',
        'currentyear' => 'سال جاری',
        'id' => 'شناسهٔ آیتم',
    ],

    // پیش‌فرض‌های sitemap (در سطح نوع با کلیدهای priority/changefreq قابل override).
    'sitemap' => [
        'priority' => 0.7,
        'changefreq' => 'weekly',
    ],

    // robots پیش‌فرض سراسری (وقتی آیتم و نوع چیزی تعیین نکرده‌اند).
    'robots_default' => [
        'noindex' => false,
        'nofollow' => false,
        'noarchive' => false,
        'noimageindex' => false,
        'nosnippet' => false,
        'notranslate' => false,
        'max_snippet' => null,
        'max_image_preview' => 'large',
        'max_video_preview' => null,
    ],
];
