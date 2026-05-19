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
|   - string      → text input تک‌خطی
|   - textarea    → text چندخطی
|   - url         → URL (با validation)
|   - image_url   → URL تصویر با پیش‌نمایش
|   - int         → عدد صحیح
|   - bool        → checkbox
|   - select      → dropdown (به همراه options)
|   - repeater    → آرایه‌ای از آیتم‌ها با item_fields
|   - reference   → انتخاب از یک مخزن (faqs | testimonials | brands)
|
| قواعد Laravel در کلید `rules` پشتیبانی می‌شوند.
|
*/

return [

    // ─── صفحه‌ی اصلی ────────────────────────────────────────────────
    'home' => [
        'title'    => 'صفحه‌ی اصلی',
        'sections' => [

            'hero' => [
                'label'       => 'سکشن Hero (H1)',
                'description' => 'تیتر اصلی، زیرتیتر و لیست خدمات اصلی.',
                'fields' => [
                    'title'     => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle'  => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'cta_label' => ['label' => 'متن دکمه CTA', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'cta_url'   => ['label' => 'لینک دکمه', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                    'services'  => [
                        'label' => 'لیست خدمات اصلی',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'label' => ['label' => 'برچسب', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'slug'  => ['label' => 'اسلاگ', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'icon'  => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'href'  => ['label' => 'لینک', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                        ],
                    ],
                ],
            ],

            'why_us' => [
                'label' => 'چرا ما (H3)',
                'fields' => [
                    'title'    => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items'    => [
                        'label' => 'آیتم‌ها',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'icon'        => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title'       => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:300'],
                        ],
                    ],
                ],
            ],

            'steps' => [
                'label' => 'تصویر مراحل (H4)',
                'fields' => [
                    'title'     => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'image_url' => ['label' => 'تصویر', 'type' => 'image_url', 'rules' => 'nullable|url|max:500'],
                    'alt'       => ['label' => 'متن جایگزین (alt)', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی (H7)',
                'fields' => [
                    'title'      => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle'   => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'image_url'  => ['label' => 'تصویر', 'type' => 'image_url', 'rules' => 'nullable|url|max:500'],
                    'link_url'   => ['label' => 'لینک هدف', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                    'link_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول (H8)',
                'fields' => [
                    'title'    => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'faq_ids'  => [
                        'label'  => 'سوالات انتخاب‌شده از مخزن',
                        'type'   => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی درباره ما ──────────────────────────────────────────
    'about' => [
        'title'    => 'صفحه‌ی درباره ما',
        'sections' => [

            'hero' => [
                'label' => 'About Hero (A1)',
                'fields' => [
                    'title'       => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:120'],
                    'subtitle'    => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                    'aparat_id'   => ['label' => 'Aparat ID', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                    'poster_url'  => ['label' => 'تصویر poster', 'type' => 'image_url', 'rules' => 'nullable|url|max:500'],
                    'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea', 'rules' => 'nullable|string|max:1000'],
                ],
            ],

            'values' => [
                'label' => 'ارزش‌های ما (A3)',
                'fields' => [
                    'title'    => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'items'    => [
                        'label' => 'ارزش‌ها',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'icon'        => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title'       => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:400'],
                        ],
                    ],
                ],
            ],

            'steps' => [
                'label' => 'تصویر مراحل (A4)',
                'fields' => [
                    'title'     => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'image_url' => ['label' => 'تصویر', 'type' => 'image_url', 'rules' => 'nullable|url|max:500'],
                    'alt'       => ['label' => 'متن جایگزین', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                ],
            ],

            'timeline' => [
                'label' => 'سال‌شمار (A5)',
                'fields' => [
                    'title' => ['label' => 'تیتر سکشن', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'items' => [
                        'label' => 'موارد',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'year'        => ['label' => 'سال', 'type' => 'string', 'rules' => 'required|string|max:20'],
                            'title'       => ['label' => 'تیتر رویداد', 'type' => 'string', 'rules' => 'required|string|max:100'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'nullable|string|max:400'],
                        ],
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول About (A7)',
                'fields' => [
                    'title'    => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'faq_ids'  => [
                        'label'  => 'سوالات انتخاب‌شده از مخزن',
                        'type'   => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

            'promo' => [
                'label' => 'بنر تبلیغاتی (A8)',
                'fields' => [
                    'title'      => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle'   => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'image_url'  => ['label' => 'تصویر', 'type' => 'image_url', 'rules' => 'nullable|url|max:500'],
                    'link_url'   => ['label' => 'لینک هدف', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                    'link_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                ],
            ],

        ],
    ],

    // ─── صفحه‌ی تماس با ما ──────────────────────────────────────────
    'contact' => [
        'title'    => 'صفحه‌ی تماس با ما',
        'sections' => [

            'channels' => [
                'label' => 'کارت‌های راه ارتباطی (C1)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'items' => [
                        'label' => 'کارت‌ها',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'icon'        => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'title'       => ['label' => 'عنوان', 'type' => 'string', 'rules' => 'required|string|max:80'],
                            'value'       => ['label' => 'مقدار', 'type' => 'string', 'rules' => 'required|string|max:120'],
                            'link_url'    => ['label' => 'لینک', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                            'description' => ['label' => 'توضیح', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                        ],
                    ],
                ],
            ],

            'info' => [
                'label' => 'اطلاعات تماس (C3)',
                'fields' => [
                    'phone'         => ['label' => 'تلفن', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'support_phone' => ['label' => 'پشتیبانی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'email'         => ['label' => 'ایمیل', 'type' => 'string', 'rules' => 'nullable|email|max:120'],
                    'address'       => ['label' => 'آدرس', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

            'hours' => [
                'label' => 'ساعات کاری (C4)',
                'fields' => [
                    'note'  => ['label' => 'یادداشت', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'items' => [
                        'label' => 'ساعات روزانه',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'day'   => ['label' => 'روز', 'type' => 'string', 'rules' => 'required|string|max:30'],
                            'hours' => ['label' => 'ساعت', 'type' => 'string', 'rules' => 'required|string|max:50'],
                        ],
                    ],
                ],
            ],

            'map' => [
                'label' => 'نقشه (C5)',
                'fields' => [
                    'lat'        => ['label' => 'عرض جغرافیایی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'lng'        => ['label' => 'طول جغرافیایی', 'type' => 'string', 'rules' => 'nullable|string|max:30'],
                    'neshan_url' => ['label' => 'لینک نشان', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
                    'zoom'       => ['label' => 'بزرگ‌نمایی پیش‌فرض', 'type' => 'int', 'rules' => 'nullable|integer|min:1|max:20'],
                ],
            ],

            'social' => [
                'label' => 'شبکه‌های اجتماعی (C6)',
                'fields' => [
                    'items' => [
                        'label' => 'لینک‌ها',
                        'type'  => 'repeater',
                        'item_fields' => [
                            'platform' => ['label' => 'پلتفرم', 'type' => 'string', 'rules' => 'required|string|max:30'],
                            'label'    => ['label' => 'نام نمایشی', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                            'url'      => ['label' => 'لینک', 'type' => 'url', 'rules' => 'required|url|max:500'],
                            'icon'     => ['label' => 'آیکون', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول Contact (C7)',
                'fields' => [
                    'title'    => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'faq_ids'  => [
                        'label'  => 'سوالات انتخاب‌شده از مخزن',
                        'type'   => 'reference',
                        'source' => 'faqs',
                    ],
                ],
            ],

        ],
    ],

];
