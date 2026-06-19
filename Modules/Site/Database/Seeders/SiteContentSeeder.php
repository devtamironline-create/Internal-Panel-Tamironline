<?php

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Device;
use Modules\Site\Models\AboutStat;
use Modules\Site\Models\Faq;
use Modules\Site\Models\PageSection;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Models\Taxonomy;

/**
 * Seeder محتوای سایت بر اساس داده‌های لایو tamironline.com
 *
 * idempotent — هر بار اجرا، رکوردها updateOrCreate می‌شوند تا داده‌ی موجود
 * را خراب نکند.
 *
 *   php artisan db:seed --class="Modules\Site\Database\Seeders\SiteContentSeeder"
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SiteCrmCatalogSeeder::class]);

        $this->seedSettings();
        $this->seedAboutStats();
        $this->seedTaxonomies();
        $this->seedFaqs();
        $this->seedPageSections();

        $this->command?->info('Site content seeded.');
    }

    // ─── Site settings ─────────────────────────────────────────────
    private function seedSettings(): void
    {
        $settings = [
            // general
            ['key' => 'site_name',     'value' => 'تعمیرآنلاین', 'group' => 'general'],
            ['key' => 'site_tagline',  'value' => 'در تعمیرآنلاین رضایت مشتری یک شعار نیست', 'group' => 'general'],

            // contact
            ['key' => 'contact_phone',         'value' => '021-45396', 'group' => 'contact'],
            ['key' => 'contact_phones',        'value' => json_encode([['label' => 'تماس و پشتیبانی', 'number' => '021-45396']], JSON_UNESCAPED_UNICODE), 'group' => 'contact'],
            ['key' => 'contact_email',         'value' => 'support@tamironline.com', 'group' => 'contact'],
            ['key' => 'contact_address',       'value' => 'تهران، خیابان مطهری، نرسیده به خیابان ترکمنستان، پلاک ۲۰، ساختمان تعمیرآنلاین', 'group' => 'contact'],
            ['key' => 'contact_working_hours', 'value' => 'شنبه تا پنج‌شنبه ۹ تا ۲۲ — روزهای تعطیل ۱۰ تا ۱۸', 'group' => 'contact'],

            // social
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/tamironlinecom', 'group' => 'social'],
            ['key' => 'social_youtube',   'value' => 'https://youtube.com/@tamironlinecom', 'group' => 'social'],

            // seo
            ['key' => 'seo_default_title',       'value' => 'تعمیرآنلاین | تعمیر لوازم خانگی', 'group' => 'seo'],
            ['key' => 'seo_default_description', 'value' => 'شرکت معتبر خدمات لوازم خانگی تحت نظارت اتحادیه اصناف — تعمیر در محل با ۶ ماه گارانتی.', 'group' => 'seo'],
        ];

        foreach ($settings as $row) {
            SiteSetting::updateOrCreate(['key' => $row['key']], ['value' => $row['value'], 'group' => $row['group']]);
        }
    }

    // ─── About stats ───────────────────────────────────────────────
    private function seedAboutStats(): void
    {
        $stats = [
            ['key' => 'experience_years', 'value' => '۸+',          'label' => 'سال تجربه',           'tone' => 'blue',   'sort_order' => 10],
            ['key' => 'repairs_count',    'value' => '۵۰,۰۰۰+',     'label' => 'تعمیر موفق',          'tone' => 'green',  'sort_order' => 20],
            ['key' => 'technicians',      'value' => '۲۰۰+',        'label' => 'تکنسین متخصص',         'tone' => 'amber',  'sort_order' => 30],
            ['key' => 'satisfaction',     'value' => '۹۸٪',         'label' => 'رضایت مشتری',          'tone' => 'rose',   'sort_order' => 40],
            ['key' => 'followers',        'value' => '۱.۵ میلیون+', 'label' => 'دنبال‌کننده شبکه‌های اجتماعی', 'tone' => 'violet', 'sort_order' => 50],
            ['key' => 'service_areas',    'value' => '۳۳ منطقه',    'label' => 'تهران و کرج',         'tone' => 'blue',   'sort_order' => 60],
        ];

        foreach ($stats as $row) {
            AboutStat::updateOrCreate(['key' => $row['key']], $row + ['is_published' => true]);
        }
    }

    // ─── Taxonomies (FAQ + Testimonial categories) ────────────────
    private function seedTaxonomies(): void
    {
        $faqCats = [
            ['slug' => 'support',  'name' => 'پشتیبانی',     'sort_order' => 10],
            ['slug' => 'usage',    'name' => 'استفاده',      'sort_order' => 20],
            ['slug' => 'pricing',  'name' => 'هزینه و تعرفه', 'sort_order' => 30],
            ['slug' => 'warranty', 'name' => 'شرایط گارانتی', 'sort_order' => 40],
            ['slug' => 'general',  'name' => 'سوالات عمومی', 'sort_order' => 50],
        ];

        foreach ($faqCats as $row) {
            Taxonomy::updateOrCreate(
                ['type' => Taxonomy::TYPE_FAQ, 'slug' => $row['slug']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order'], 'is_active' => true]
            );
        }

        $testimonialCats = [
            ['slug' => 'washing-machine', 'name' => 'لباس‌شویی',  'sort_order' => 10],
            ['slug' => 'refrigerator',    'name' => 'یخچال',     'sort_order' => 20],
            ['slug' => 'dishwasher',      'name' => 'ظرفشویی',   'sort_order' => 30],
            ['slug' => 'general',         'name' => 'عمومی',     'sort_order' => 90],
        ];

        foreach ($testimonialCats as $row) {
            Taxonomy::updateOrCreate(
                ['type' => Taxonomy::TYPE_TESTIMONIAL, 'slug' => $row['slug']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order'], 'is_active' => true]
            );
        }
    }

    // ─── FAQs ──────────────────────────────────────────────────────
    private function seedFaqs(): void
    {
        $faqCats = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->get()->keyBy('slug');

        $faqs = [
            // پشتیبانی
            ['cat' => 'support', 'q' => 'چطور از وضعیت سفارش خود مطلع شوم؟',
                'a' => 'با ثبت سفارش، یک شماره پیگیری دریافت می‌کنید که از طریق پنل کاربری یا تماس با پشتیبانی می‌توانید وضعیت لحظه‌ای آن را ببینید.'],
            ['cat' => 'support', 'q' => 'ساعت کاری پشتیبانی چه زمانی است؟',
                'a' => 'تیم پشتیبانی تعمیرآنلاین همه روزه از ساعت ۹ صبح تا ۱۰ شب در دسترس است.'],
            ['cat' => 'support', 'q' => 'اپراتور پس از چه مدتی با من تماس می‌گیرد؟',
                'a' => 'پس از ثبت سفارش، اپراتور در کمتر از ۶۰ دقیقه با شما تماس می‌گیرد.'],

            // استفاده
            ['cat' => 'usage', 'q' => 'چطور سفارش تعمیر ثبت کنم؟',
                'a' => 'از طریق فرم سفارش در سایت، تماس تلفنی با شماره‌ی ۰۲۱-۴۵۳۹۶، یا اپلیکیشن می‌توانید سفارش خود را ثبت کنید.'],
            ['cat' => 'usage', 'q' => 'آیا تعمیر در محل انجام می‌شود؟',
                'a' => 'بله، اکثر تعمیرات در محل و در منزل شما انجام می‌شود؛ مگر آن که نیاز به تجهیزات کارگاهی داشته باشد.'],
            ['cat' => 'usage', 'q' => 'تعمیر {device} چقدر زمان می‌برد؟',
                'a' => 'تعمیر {device} معمولاً بین ۱ تا ۳ ساعت زمان می‌برد. در صورت نیاز به قطعه‌ی خاص، تکنسین زمان دقیق را به شما اعلام می‌کند.'],

            // هزینه
            ['cat' => 'pricing', 'q' => 'هزینه ایاب و ذهاب و تشخیص چقدر است؟',
                'a' => 'هزینه ایاب و ذهاب و تشخیص ایراد ۲۰۰ هزار تومان است که قبل از اعزام تکنسین به اطلاع شما می‌رسد.'],
            ['cat' => 'pricing', 'q' => 'آیا قبل از تعمیر، قیمت اعلام می‌شود؟',
                'a' => 'بله، پس از تشخیص ایراد، تکنسین قیمت کامل تعمیر را اعلام می‌کند و در صورت تأیید شما، تعمیر آغاز می‌شود.'],

            // گارانتی
            ['cat' => 'warranty', 'q' => 'گارانتی تعمیرات چند ماه است؟',
                'a' => 'همه‌ی تعمیرات تعمیرآنلاین شامل ۶ ماه گارانتی کیفیت قطعات و خدمات هستند.'],
            ['cat' => 'warranty', 'q' => 'گارانتی شامل چه مواردی می‌شود؟',
                'a' => 'گارانتی شامل ایراد مجدد در همان قسمت تعمیر شده می‌شود. ضربه، نوسان برق و آب‌خوردگی شامل گارانتی نمی‌شوند.'],
            ['cat' => 'warranty', 'q' => 'اگر همان ایراد مجدداً تکرار شد چه می‌شود؟',
                'a' => 'در دوره‌ی گارانتی، اگر همان ایراد قبلی تکرار شود، تکنسین بدون دریافت هزینه‌ی اضافه برای رفع مشکل اعزام می‌شود.'],

            // عمومی
            ['cat' => 'general', 'q' => 'خدمات شما در چه مناطقی ارائه می‌شود؟',
                'a' => 'تعمیرآنلاین در ۲۲ منطقه‌ی تهران و ۱۱ منطقه‌ی کرج خدمات ارائه می‌دهد.'],
            ['cat' => 'general', 'q' => 'آیا خدمات در روزهای تعطیل هم ارائه می‌شود؟',
                'a' => 'بله، تعمیرآنلاین در روزهای تعطیل نیز خدمات ارائه می‌دهد، البته زمان اعزام ممکن است متفاوت باشد.'],
            ['cat' => 'general', 'q' => 'چطور می‌توانم شکایت ثبت کنم؟',
                'a' => 'از طریق فرم تماس با ما در سایت، تماس تلفنی، یا ایمیل support@tamironline.com می‌توانید شکایت یا پیشنهاد خود را ثبت کنید.'],
        ];

        $order = 0;
        foreach ($faqs as $row) {
            $faq = Faq::updateOrCreate(
                ['question' => $row['q']],
                ['answer' => $row['a'], 'is_published' => true, 'sort_order' => $order += 10]
            );
            if ($cat = $faqCats->get($row['cat'])) {
                $faq->taxonomies()->syncWithoutDetaching([$cat->id]);
            }
        }
    }

    // ─── Page sections ─────────────────────────────────────────────
    private function seedPageSections(): void
    {
        $devices = Device::query()->where('is_active', true)->pluck('id')->all();
        $supportCatId = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->where('slug', 'support')->value('id');
        $warrantyCatId = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->where('slug', 'warranty')->value('id');
        $pricingCatId = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->where('slug', 'pricing')->value('id');
        $generalCatId = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->where('slug', 'general')->value('id');
        $usageCatId = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->where('slug', 'usage')->value('id');

        $faqCatsAll = array_values(array_filter([$supportCatId, $usageCatId, $pricingCatId, $warrantyCatId, $generalCatId]));

        $pages = [
            'home' => [
                'hero' => [
                    'title' => 'تعمیر در محل لوازم خانگی',
                    'subtitle' => 'با تکنسین‌های مجرب، قطعات اصلی و ۶ ماه گارانتی کتبی',
                    'cta_label' => 'ثبت سفارش',
                    'cta_url' => '/order',
                    'services' => $devices,
                ],
                'why_us' => [
                    'title' => 'چرا تعمیرآنلاین؟',
                    'subtitle' => 'با اطمینان خاطر، خدمات حرفه‌ای دریافت کنید',
                    'items' => [
                        ['icon' => 'headphones', 'title' => 'پشتیبانی قوی',     'description' => 'تیم پشتیبانی همه‌روزه از ۹ تا ۲۲ پاسخگوی شماست.'],
                        ['icon' => 'user-check', 'title' => 'تعمیرکاران کاربلد', 'description' => 'تکنسین‌های متخصص و آموزش‌دیده با تجربه‌ی بالا.'],
                        ['icon' => 'shield',     'title' => 'گارانتی ۶ ماهه',    'description' => 'تمام خدمات با گارانتی کتبی ۶ ماهه ارائه می‌شوند.'],
                        ['icon' => 'wrench',     'title' => 'قطعات اصلی',       'description' => 'استفاده از قطعات اورجینال با گارانتی.'],
                        ['icon' => 'credit-card', 'title' => 'تعرفه شفاف',       'description' => 'قیمت‌ها قبل از تعمیر اعلام می‌شوند.'],
                        ['icon' => 'clock',      'title' => 'سرعت بالا',         'description' => 'اعزام تکنسین در کمتر از ۳ ساعت.'],
                    ],
                ],
                'promo' => [
                    'zone_slug' => 'home_promo',
                ],
                'faq' => [
                    'title' => 'پرسش‌های متداول',
                    'subtitle' => 'هر سوالی دارید — احتمالاً پاسخ آن را اینجا می‌یابید.',
                    'category_ids' => $faqCatsAll,
                ],
            ],
            'about' => [
                'hero' => [
                    'title' => 'درباره تعمیرآنلاین',
                    'subtitle' => 'تعمیرآنلاین چگونه متولد شد؟',
                    'description' => 'تعمیرآنلاین در اردیبهشت ۱۳۹۶ با هدف تسهیل خدمات تعمیر لوازم خانگی متولد شد. امروز با بیش از ۲۰۰ تکنسین متخصص در ۲۲ منطقه‌ی تهران و ۱۱ منطقه‌ی کرج خدمت‌رسانی می‌کنیم.',
                    'highlights' => [
                        ['icon' => 'shield-check', 'text' => 'تکنسین‌های متخصص با مجوز رسمی'],
                        ['icon' => 'shield-check', 'text' => 'استفاده از قطعات اصلی و باکیفیت'],
                        ['icon' => 'shield-check', 'text' => 'گارانتی کتبی ۶ ماهه'],
                        ['icon' => 'shield-check', 'text' => 'قیمت شفاف، بدون هزینه پنهان'],
                    ],
                ],
                'stats' => [
                    'title' => 'تعمیرآنلاین در یک نگاه',
                    'subtitle' => 'اعداد و آماری که نشان‌دهنده‌ی کیفیت کار ماست',
                    'items' => [
                        ['key' => 'experience_years', 'value' => '۸+',          'label' => 'سال تجربه',          'tone' => 'blue'],
                        ['key' => 'repairs_count',    'value' => '۵۰,۰۰۰+',     'label' => 'تعمیر موفق',         'tone' => 'green'],
                        ['key' => 'technicians',      'value' => '۲۰۰+',        'label' => 'تکنسین متخصص',        'tone' => 'amber'],
                        ['key' => 'satisfaction',     'value' => '۹۸٪',         'label' => 'رضایت مشتری',         'tone' => 'rose'],
                        ['key' => 'followers',        'value' => '۱.۵ میلیون+', 'label' => 'دنبال‌کننده',         'tone' => 'violet'],
                        ['key' => 'service_areas',    'value' => '۳۳ منطقه',    'label' => 'تهران و کرج',         'tone' => 'blue'],
                    ],
                ],
                'values' => [
                    'title' => 'ارزش‌های ما',
                    'subtitle' => 'آنچه ما را متفاوت می‌کند',
                    'items' => [
                        ['icon' => 'headphones', 'title' => 'پشتیبانی قوی',     'description' => 'پاسخگویی سریع و رفع مشکل بدون معطلی.'],
                        ['icon' => 'award',      'title' => 'تعمیرکاران کاربلد', 'description' => 'تخصص و تجربه‌ی واقعی در تعمیر لوازم خانگی.'],
                        ['icon' => 'shield',     'title' => 'شش ماه گارانتی',   'description' => 'تضمین کیفیت تعمیر برای ۶ ماه کامل.'],
                        ['icon' => 'eye',        'title' => 'تعرفه شفاف',       'description' => 'بدون هزینه‌ی پنهان، همه چیز از ابتدا اعلام می‌شود.'],
                        ['icon' => 'zap',        'title' => 'سرعت بالا',         'description' => 'حداکثر زمان اعزام: ۳ ساعت در روزهای عادی.'],
                        ['icon' => 'star',       'title' => 'قطعات با کیفیت',   'description' => 'استفاده از قطعات اصلی و دارای گارانتی.'],
                    ],
                ],
                'timeline' => [
                    'title' => 'سال‌شمار تعمیرآنلاین',
                    'items' => [
                        ['year' => '۱۳۹۶', 'title' => 'تأسیس شرکت', 'description' => 'ثبت‌نام رسمی برند تعمیرآنلاین و شروع فعالیت در تهران.'],
                        ['year' => '۱۳۹۸', 'title' => 'توسعه خدمات', 'description' => 'افزودن خدمات جدید و ورود به شبکه‌های اجتماعی.'],
                        ['year' => '۱۴۰۰', 'title' => 'یک میلیون دنبال‌کننده', 'description' => 'رسیدن به جامعه‌ی یک میلیون نفری در اینستاگرام.'],
                        ['year' => '۱۴۰۲', 'title' => 'گسترش به کرج', 'description' => 'شروع سرویس‌دهی در ۱۱ منطقه‌ی کرج.'],
                    ],
                ],
                'faq' => [
                    'title' => 'پرسش‌های متداول',
                    'category_ids' => array_values(array_filter([$supportCatId, $warrantyCatId, $generalCatId])),
                ],
                'promo' => [
                    'zone_slug' => 'about_promo',
                ],
            ],
            'services' => [
                'promo' => [
                    'zone_slug' => 'services_promo',
                ],
            ],
            'blog' => [
                'banner' => [
                    'zone_slug' => 'blog_hero',
                ],
            ],
            'device' => [
                'promo' => [
                    'zone_slug' => 'device_promo',
                ],
            ],
            'brand' => [
                'promo' => [
                    'zone_slug' => 'brand_promo',
                ],
            ],
            'device_brand' => [
                'promo' => [
                    'zone_slug' => 'brand_device_promo',
                ],
            ],
            'contact' => [
                'channels' => [
                    'title' => 'راه‌های ارتباطی',
                    'items' => [
                        ['icon' => 'phone',     'title' => 'تماس تلفنی',    'value' => '۰۲۱-۴۵۳۹۶',           'link_url' => 'tel:02145396',          'description' => 'پاسخگویی همه روزه از ۹ تا ۲۲'],
                        ['icon' => 'phone',     'title' => 'خط دوم',        'value' => '۰۲۱-۵۸۹۵۳۰۰۰',        'link_url' => 'tel:02158953000',       'description' => 'پاسخگویی همه روزه از ۹ تا ۲۲'],
                        ['icon' => 'mail',      'title' => 'ایمیل',         'value' => 'support@tamironline.com', 'link_url' => 'mailto:support@tamironline.com', 'description' => 'پاسخگویی ظرف ۲۴ ساعت'],
                        ['icon' => 'instagram', 'title' => 'اینستاگرام',     'value' => '@tamironlinecom',     'link_url' => 'https://instagram.com/tamironlinecom', 'description' => 'دنبال کنید'],
                    ],
                ],
                'info' => [
                    'phone' => '۰۲۱-۴۵۳۹۶',
                    'support_phone' => '۰۲۱-۵۸۹۵۳۰۰۰',
                    'email' => 'support@tamironline.com',
                    'address' => 'تهران، خیابان مطهری، نرسیده به خیابان ترکمنستان، پلاک ۲۰، ساختمان تعمیرآنلاین',
                ],
                'hours' => [
                    'note' => 'تیم پاسخگویی همه روزه آماده‌ی خدمت‌رسانی است',
                    'items' => [
                        ['day' => 'شنبه تا چهارشنبه', 'hours' => '۹ تا ۱۸'],
                        ['day' => 'پنج‌شنبه',          'hours' => '۹ تا ۱۵'],
                        ['day' => 'تیم پاسخگویی',     'hours' => 'شنبه تا پنج‌شنبه ۹ تا ۲۲'],
                        ['day' => 'روزهای تعطیل',     'hours' => '۱۰ تا ۱۸'],
                    ],
                ],
                'map' => [
                    'lat' => '35.723956',
                    'lng' => '51.440499',
                    'neshan_url' => 'https://nshn.ir/_tamironline',
                    'zoom' => 16,
                ],
                'social' => [
                    'items' => [
                        ['platform' => 'instagram', 'label' => 'اینستاگرام', 'url' => 'https://instagram.com/tamironlinecom', 'icon' => 'instagram'],
                        ['platform' => 'youtube',   'label' => 'یوتیوب',     'url' => 'https://youtube.com/@tamironlinecom',  'icon' => 'youtube'],
                        ['platform' => 'aparat',    'label' => 'آپارات',     'url' => 'https://aparat.com/tamironline',       'icon' => 'video'],
                    ],
                ],
                'faq' => [
                    'title' => 'سوالات متداول تماس',
                    'category_ids' => array_values(array_filter([$supportCatId, $generalCatId])),
                ],
            ],
            'layout' => [
                'header' => [
                    'logo_alt' => 'تعمیرآنلاین',
                    'cta_label' => 'ثبت سفارش',
                    'cta_url' => '/order',
                    'phone_label' => 'پشتیبانی',
                    'phone_number' => '۰۲۱-۴۵۳۹۶',
                    'nav_items' => [
                        ['label' => 'صفحه اصلی',    'href' => '/'],
                        ['label' => 'درباره ما',     'href' => '/about'],
                        ['label' => 'تماس با ما',    'href' => '/contact'],
                        ['label' => 'بلاگ',          'href' => '/blog'],
                    ],
                    'services_dropdown' => [
                        'trigger_label' => 'خدمات',
                        'title' => 'خدمات تعمیر در محل',
                        'subtitle' => 'تعمیر لوازم خانگی با گارانتی ۶ ماهه — انتخاب دستگاه:',
                        'view_all_label' => 'مشاهده همه دستگاه‌ها',
                        'view_all_url' => '/devices',
                        'device_ids' => $devices, // همه دستگاه‌های فعال (id ها)
                    ],
                ],
                'footer' => [
                    'description' => 'تعمیرآنلاین، خدمات تعمیر لوازم خانگی در محل با بیش از ۸ سال سابقه — تحت نظارت اتحادیه اصناف.',
                    'contact_info' => [
                        'title' => 'اطلاعات تماس',
                        'address' => 'تهران، خیابان مطهری، نرسیده به خیابان ترکمنستان، پلاک ۲۰، ساختمان تعمیرآنلاین',
                        'phone' => '02145396',
                        'phone_display' => '۰۲۱-۴۵۳۹۶',
                        'email' => 'support@tamironline.com',
                    ],
                    'app_download' => [
                        'title' => 'اپلیکیشن تعمیرآنلاین',
                        'subtitle' => 'سفارش سریع و پیگیری از موبایل — هم اندروید هم iOS.',
                        'stores' => [
                            ['name' => 'Google Play',  'icon' => 'google-play', 'url' => 'https://play.google.com/store/apps/details?id=com.tamironline', 'image' => null],
                            ['name' => 'کافه بازار',   'icon' => 'bazaar',      'url' => 'https://cafebazaar.ir/app/com.tamironline', 'image' => null],
                            ['name' => 'سیب اپ',      'icon' => 'sibapp',      'url' => 'https://sibapp.com/applications/tamironline', 'image' => null],
                        ],
                    ],
                    'social' => [
                        ['platform' => 'instagram', 'icon' => 'instagram', 'url' => 'https://instagram.com/tamironlinecom'],
                        ['platform' => 'youtube',   'icon' => 'youtube',   'url' => 'https://youtube.com/@tamironlinecom'],
                        ['platform' => 'aparat',    'icon' => 'video',     'url' => 'https://aparat.com/tamironline'],
                    ],
                    'copyright_text' => 'تمام حقوق مادی و معنوی این وب‌سایت متعلق به تعمیرآنلاین می‌باشد.',
                ],
                'service_features' => [
                    'aria_label' => 'ویژگی‌های ما',
                    'speed' => 8,
                    'items' => [
                        ['icon_key' => 'shield',      'label' => 'گارانتی ۶ ماهه کتبی', 'bg' => '#ecfdf5', 'fg' => '#047857', 'border' => '#a7f3d0'],
                        ['icon_key' => 'clock',       'label' => 'اعزام در ۳ ساعت',     'bg' => '#eff6ff', 'fg' => '#1d4ed8', 'border' => '#bfdbfe'],
                        ['icon_key' => 'user-check',  'label' => 'تکنسین مجرب',          'bg' => '#f5f3ff', 'fg' => '#6d28d9', 'border' => '#ddd6fe'],
                        ['icon_key' => 'wrench',      'label' => 'قطعات اصلی',           'bg' => '#fffbeb', 'fg' => '#a16207', 'border' => '#fde68a'],
                        ['icon_key' => 'map-pin',     'label' => 'تعمیر در محل',         'bg' => '#fff1f2', 'fg' => '#be123c', 'border' => '#fecdd3'],
                        ['icon_key' => 'credit-card', 'label' => 'قیمت شفاف',            'bg' => '#ecfeff', 'fg' => '#0e7490', 'border' => '#a5f3fc'],
                        ['icon_key' => 'thumbs-up',   'label' => 'رضایت ۹۸٪ مشتریان',    'bg' => '#ffedd5', 'fg' => '#c2410c', 'border' => '#fed7aa'],
                        ['icon_key' => 'sparkles',    'label' => 'خدمات تخصصی',          'bg' => '#fdf4ff', 'fg' => '#a21caf', 'border' => '#f5d0fe'],
                        ['icon_key' => 'award',       'label' => 'تجربه ۸+ سال',         'bg' => '#f0fdfa', 'fg' => '#0f766e', 'border' => '#99f6e4'],
                    ],
                ],
                'seo_footer' => [
                    'title' => 'درباره تعمیرآنلاین',
                    'expand_label' => 'ادامه‌ی متن',
                    'collapse_label' => 'بستن',
                    'paragraphs' => [
                        ['text' => 'تعمیرآنلاین یکی از معتبرترین مجموعه‌های ارائه‌دهنده‌ی خدمات تعمیر لوازم خانگی در ایران است که با هدف ساده‌سازی فرایند تعمیر و افزایش کیفیت خدمات راه‌اندازی شده است. ما در تعمیرآنلاین تلاش می‌کنیم با گردآوری تیمی از تکنسین‌های متخصص و آموزش‌دیده، خدمات استاندارد و قابل‌اعتمادی برای تعمیر ماشین لباسشویی، ماشین ظرفشویی، یخچال و فریزر، کولر گازی، پکیج، اجاق گاز، ماکروفر و سایر لوازم خانگی در محل و در سراسر کشور ارائه دهیم.'],
                        ['text' => 'تمامی خدمات با گارانتی کتبی ۶ ماهه و تعرفه‌ی شفاف ارائه می‌شود؛ بدین معنا که پیش از شروع تعمیر، هزینه‌ی دقیق به اطلاع شما می‌رسد و در صورت بروز هرگونه مشکل در بازه‌ی گارانتی، تکنسین به‌صورت رایگان بازبینی و رفع ایراد را انجام می‌دهد. تیم پشتیبانی تعمیرآنلاین هفت روز هفته آماده پاسخگویی به سؤالات شماست و در کمتر از یک ساعت پس از ثبت سفارش، تکنسین متخصص به آدرس شما اعزام می‌شود.'],
                        ['text' => 'علاوه بر خدمات تعمیر، تعمیرآنلاین خدمات نصب، سرویس دوره‌ای و مشاوره‌ی فنی نیز ارائه می‌دهد. ما به دنبال ایجاد رابطه‌ی بلندمدت با مشتریان هستیم و معتقدیم رضایت مشتری مهم‌ترین سرمایه‌ی ماست. به همین دلیل تمامی قطعات استفاده‌شده اورجینال و دارای ضمانت هستند و کلیه‌ی تکنسین‌ها زیر نظر کارشناسان فنی تعمیرآنلاین فعالیت می‌کنند.'],
                    ],
                ],
                'mobile_cta' => [
                    'is_active' => true,
                    'primary' => [
                        'label' => 'تماس',
                        'icon' => 'phone',
                        'type' => 'tel',
                        'value' => '02145396',
                    ],
                    'secondary' => [
                        'label' => 'ثبت سفارش',
                        'icon' => 'wrench',
                        'type' => 'link',
                        'value' => '/order',
                    ],
                ],
            ],
        ];

        foreach ($pages as $pageSlug => $sections) {
            foreach ($sections as $sectionKey => $payload) {
                PageSection::updateOrCreate(
                    ['page_slug' => $pageSlug, 'section_key' => $sectionKey],
                    ['payload' => $payload, 'is_published' => true]
                );
            }
        }
    }
}
