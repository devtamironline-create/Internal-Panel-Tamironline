<?php

namespace Modules\Technician\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianSetting extends Model
{
    protected $table = 'technician_settings';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getJson(string $key, array $default = []): array
    {
        $value = static::where('key', $key)->value('value');
        if (!$value) return $default;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * مقادیر پیش‌فرض صفحه لندینگ
     */
    public static function defaults(): array
    {
        return [
            'page_title'         => 'همکاری با تعمیر آنلاین',
            'hero_title'         => 'به تیم تکنسین‌های ما بپیوندید',
            'hero_subtitle'      => 'کار آزاد، درآمد خوب، در شهر خودت',
            'hero_description'   => 'تعمیر آنلاین به تکنسین‌های حرفه‌ای در رشته‌های برق، تاسیسات، سرمایش و گرمایش، لوله‌کشی و دیگر حوزه‌های فنی نیاز دارد. با ما همکار شو و درآمد خود را افزایش بده.',
            'hero_cta_text'      => 'همین حالا ثبت‌نام کن',
            'hero_badge'         => 'جدیدترین فرصت شغلی',
            'benefits_title'     => 'چرا با تعمیر آنلاین همکار شوی؟',
            'benefits'           => json_encode([
                ['icon' => 'money',   'title' => 'درآمد بالا',          'description' => 'با هر سرویسی که انجام می‌دهی، مستقیم به حسابت واریز می‌شود.'],
                ['icon' => 'clock',   'title' => 'ساعت کاری آزاد',      'description' => 'خودت تعیین می‌کنی کی و کجا کار کنی. هیچ الزامی نداری.'],
                ['icon' => 'map',     'title' => 'کار در شهر خودت',     'description' => 'سرویس‌های نزدیک به محل سکونتت برایت ارسال می‌شود.'],
                ['icon' => 'support', 'title' => 'پشتیبانی ۲۴/۷',       'description' => 'تیم پشتیبانی ما همیشه آماده کمک به تکنسین‌هاست.'],
                ['icon' => 'growth',  'title' => 'رشد و پیشرفت',         'description' => 'با افزایش امتیاز و رتبه، سرویس‌های بهتر دریافت کن.'],
                ['icon' => 'tools',   'title' => 'ابزار حرفه‌ای',        'description' => 'اپلیکیشن هوشمند برای مدیریت سفارش‌ها، مسیریابی و درآمد.'],
            ], JSON_UNESCAPED_UNICODE),
            'steps_title'        => 'چطور شروع کنی؟',
            'steps'              => json_encode([
                ['number' => '۱', 'title' => 'ثبت‌نام کن',            'description' => 'فرم ثبت‌نام را تکمیل کن و مشخصات و مدارک فنی خود را آپلود کن.'],
                ['number' => '۲', 'title' => 'تأیید هویت',            'description' => 'مدارک تو توسط تیم ما بررسی و تأیید می‌شود.'],
                ['number' => '۳', 'title' => 'آموزش و آشنایی',         'description' => 'آموزش کوتاه سیستم و نحوه انجام سرویس را ببین.'],
                ['number' => '۴', 'title' => 'شروع به کار',           'description' => 'سفارش دریافت کن، سرویس انجام بده و پول بگیر!'],
            ], JSON_UNESCAPED_UNICODE),
            'requirements_title' => 'شرایط همکاری',
            'requirements'       => json_encode([
                'داشتن تخصص در حداقل یک رشته فنی',
                'حداقل ۲ سال سابقه کار عملی',
                'داشتن کارت ملی و اطلاعات هویتی معتبر',
                'داشتن گوشی هوشمند و اینترنت',
                'توانایی ارتباط موثر با مشتریان',
            ], JSON_UNESCAPED_UNICODE),
            'faq_title'          => 'سوالات متداول',
            'faq'                => json_encode([
                ['question' => 'آیا نیاز به تجربه قبلی دارم؟',           'answer' => 'بله، حداقل ۲ سال سابقه کار عملی در حوزه تخصصی خود لازم است.'],
                ['question' => 'پرداخت‌ها چطور انجام می‌شود؟',           'answer' => 'پرداخت هفتگی به حساب بانکی شما واریز می‌شود.'],
                ['question' => 'آیا می‌توانم در چند شهر فعال باشم؟',     'answer' => 'خیر، هر تکنسین فقط در شهر ثبت‌شده خود فعال است.'],
                ['question' => 'ابزار و تجهیزات را خودم تهیه کنم؟',     'answer' => 'بله، تکنسین باید ابزار اولیه کار خود را داشته باشد.'],
                ['question' => 'چقدر طول می‌کشد تا تأیید شوم؟',         'answer' => 'معمولاً بین ۲ تا ۵ روز کاری.'],
            ], JSON_UNESCAPED_UNICODE),
            'cta_title'          => 'آماده‌ای شروع کنی؟',
            'cta_description'    => 'همین الان ثبت‌نام کن و اولین سفارشت را دریافت کن.',
            'cta_badge'          => 'در حال پذیرش تکنسین',
            'cta_button_text'    => 'ثبت‌نام تکنسین',
            'cta_button_link'    => 'mailto:join@tamironline.ir',
            'cta_phone_text'     => 'تماس با ما',
            'cta_phone'          => '02100000000',
            'cta_footnote'       => 'پاسخگویی ۲۴ ساعته · بدون نیاز به قرار قبلی',
            'brand_name'         => 'تعمیر آنلاین',
        ];
    }
}
