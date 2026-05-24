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
                'label' => 'بنر تبلیغاتی (H7)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'image' => ['label' => 'تصویر (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'link_url' => ['label' => 'لینک هدف (مسیر داخلی یا URL کامل)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                    'link_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
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
                    'poster' => ['label' => 'تصویر poster (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
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
                'label' => 'بنر تبلیغاتی (A8)',
                'fields' => [
                    'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'subtitle' => ['label' => 'زیرتیتر', 'type' => 'textarea', 'rules' => 'nullable|string|max:300'],
                    'image' => ['label' => 'تصویر (موبایل/دسکتاپ)', 'type' => 'responsive_image'],
                    'link_url' => ['label' => 'لینک هدف (مسیر داخلی یا URL کامل)', 'type' => 'string', 'rules' => 'nullable|site_url|max:500'],
                    'link_label' => ['label' => 'متن دکمه', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
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

            'identity' => [
                'label' => 'هویت (پیش‌فرض)',
                'fields' => [
                    'tagline' => ['label' => 'شعار', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'nullable|string|max:10000'],
                ],
            ],

            'stats' => [
                'label' => 'آمار پیش‌فرض',
                'fields' => [
                    'items' => [
                        'label' => 'آمار',
                        'type' => 'repeater',
                        'item_fields' => [
                            'value' => ['label' => 'مقدار', 'type' => 'string', 'rules' => 'required|string|max:60'],
                            'label' => ['label' => 'برچسب', 'type' => 'string', 'rules' => 'required|string|max:120'],
                        ],
                    ],
                ],
            ],

            'issues' => [
                'label' => 'مشکلات رایج (پیش‌فرض)',
                'fields' => [
                    'items' => [
                        'label' => 'آیتم‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'title' => ['label' => 'عنوان', 'type' => 'string', 'rules' => 'required|string|max:160'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:1000'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            'why_us' => [
                'label' => 'چرا ما (پیش‌فرض)',
                'fields' => [
                    'items' => [
                        'label' => 'آیتم‌ها',
                        'type' => 'repeater',
                        'item_fields' => [
                            'title' => ['label' => 'عنوان', 'type' => 'string', 'rules' => 'required|string|max:160'],
                            'description' => ['label' => 'توضیح', 'type' => 'textarea', 'rules' => 'required|string|max:1000'],
                            'icon' => ['label' => 'آیکن Lucide', 'type' => 'string', 'rules' => 'nullable|string|max:60'],
                        ],
                    ],
                ],
            ],

            'faq' => [
                'label' => 'سوالات متداول (پیش‌فرض)',
                'fields' => [
                    'category_ids' => ['label' => 'دسته‌بندی FAQ', 'type' => 'reference', 'source' => 'faq_categories'],
                    'faq_ids' => ['label' => 'سوالات منفرد', 'type' => 'reference', 'source' => 'faqs'],
                ],
            ],

            'support' => [
                'label' => 'گارانتی و پشتیبانی',
                'fields' => [
                    'warranty_text' => ['label' => 'متن گارانتی', 'type' => 'textarea', 'rules' => 'nullable|string|max:3000'],
                    'support_info' => ['label' => 'اطلاعات پشتیبانی', 'type' => 'textarea', 'rules' => 'nullable|string|max:3000'],
                ],
            ],

            'seo' => [
                'label' => 'سئو (پیش‌فرض)',
                'fields' => [
                    'meta_title' => ['label' => 'Meta Title (با {brand})', 'type' => 'string', 'rules' => 'nullable|string|max:200'],
                    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'rules' => 'nullable|string|max:500'],
                ],
            ],

        ],
    ],

];
