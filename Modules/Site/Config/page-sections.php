<?php

/*
|--------------------------------------------------------------------------
| Page Sections Schema — منبع حقیقت برای محتوای صفحات سایت
|--------------------------------------------------------------------------
|
| این فایل ساختار سکشن‌های هر صفحه‌ی فرانت را تعریف می‌کند. هر تغییر در
| این schema → فرم ادمین و قرارداد API به‌صورت خودکار به‌روز می‌شوند.
|
| نوع فیلدها:
|   - string           → text input تک‌خطی
|   - textarea         → text چندخطی
|   - url              → URL (با validation)
|   - image_url        → URL تصویر تک (با پیش‌نمایش)  ⚠️ منسوخ — از responsive_image استفاده شود
|   - responsive_image → دو URL: desktop و mobile با پیش‌نمایش
|   - int              → عدد صحیح
|   - bool             → checkbox
|   - select           → dropdown (به همراه options)
|   - repeater         → آرایه‌ای از آیتم‌ها با item_fields
|   - reference        → انتخاب از یک مخزن (faqs | testimonials | brands | devices)
|
| قواعد Laravel در کلید `rules` پشتیبانی می‌شوند.
|
*/

return [

    // ─── صفحه‌ی خدمات (لیست دسته‌بندی دستگاه‌ها + برندها) ──────────────
    'services' => [
        'title' => 'صفحه‌ی خدمات (/services)',
        'sections' => [

            'hero' => [
                'label' => 'Hero صفحه‌ی خدمات',
                'description' => 'تیتر و زیرتیتر بالای صفحه. لیست دسته‌بندی‌ها و برندها به‌صورت زنده از API می‌آید.',
                'fields' => [
                    'badge' => ['label' => 'Badge (بالای تیتر)', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل، هر کدام با alt مجزا)', 'type' => 'hero_visual'],
                ],
            ],

            'intro' => [
                'label' => 'متن معرفی (اختیاری)',
                'description' => 'محتوای متنی غنی که بالای لیست خدمات نمایش داده می‌شود.',
                'fields' => [
                    'html' => ['label' => 'محتوای HTML', 'type' => 'textarea', 'rules' => 'nullable|string|max:50000'],
                ],
            ],

            'categories' => [
                'label' => 'سکشن دسته‌بندی دستگاه‌ها',
                'description' => 'تیتر سکشن + انتخاب دستی دسته‌بندی‌ها. اگر هیچ دسته‌ای انتخاب نکنید، فرانت همه‌ی دسته‌های فعال را از /v1/catalog/device-categories نمایش می‌دهد.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'category_ids' => [
                        'label' => 'انتخاب/ترتیب دستی دسته‌بندی‌ها (خالی = همه)',
                        'type' => 'reference',
                        'source' => 'device_categories',
                    ],
                ],
            ],

            'brands' => [
                'label' => 'سکشن برندها',
                'description' => 'تیتر سکشن + انتخاب دستی برندها. اگر خالی بگذارید، فرانت همه‌ی برندهای فعال را از /v1/catalog/brands نمایش می‌دهد.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'brand_ids' => [
                        'label' => 'انتخاب/ترتیب دستی برندها (خالی = همه)',
                        'type' => 'reference',
                        'source' => 'brands',
                    ],
                ],
            ],

            'cta' => [
                'label' => 'بنر CTA پایین صفحه',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'button_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'button_url' => ['label' => 'لینک دکمه', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی — از مخزن بنرها',
                'description' => 'یک زون بنر برای صفحه‌ی خدمات انتخاب کنید (≈۱۲۰۰×۳۰۰).',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول صفحه‌ی خدمات',
                'description' => 'تیتر سکشن + انتخاب از بانک FAQ (دسته‌بندی یا سوال منفرد). اگر خالی بماند، فرانت می‌تواند fixture استاتیک خود را نشان دهد.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'category_ids' => [
                        'label' => 'دسته‌بندی FAQ',
                        'type' => 'reference',
                        'source' => 'faq_categories',
                    ],
                    'faq_ids' => [
                        'label' => 'سوالات منفرد',
                        'type' => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

            'testimonials' => [
                'label' => 'نظرات مشتریان صفحه‌ی خدمات',
                'description' => 'تیتر سکشن + انتخاب توصیه‌نامه‌های صوتی از بانک نظرات. اگر خالی بماند، فرانت fixture استاتیک خود را نشان می‌دهد.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'testimonial_ids' => [
                        'label' => 'انتخاب از بانک نظرات',
                        'type' => 'reference',
                        'source' => 'testimonials',
                    ],
                ],
            ],

            'seo' => [
                'label' => 'سئو',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی انجمن (/forum) ──────────────────────────────────────
    'forum' => [
        'title' => 'صفحه‌ی انجمن (/forum)',
        'sections' => [

            'hero' => [
                'label' => 'Hero بالای صفحه',
                'fields' => [
                    'badge' => ['label' => 'Badge', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'highlight' => ['label' => 'بخش گرادیانت تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'popular_searches' => [
                        'label' => 'جستجوهای محبوب (هر آیتم یک pill)',
                        'type' => 'repeater',
                        'item_fields' => [
                            'text' => ['label' => 'متن', 'type' => 'string', 'rules' => 'required|string|max:100'],
                            'url' => ['label' => 'لینک (اختیاری)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                        ],
                    ],
                ],
            ],

            'sections_visibility' => [
                'label' => 'فعال/غیرفعال‌سازی سکشن‌ها',
                'fields' => [
                    'show_device_picker' => ['label' => 'DevicePicker', 'type' => 'bool'],
                    'show_questions_list' => ['label' => 'لیست سوالات', 'type' => 'bool'],
                    'show_hot_problems' => ['label' => 'داغ‌ترین مشکلات', 'type' => 'bool'],
                    'show_app_promo' => ['label' => 'بنر اپ', 'type' => 'bool'],
                    'show_top_experts' => ['label' => 'کارشناسان برتر', 'type' => 'bool'],
                    'show_categories' => ['label' => 'دسته‌بندی خودکار (دستگاه/برند/ترکیبی)', 'type' => 'bool'],
                    'show_expert_answers' => ['label' => 'پاسخ‌های کارشناسی', 'type' => 'bool'],
                    'show_final_cta' => ['label' => 'CTA پایانی', 'type' => 'bool'],
                ],
            ],

            'experts_section' => [
                'label' => 'تیتر سکشن کارشناسان',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                ],
            ],

            'expert_answers_section' => [
                'label' => 'تیتر سکشن پاسخ‌های کارشناسی',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                ],
            ],

            'categories' => [
                'label' => 'دسته‌بندی خودکار (دستگاه‌ها / برندها / ترکیبی محبوب)',
                'description' => 'این سکشن خودکار از CRM می‌خواند — لیست دستگاه‌های فعال، برندهای فعال، و ترکیبی‌های محبوب (top N با بیشترین سوال انجمن). تنها تنظیمات این سکشن، تیتر و فعال/غیرفعال‌بودن هر گرید است.',
                'fields' => [
                    'show_devices' => ['label' => 'نمایش گرید دستگاه‌ها', 'type' => 'bool'],
                    'devices_title' => ['label' => 'تیتر گرید دستگاه‌ها', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'show_brands' => ['label' => 'نمایش گرید برندها', 'type' => 'bool'],
                    'brands_title' => ['label' => 'تیتر گرید برندها', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'show_combos' => ['label' => 'نمایش گرید ترکیبی محبوب', 'type' => 'bool'],
                    'combos_title' => ['label' => 'تیتر گرید ترکیبی', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'combos_limit' => ['label' => 'تعداد آیتم‌های ترکیبی (top N)', 'type' => 'int', 'rules' => 'nullable|integer|min:1|max:30'],
                ],
            ],

            'final_cta' => [
                'label' => 'CTA پایانی',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'primary' => [
                        'label' => 'دکمه‌ی اصلی',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                        ],
                    ],
                    'secondary' => [
                        'label' => 'دکمه‌ی دوم',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                        ],
                    ],
                ],
            ],

            'sidebar_banners' => [
                'label' => 'بنرهای ساید‌بار صفحه‌ی جزئیات',
                'fields' => [
                    'items' => [
                        'label' => 'بنرها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'id' => ['label' => 'id', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'category' => ['label' => 'category', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:160'],
                            'description' => ['label' => 'توضیح', 'type' => 'string', 'rules' => 'nullable|string|max:300'],
                            'cta_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'cta_url' => ['label' => 'لینک دکمه', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'tone' => ['label' => 'tone', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                        ],
                    ],
                ],
            ],

            'seo' => [
                'label' => 'سئو',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی بلاگ (/blog) ────────────────────────────────────────
    'blog' => [
        'title' => 'صفحه‌ی بلاگ (/blog)',
        'sections' => [

            'hero' => [
                'label' => 'Hero بالای صفحه‌ی بلاگ',
                'fields' => [
                    'badge' => ['label' => 'Badge (بالای تیتر)', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'highlight' => ['label' => 'بخش گرادیانت تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

            'search' => [
                'label' => 'سرچ مقالات',
                'fields' => [
                    'placeholder' => ['label' => 'متن placeholder سرچ', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'button_label' => ['label' => 'متن دکمه‌ی سرچ', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                ],
            ],

            'banner' => [
                'label' => 'بنر بین سرچ و لیست مقالات — از مخزن بنرها',
                'description' => 'یک زون بنر انتخاب کنید. مدیریت تصاویر و زمان‌بندی در پنل بنرها (≈۱۲۰۰×۳۰۰).',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            'sections_visibility' => [
                'label' => 'فعال/غیرفعال‌سازی سکشن‌ها',
                'fields' => [
                    'show_topics_marquee' => ['label' => 'نمایش marquee تاپیک‌ها', 'type' => 'bool'],
                    'show_search' => ['label' => 'نمایش سرچ', 'type' => 'bool'],
                    'show_banner' => ['label' => 'نمایش بنر', 'type' => 'bool'],
                    'show_categories' => ['label' => 'نمایش دسته‌بندی دستگاه‌ها', 'type' => 'bool'],
                    'show_brands' => ['label' => 'نمایش برندها', 'type' => 'bool'],
                ],
            ],

            'categories_section' => [
                'label' => 'تیتر سکشن دسته‌بندی دستگاه‌ها',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                ],
            ],

            'brands_section' => [
                'label' => 'تیتر سکشن برندها',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                ],
            ],

            'articles_section' => [
                'label' => 'تیتر سکشن مقالات',
                'fields' => [
                    'title' => ['label' => 'تیتر (مثل «جدیدترین مقالات»)', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'page_size' => ['label' => 'تعداد در هر صفحه (pagination)', 'type' => 'int', 'rules' => 'nullable|integer|min:3|max:50'],
                ],
            ],

            'seo' => [
                'label' => 'سئو',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی اصلی ────────────────────────────────────────────────
    'home' => [
        'title' => 'صفحه‌ی اصلی',
        'sections' => [

            'hero' => [
                'label' => 'سکشن Hero (H1)',
                'description' => 'تیتر اصلی، زیرتیتر و لیست خدمات اصلی (دستگاه‌ها).',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل، هر کدام با alt مجزا)', 'type' => 'hero_visual'],
                    'cta_label' => ['label' => 'متن دکمه CTA', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'cta_url' => ['label' => 'لینک دکمه (مسیر داخلی /order یا URL کامل)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                    'services' => [
                        'label' => 'لیست خدمات اصلی (انتخاب از دستگاه‌های CRM)',
                        'type' => 'reference',
                        'source' => 'devices',
                    ],
                ],
            ],

            'why_us' => [
                'label' => 'چرا ما (H3)',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items' => [
                        'label' => 'آیتم‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'icon' => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:300'],
                        ],
                    ],
                ],
            ],

            'steps' => [
                'label' => 'تصویر مراحل (H4)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'image' => ['label' => 'تصویر (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'alt' => ['label' => 'متن جایگزین (alt)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی (H7) — از مخزن بنرها',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول (H8)',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'category_ids' => [
                        'label' => 'دسته‌بندی‌ها (در فرانت به‌صورت تب نمایش داده می‌شوند)',
                        'type' => 'reference',
                        'source' => 'faq_categories',
                    ],
                    'faq_ids' => [
                        'label' => 'یا سوالات منفرد از مخزن',
                        'type' => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی درباره ما ──────────────────────────────────────────
    'about' => [
        'title' => 'صفحه‌ی درباره ما',
        'sections' => [

            'hero' => [
                'label' => 'About Hero (A1)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'aparat_id' => ['label' => 'Aparat ID', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'poster' => ['label' => 'تصاویر poster (۲ تصویر دسکتاپ + ۱ تصویر موبایل، هر کدام با alt مجزا)', 'type' => 'hero_visual'],
                    'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea', 'rules' => 'nullable|string|max:1000'],
                    'highlights' => [
                        'label' => 'لیست بولت‌های Hero (تیک‌دار)',
                        'type' => 'repeater',
                        'item_fields' => [
                            'icon' => ['label' => 'کلید آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'text' => ['label' => 'متن', 'type' => 'string', 'rules' => 'required|string|max:200'],
                        ],
                    ],
                ],
            ],

            'stats' => [
                'label' => 'آمار About (A2)',
                'description' => 'هدر سکشن + آرایه‌ی آمار. منبع داده اصلی این بخش — جایگزین /v1/site/about-stats.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items' => [
                        'label' => 'آمار',
                        'type' => 'repeater',
                        'item_fields' => [
                            'key' => ['label' => 'کلید پایدار (snake_case)', 'type' => 'string', 'rules' => 'required|string|max:60|regex:/^[a-z][a-z0-9_]*$/'],
                            'value' => ['label' => 'مقدار نمایشی', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'label' => ['label' => 'برچسب فارسی', 'type' => 'string', 'rules' => 'required|string|max:120'],
                            'tone' => [
                                'label' => 'تم رنگ',
                                'type' => 'select',
                                'options' => [
                                    'blue' => 'آبی',
                                    'green' => 'سبز',
                                    'amber' => 'کهربایی',
                                    'rose' => 'صورتی',
                                    'violet' => 'بنفش',
                                ],
                                'rules' => 'required|in:blue,green,amber,rose,violet',
                            ],
                        ],
                    ],
                ],
            ],

            'values' => [
                'label' => 'ارزش‌های ما (A3)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items' => [
                        'label' => 'ارزش‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'icon' => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:400'],
                        ],
                    ],
                ],
            ],

            'steps' => [
                'label' => 'تصویر مراحل (A4)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'image' => ['label' => 'تصویر (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'alt' => ['label' => 'متن جایگزین', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            'timeline' => [
                'label' => 'سال‌شمار (A5)',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'items' => [
                        'label' => 'موارد',
                        'type' => 'repeater',
                        'item_fields' => [
                            'year' => ['label' => 'سال', 'type' => 'string', 'rules' => 'required|string|max:20'],
                            'title' => ['label' => 'تیتر رویداد', 'type' => 'string', 'rules' => 'required|string|max:100'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'nullable|string|max:400'],
                        ],
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول About (A7)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'category_ids' => [
                        'label' => 'دسته‌بندی‌ها (تب)',
                        'type' => 'reference',
                        'source' => 'faq_categories',
                    ],
                    'faq_ids' => [
                        'label' => 'یا سوالات منفرد',
                        'type' => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی (A8) — از مخزن بنرها',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

        ],
    ],

    // ─── هدر و فوتر سایت (Layout مشترک) ──────────────────────────────
    'layout' => [
        'title' => 'هدر و فوتر',
        'sections' => [

            'header' => [
                'label' => 'هدر سایت',
                'description' => 'لوگو، منو ناوبری، دکمه CTA و dropdown خدمات.',
                'fields' => [
                    'logo' => ['label' => 'لوگو (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'logo_alt' => ['label' => 'متن جایگزین لوگو', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'cta_label' => ['label' => 'متن دکمه CTA', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'cta_url' => ['label' => 'لینک دکمه CTA (مسیر داخلی یا URL کامل)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                    'phone_label' => ['label' => 'متن شماره تماس', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'phone_number' => ['label' => 'شماره تماس', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'nav_items' => [
                        'label' => 'آیتم‌های منوی ناوبری',
                        'type' => 'repeater',
                        'item_fields' => [
                            'label' => ['label' => 'برچسب', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'href' => ['label' => 'لینک (مسیر داخلی یا URL کامل)', 'type' => 'string', 'rules' => 'required|site_url|max:200'],
                        ],
                    ],
                    'services_dropdown' => [
                        'label' => 'منوی Dropdown خدمات',
                        'description' => 'مگامنوی خدمات که با hover روی «خدمات» در هدر باز می‌شود.',
                        'type' => 'group',
                        'fields' => [
                            'trigger_label' => ['label' => 'برچسب trigger در هدر', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title' => ['label' => 'تیتر داخل dropdown', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                            'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                            'view_all_label' => ['label' => 'متن لینک «همه دستگاه‌ها»', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'view_all_url' => ['label' => 'لینک «همه دستگاه‌ها»', 'type' => 'string', 'rules' => 'nullable|site_url|max:200'],
                            'device_ids' => [
                                'label' => 'دستگاه‌های نمایش‌داده‌شده در dropdown',
                                'type' => 'reference',
                                'source' => 'devices',
                            ],
                        ],
                    ],
                ],
            ],

            'footer' => [
                'label' => 'فوتر سایت',
                'description' => 'لوگو، توضیح، گروه‌های لینک، اطلاعات تماس، دانلود اپ، شبکه‌های اجتماعی و حقوق ناشر.',
                'fields' => [
                    'logo' => ['label' => 'لوگوی فوتر (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'groups' => [
                        'label' => 'گروه‌های لینک',
                        'type' => 'repeater',
                        'item_fields' => [
                            'title' => ['label' => 'تیتر گروه', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'links' => ['label' => 'لینک‌ها (label|href جدا با کاما)', 'type' => 'textarea', 'rules' => 'nullable|string|max:2000'],
                        ],
                    ],
                    'contact_info' => [
                        'label' => 'اطلاعات تماس (در فوتر)',
                        'description' => 'بلوک اطلاعات تماس که در ستون فوتر نمایش داده می‌شود.',
                        'type' => 'group',
                        'fields' => [
                            'title' => ['label' => 'تیتر بلوک', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'address' => ['label' => 'آدرس', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                            'phone' => ['label' => 'شماره تماس (تلیبل tel:)', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                            'phone_display' => ['label' => 'شماره تماس نمایشی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                            'email' => ['label' => 'ایمیل', 'type' => 'string', 'rules' => 'nullable|email|max:120'],
                        ],
                    ],
                    'app_download' => [
                        'label' => 'بلوک دانلود اپلیکیشن',
                        'description' => 'تیتر و لینک‌های دانلود اپ (Google Play، Cafe Bazaar، App Store، Sib Apple، ...).',
                        'type' => 'group',
                        'fields' => [
                            'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                            'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                            'image' => ['label' => 'تصویر اپ (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                            'stores' => [
                                'label' => 'فروشگاه‌ها (لینک دانلود)',
                                'type' => 'repeater',
                                'item_fields' => [
                                    'name' => ['label' => 'نام فروشگاه', 'type' => 'string', 'rules' => 'required|string|max:60'],
                                    'icon' => ['label' => 'کلید آیکن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                                    'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'required|site_url|max:500'],
                                    'image' => ['label' => 'تصویر badge', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                                ],
                            ],
                        ],
                    ],
                    'social' => [
                        'label' => 'شبکه‌های اجتماعی',
                        'type' => 'repeater',
                        'item_fields' => [
                            'platform' => ['label' => 'پلتفرم', 'type' => 'string', 'rules' => 'required|string|max:30'],
                            'icon' => ['label' => 'آیکن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'url', 'rules' => 'required|url|max:500'],
                        ],
                    ],
                    'copyright_text' => ['label' => 'متن حقوق', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'enamad_code' => ['label' => 'کد HTML اعتماد الکترونیکی', 'type' => 'textarea', 'rules' => 'nullable|string|max:2000'],
                ],
            ],

            'service_features' => [
                'label' => 'ویژگی‌های ما (نوار افقی)',
                'description' => 'نوار ثابت ویژگی‌ها که در همه‌ی صفحات سایت تکرار می‌شود (Feature Marquee).',
                'fields' => [
                    'aria_label' => ['label' => 'متن aria-label', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'speed' => ['label' => 'سرعت اسکرول (پیش‌فرض ۸)', 'type' => 'int', 'rules' => 'nullable|integer|min:1|max:60'],
                    'items' => [
                        'label' => 'ویژگی‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'icon_key' => ['label' => 'کلید آیکن Lucide', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'label' => ['label' => 'متن', 'type' => 'string', 'rules' => 'required|string|max:120'],
                            'bg' => ['label' => 'پس‌زمینه (hex)', 'type' => 'string', 'rules' => 'nullable|string|max:20'],
                            'fg' => ['label' => 'متن (hex)', 'type' => 'string', 'rules' => 'nullable|string|max:20'],
                            'border' => ['label' => 'حاشیه (hex)', 'type' => 'string', 'rules' => 'nullable|string|max:20'],
                        ],
                    ],
                ],
            ],

            'seo_footer' => [
                'label' => 'متن SEO پایین (Read More)',
                'description' => 'بلوک متن سئوی پایین صفحات (کنار فوتر) — قابل expand/collapse در فرانت.',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'expand_label' => ['label' => 'متن دکمه نمایش بیشتر', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'collapse_label' => ['label' => 'متن دکمه بستن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'paragraphs' => [
                        'label' => 'پاراگراف‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'text' => ['label' => 'متن پاراگراف', 'type' => 'textarea', 'rules' => 'required|string|max:3000'],
                        ],
                    ],
                ],
            ],

            'mobile_cta' => [
                'label' => 'نوار CTA موبایل (Sticky Bottom)',
                'description' => 'نوار چسبیده به پایین صفحه فقط در نمایش موبایل — معمولاً شامل دکمه‌ی تماس و دکمه‌ی ثبت سفارش.',
                'fields' => [
                    'is_active' => ['label' => 'فعال', 'type' => 'bool'],
                    'primary' => [
                        'label' => 'دکمه اصلی',
                        'description' => 'معمولاً تماس تلفنی.',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'برچسب', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'icon' => ['label' => 'کلید آیکن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'type' => [
                                'label' => 'نوع',
                                'type' => 'select',
                                'options' => ['tel' => 'tel (تماس)', 'link' => 'link (مسیر)', 'mailto' => 'mailto (ایمیل)'],
                                'rules' => 'nullable|in:tel,link,mailto',
                            ],
                            'value' => ['label' => 'مقدار (شماره تلفن یا مسیر)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                        ],
                    ],
                    'secondary' => [
                        'label' => 'دکمه دوم',
                        'description' => 'معمولاً ثبت سفارش یا چت آنلاین.',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'برچسب', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'icon' => ['label' => 'کلید آیکن', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'type' => [
                                'label' => 'نوع',
                                'type' => 'select',
                                'options' => ['tel' => 'tel (تماس)', 'link' => 'link (مسیر)', 'mailto' => 'mailto (ایمیل)'],
                                'rules' => 'nullable|in:tel,link,mailto',
                            ],
                            'value' => ['label' => 'مقدار (شماره تلفن یا مسیر)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                        ],
                    ],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی تماس با ما ──────────────────────────────────────────
    'contact' => [
        'title' => 'صفحه‌ی تماس با ما',
        'sections' => [

            'hero' => [
                'label' => 'Hero صفحه‌ی تماس',
                'description' => 'تیتر، زیرتیتر و تصویر بالای صفحه (دسکتاپ + موبایل با alt مجزا).',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل، هر کدام با alt مجزا)', 'type' => 'hero_visual'],
                ],
            ],

            'channels' => [
                'label' => 'کارت‌های راه ارتباطی (C1)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'items' => [
                        'label' => 'کارت‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'icon' => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title' => ['label' => 'عنوان', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'value' => ['label' => 'مقدار', 'type' => 'string', 'rules' => 'required|string|max:120'],
                            'link_url' => ['label' => 'لینک (داخلی یا کامل: /order، tel:، mailto:، https://...)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'description' => ['label' => 'توضیح', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                        ],
                    ],
                ],
            ],

            'info' => [
                'label' => 'اطلاعات تماس (C3)',
                'fields' => [
                    'phone' => ['label' => 'تلفن', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'support_phone' => ['label' => 'پشتیبانی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'email' => ['label' => 'ایمیل', 'type' => 'string', 'rules' => 'nullable|email|max:120'],
                    'address' => ['label' => 'آدرس', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

            'hours' => [
                'label' => 'ساعات کاری (C4)',
                'fields' => [
                    'note' => ['label' => 'یادداشت', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'items' => [
                        'label' => 'ساعات روزانه',
                        'type' => 'repeater',
                        'item_fields' => [
                            'day' => ['label' => 'روز', 'type' => 'string', 'rules' => 'required|string|max:30'],
                            'hours' => ['label' => 'ساعت', 'type' => 'string', 'rules' => 'required|string|max:50'],
                        ],
                    ],
                ],
            ],

            'map' => [
                'label' => 'نقشه (C5)',
                'fields' => [
                    'lat' => ['label' => 'عرض جغرافیایی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'lng' => ['label' => 'طول جغرافیایی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'neshan_url' => ['label' => 'لینک نشان', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                    'zoom' => ['label' => 'بزرگ‌نمایی پیش‌فرض', 'type' => 'int', 'rules' => 'nullable|integer|min:1|max:20'],
                ],
            ],

            'social' => [
                'label' => 'شبکه‌های اجتماعی (C6)',
                'fields' => [
                    'items' => [
                        'label' => 'لینک‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'platform' => ['label' => 'پلتفرم', 'type' => 'string', 'rules' => 'required|string|max:30'],
                            'label' => ['label' => 'نام نمایشی', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'url', 'rules' => 'required|url|max:500'],
                            'icon' => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول Contact (C7)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'category_ids' => [
                        'label' => 'دسته‌بندی‌ها (تب)',
                        'type' => 'reference',
                        'source' => 'faq_categories',
                    ],
                    'faq_ids' => [
                        'label' => 'یا سوالات منفرد',
                        'type' => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

        ],
    ],

    // ─── الگوی صفحه‌ی دستگاه (template برای همه دستگاه‌ها) ──────────
    // ادمین در /admin/site/page-content/device این متن‌های پیش‌فرض را
    // تنظیم می‌کند. در /v1/catalog/devices/{slug} هر فیلد per-device
    // (در crm_devices) اگر null باشد، از این template با placeholder
    // substituted استفاده می‌شود.
    //
    // Placeholderها در متن‌ها: {device}, {device_label}, {device_slug}
    'device' => [
        'title' => 'الگوی سراسری صفحه دستگاه (پیش‌فرض همه دستگاه‌ها)',
        'sections' => [

            // ─── 1. Hero ──────────────────────────────────────────────
            'hero' => [
                'label' => 'Hero (پیش‌فرض)',
                'description' => 'فیلدهای بالای صفحه: badge/تیتر/زیرتیتر/کپشن. از {device} برای نام دستگاه استفاده کنید.',
                'fields' => [
                    'badge' => ['label' => 'Badge (بالای تیتر)', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'title' => ['label' => 'تیتر (با {device})', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'caption' => ['label' => 'کپشن (متن کوتاه زیر زیرتیتر)', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل با alt مجزا — {device} پشتیبانی می‌شود)', 'type' => 'hero_visual'],
                    'cta_primary' => [
                        'label' => 'دکمه‌ی ثبت سفارش',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                    'cta_secondary' => [
                        'label' => 'دکمه‌ی تماس فوری',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک (tel: یا URL)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            // ─── 2. Steps (مراحل دریافت خدمات) — global by default ──
            'steps' => [
                'label' => 'مراحل دریافت خدمات (تصویر)',
                'description' => 'دو تصویر برای دسکتاپ و موبایل. در هر دستگاه می‌توانید این بخش را غیرفعال کنید یا تصاویر را override کنید.',
                'fields' => [
                    'image' => ['label' => 'تصویر مراحل (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'alt' => ['label' => 'متن جایگزین (alt)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            // ─── 4. Content (محتوای کامل HTML) ──────────────────────
            'content' => [
                'label' => 'محتوای متنی کامل (پیش‌فرض)',
                'description' => 'محتوای detail دستگاه — می‌توانید از قالب‌بندی HTML استفاده کنید. placeholder {device} پشتیبانی می‌شود.',
                'fields' => [
                    'html' => ['label' => 'محتوای HTML', 'type' => 'textarea', 'rules' => 'nullable|string|max:200000'],
                ],
            ],

            // ─── 5. FAQ — از بانک FAQ ───────────────────────────────
            'faq' => [
                'label' => 'سوالات متداول (پیش‌فرض)',
                'description' => 'از مخزن FAQ انتخاب کنید (دسته‌بندی یا منفرد). placeholderها در متن سوال/پاسخ خودکار جایگزین می‌شوند.',
                'fields' => [
                    'category_ids' => [
                        'label' => 'دسته‌بندی FAQ',
                        'type' => 'reference',
                        'source' => 'faq_categories',
                    ],
                    'faq_ids' => [
                        'label' => 'سوالات منفرد',
                        'type' => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

            // ─── 7. Testimonials (پیش‌فرض از بانک reviews) ──────────
            'testimonials' => [
                'label' => 'نظرات مشتریان (پیش‌فرض)',
                'description' => 'reviewهای انتخابی برای نمایش به‌صورت پیش‌فرض در همه‌ی صفحات دستگاه. در هر دستگاه می‌توانید انتخاب اختصاصی override کنید.',
                'fields' => [
                    'testimonial_ids' => [
                        'label' => 'انتخاب از بانک نظرات',
                        'type' => 'reference',
                        'source' => 'testimonials',
                    ],
                ],
            ],

            // ─── 7b. Videos (پیش‌فرض همه‌ی صفحات دستگاه) ────────────
            'videos' => [
                'label' => 'ویدیوها (پیش‌فرض همه‌ی صفحات دستگاه)',
                'description' => 'ویدیوهای آموزشی/معرفی. هر دستگاه می‌تواند لیست اختصاصی خود را در فرم ویرایش دستگاه ست کند تا این پیش‌فرض را override کند.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items' => [
                        'label' => 'لیست ویدیوها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'title' => ['label' => 'عنوان ویدیو', 'type' => 'string', 'rules' => 'required|string|max:200'],
                            'aparat_id' => ['label' => 'Aparat ID (اولویت ۱)', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'youtube_id' => ['label' => 'YouTube ID (اولویت ۲)', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'video_url' => ['label' => 'URL مستقیم mp4 (اولویت ۳)', 'type' => 'url', 'rules' => 'nullable|site_url|max:500'],
                            'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea', 'rules' => 'nullable|string|max:600'],
                            'poster_url' => ['label' => 'تصویر cover (اختیاری) — انتخاب از مخزن مدیا', 'type' => 'image', 'rules' => 'nullable|string|max:500'],
                        ],
                    ],
                ],
            ],

            // ─── 7c. Forum Questions (۵ سوال آخر این دستگاه) ────────
            'forum_questions' => [
                'label' => 'سوالات اخیر انجمن (فقط مرتبط با این دستگاه)',
                'description' => 'تیتر و زیرتیتر سکشن. ۵ سوال آخر بر اساس device_id خودکار از /v1/forum/questions می‌آیند.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'see_all_label' => ['label' => 'متن لینک «مشاهده همه»', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                ],
            ],

            // ─── 8. Promo Banner (مشترک همه‌ی صفحات دستگاه) ────────
            'promo' => [
                'label' => 'بنر تبلیغاتی (پیش‌فرض همه‌ی صفحات دستگاه) — از مخزن بنرها',
                'description' => 'یک زون بنر انتخاب کنید (≈۱۲۰۰×۳۰۰). در همه‌ی صفحات /devices/[device] نمایش داده می‌شود.',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            // ─── SEO ────────────────────────────────────────────────
            'seo' => [
                'label' => 'سئو (پیش‌فرض)',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title (با {device})', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

    // ─── الگوی صفحه‌ی برند ──────────────────────────────────────────
    'brand' => [
        'title' => 'الگوی صفحه برند (پیش‌فرض همه برندها)',
        'sections' => [

            // ─── 1. Hero ──────────────────────────────────────────────
            'hero' => [
                'label' => 'Hero (پیش‌فرض)',
                'description' => 'فیلدهای بالای صفحه: badge/تیتر/زیرتیتر/کپشن. از {brand} برای نام برند استفاده کنید.',
                'fields' => [
                    'badge' => ['label' => 'Badge (بالای تیتر)', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'title' => ['label' => 'تیتر (با {brand})', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'caption' => ['label' => 'کپشن', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل با alt مجزا — {brand} پشتیبانی می‌شود)', 'type' => 'hero_visual'],
                    'cta_primary' => [
                        'label' => 'دکمه‌ی ثبت سفارش',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                    'cta_secondary' => [
                        'label' => 'دکمه‌ی تماس فوری',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک (tel: یا URL)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            // ─── 2. Steps (مراحل دریافت خدمات) — global by default ──
            'steps' => [
                'label' => 'مراحل دریافت خدمات (تصویر)',
                'description' => 'دو تصویر برای دسکتاپ و موبایل. در هر برند می‌توانید این بخش را غیرفعال کنید یا تصاویر را override کنید.',
                'fields' => [
                    'image' => ['label' => 'تصویر مراحل (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'alt' => ['label' => 'متن جایگزین (alt)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            // ─── 4. Content (محتوای کامل HTML) ──────────────────────
            'content' => [
                'label' => 'محتوای متنی کامل (پیش‌فرض)',
                'description' => 'محتوای detail برند — می‌توانید از قالب‌بندی HTML استفاده کنید. placeholder {brand} پشتیبانی می‌شود.',
                'fields' => [
                    'html' => ['label' => 'محتوای HTML', 'type' => 'textarea', 'rules' => 'nullable|string|max:200000'],
                ],
            ],

            // ─── 5. FAQ ─────────────────────────────────────────────
            'faq' => [
                'label' => 'سوالات متداول (پیش‌فرض)',
                'description' => 'از مخزن FAQ انتخاب کنید (دسته‌بندی یا منفرد).',
                'fields' => [
                    'category_ids' => ['label' => 'دسته‌بندی FAQ', 'type' => 'reference', 'source' => 'faq_categories'],
                    'faq_ids' => ['label' => 'سوالات منفرد', 'type' => 'reference', 'source' => 'faqs'],
                ],
            ],

            // ─── 7. Testimonials (پیش‌فرض از بانک reviews) ──────────
            'testimonials' => [
                'label' => 'نظرات مشتریان (پیش‌فرض)',
                'description' => 'reviewهای انتخابی برای نمایش به‌صورت پیش‌فرض در همه‌ی صفحات برند.',
                'fields' => [
                    'testimonial_ids' => [
                        'label' => 'انتخاب از بانک نظرات',
                        'type' => 'reference',
                        'source' => 'testimonials',
                    ],
                ],
            ],

            // ─── 7b. Videos (پیش‌فرض همه‌ی صفحات برند) ──────────────
            'videos' => [
                'label' => 'ویدیوها (پیش‌فرض همه‌ی صفحات برند)',
                'description' => 'ویدیوهای آموزشی/معرفی. هر برند می‌تواند لیست اختصاصی خود را در فرم ویرایش برند ست کند تا این پیش‌فرض را override کند.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items' => [
                        'label' => 'لیست ویدیوها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'title' => ['label' => 'عنوان ویدیو', 'type' => 'string', 'rules' => 'required|string|max:200'],
                            'aparat_id' => ['label' => 'Aparat ID (اولویت ۱)', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'youtube_id' => ['label' => 'YouTube ID (اولویت ۲)', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'video_url' => ['label' => 'URL مستقیم mp4 (اولویت ۳)', 'type' => 'url', 'rules' => 'nullable|site_url|max:500'],
                            'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea', 'rules' => 'nullable|string|max:600'],
                            'poster_url' => ['label' => 'تصویر cover (اختیاری) — انتخاب از مخزن مدیا', 'type' => 'image', 'rules' => 'nullable|string|max:500'],
                        ],
                    ],
                ],
            ],

            // ─── 7c. Forum Questions (۵ سوال آخر این برند) ──────────
            'forum_questions' => [
                'label' => 'سوالات اخیر انجمن (فقط مرتبط با این برند)',
                'description' => 'تیتر و زیرتیتر سکشن. ۵ سوال آخر بر اساس brand_id خودکار از انجمن می‌آیند.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'see_all_label' => ['label' => 'متن لینک «مشاهده همه»', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                ],
            ],

            // ─── Promo Banner (مشترک همه‌ی صفحات برند) ─────────────
            'promo' => [
                'label' => 'بنر تبلیغاتی (پیش‌فرض همه‌ی صفحات برند) — از مخزن بنرها',
                'description' => 'یک زون بنر انتخاب کنید (≈۱۲۰۰×۳۰۰). در همه‌ی صفحات /brands/[brand] نمایش داده می‌شود.',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            // ─── SEO ────────────────────────────────────────────────
            'seo' => [
                'label' => 'سئو (پیش‌فرض)',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title (با {brand})', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

    // ─── الگوی سراسری صفحه‌ی ترکیبی device × brand ─────────────────
    'device_brand' => [
        'title' => 'الگوی سراسری صفحه‌ی ترکیبی دستگاه × برند',
        'sections' => [

            'hero' => [
                'label' => 'Hero (پیش‌فرض ترکیبی)',
                'description' => 'placeholderهای {device}, {brand} پشتیبانی می‌شوند.',
                'fields' => [
                    'badge' => ['label' => 'Badge', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'title' => ['label' => 'تیتر (با {device} و {brand})', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'caption' => ['label' => 'کپشن', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'image' => ['label' => 'تصاویر Hero (۲ تصویر دسکتاپ + ۱ تصویر موبایل با alt مجزا — {device} و {brand} پشتیبانی می‌شوند)', 'type' => 'hero_visual'],
                    'cta_primary' => [
                        'label' => 'دکمه‌ی ثبت سفارش',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                    'cta_secondary' => [
                        'label' => 'دکمه‌ی تماس فوری',
                        'type' => 'group',
                        'fields' => [
                            'label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url' => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            'steps' => [
                'label' => 'مراحل دریافت خدمات (تصویر)',
                'fields' => [
                    'image' => ['label' => 'تصویر مراحل (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'alt' => ['label' => 'متن جایگزین', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            'content' => [
                'label' => 'محتوای متنی کامل (پیش‌فرض ترکیبی)',
                'fields' => [
                    'html' => ['label' => 'محتوای HTML', 'type' => 'textarea', 'rules' => 'nullable|string|max:200000'],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول (پیش‌فرض ترکیبی)',
                'fields' => [
                    'category_ids' => ['label' => 'دسته‌بندی FAQ', 'type' => 'reference', 'source' => 'faq_categories'],
                    'faq_ids' => ['label' => 'سوالات منفرد', 'type' => 'reference', 'source' => 'faqs'],
                ],
            ],

            'testimonials' => [
                'label' => 'نظرات مشتریان (پیش‌فرض ترکیبی)',
                'fields' => [
                    'testimonial_ids' => ['label' => 'انتخاب از بانک نظرات', 'type' => 'reference', 'source' => 'testimonials'],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی (پیش‌فرض همه‌ی صفحات ترکیبی) — از مخزن بنرها',
                'description' => 'یک زون بنر انتخاب کنید (≈۱۲۰۰×۳۰۰). در همه‌ی صفحات /brands/[b]/[d] و /devices/[d]/[b] نمایش داده می‌شود.',
                'fields' => [
                    'zone_slug' => [
                        'label' => 'زون بنر',
                        'type' => 'banner_zone',
                        'rules' => 'nullable|string|max:120',
                    ],
                ],
            ],

            'forum_questions' => [
                'label' => 'سوالات اخیر انجمن (فقط مرتبط با این ترکیب)',
                'description' => '۵ سوال آخر که هم device_id و هم brand_id آن‌ها مطابقت دارد. اگر چیزی نباشد، فرانت می‌تواند fallback به فقط device_id یا brand_id بزند.',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'see_all_label' => ['label' => 'متن لینک «مشاهده همه»', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                ],
            ],

            'seo' => [
                'label' => 'سئو (پیش‌فرض ترکیبی)',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title (با {device} و {brand})', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

];
