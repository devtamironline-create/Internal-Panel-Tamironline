<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Objection;

/**
 * لیست استاندارد ایرادات قابل انتخاب توسط مشتری در فرم ثبت سفارش اپ موبایل.
 *
 * هر ایراد به یک یا چند دستگاه متصل می‌شود. matching بر اساس slug دستگاه است
 * (مثلاً 'washing-machine'). اگر در DB دستگاهی با آن slug نباشد، ایراد ساخته
 * می‌شود ولی attachment رد می‌شود و در خروجی command گزارش می‌شود.
 *
 * Idempotent: با slug unique چک می‌شود؛ دوبار اجرا = همان نتیجه.
 *
 *   php artisan db:seed --class=Database\\Seeders\\ObjectionsSeeder
 */
class ObjectionsSeeder extends Seeder
{
    /**
     * نقشه‌ی ایرادات بر اساس slug دستگاه.
     *
     * هر کلید سطح اول یک slug دستگاه (یا کلید 'all' برای همه).
     * هر آیتم: [slug => ['name' => ..., 'icon' => ..., 'description' => ?]]
     *
     * @var array<string, array<string, array<string, string|null>>>
     */
    private const ITEMS = [
        // ── ماشین لباسشویی ────────────────────────────────────────────
        'washing-machine' => [
            'wm-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'wm-no-spin' => ['name' => 'دلو نمی‌چرخد', 'icon' => 'rotate-cw'],
            'wm-no-drain' => ['name' => 'آب تخلیه نمی‌شود', 'icon' => 'droplet'],
            'wm-water-leak' => ['name' => 'نشتی آب از زیر دستگاه', 'icon' => 'droplets'],
            'wm-noise' => ['name' => 'صدای غیرعادی هنگام کار', 'icon' => 'volume-2'],
            'wm-vibration' => ['name' => 'لرزش زیاد', 'icon' => 'activity'],
            'wm-no-heat' => ['name' => 'آب گرم نمی‌شود', 'icon' => 'thermometer-snowflake'],
            'wm-error-code' => ['name' => 'نمایش کد خطا روی نمایشگر', 'icon' => 'alert-triangle'],
            'wm-door-stuck' => ['name' => 'درب باز نمی‌شود / گیر کرده', 'icon' => 'lock'],
            'wm-bad-smell' => ['name' => 'بوی بد می‌دهد', 'icon' => 'wind'],
            'wm-clothes-dirty' => ['name' => 'لباس‌ها تمیز نمی‌شوند', 'icon' => 'shirt'],
            'wm-not-rinsing' => ['name' => 'آبکشی به‌خوبی انجام نمی‌شود', 'icon' => 'shower-head'],
        ],

        // ── ظرفشویی ────────────────────────────────────────────────────
        'dishwasher' => [
            'dw-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'dw-dishes-dirty' => ['name' => 'ظروف کثیف می‌مانند', 'icon' => 'utensils'],
            'dw-water-leak' => ['name' => 'نشتی آب', 'icon' => 'droplets'],
            'dw-weak-spray' => ['name' => 'پاشش آب ضعیف است', 'icon' => 'shower-head'],
            'dw-no-drain' => ['name' => 'آب تخلیه نمی‌شود', 'icon' => 'droplet'],
            'dw-no-heat' => ['name' => 'آب گرم نمی‌شود', 'icon' => 'thermometer-snowflake'],
            'dw-no-dry' => ['name' => 'ظروف خشک نمی‌شوند', 'icon' => 'wind'],
            'dw-noise' => ['name' => 'صدای بلند هنگام کار', 'icon' => 'volume-2'],
            'dw-error-code' => ['name' => 'نمایش کد خطا', 'icon' => 'alert-triangle'],
            'dw-bad-smell' => ['name' => 'بوی بد', 'icon' => 'wind'],
        ],

        // ── یخچال / فریزر ──────────────────────────────────────────────
        'refrigerator' => [
            'fr-no-cool' => ['name' => 'سرما نمی‌گیرد', 'icon' => 'thermometer-sun'],
            'fr-weak-cool' => ['name' => 'سرمای کافی ندارد', 'icon' => 'thermometer'],
            'fr-no-freeze' => ['name' => 'فریزر یخ نمی‌زند', 'icon' => 'snowflake'],
            'fr-over-freeze' => ['name' => 'یخ‌زدگی زیاد در فریزر', 'icon' => 'snowflake'],
            'fr-water-leak' => ['name' => 'نشتی آب از یخچال', 'icon' => 'droplets'],
            'fr-noise' => ['name' => 'صدای غیرعادی', 'icon' => 'volume-2'],
            'fr-motor-always-on' => ['name' => 'موتور دائم کار می‌کند', 'icon' => 'activity'],
            'fr-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'fr-door-seal' => ['name' => 'لاستیک درب خراب / درب درست بسته نمی‌شود', 'icon' => 'door-closed'],
            'fr-bad-smell' => ['name' => 'بوی بد داخل', 'icon' => 'wind'],
            'fr-light-broken' => ['name' => 'لامپ داخلی روشن نمی‌شود', 'icon' => 'lightbulb'],
            'fr-ice-maker' => ['name' => 'آب‌چکان / یخ‌ساز مشکل دارد', 'icon' => 'droplet'],
        ],

        // ── کولر گازی / اسپلیت ──────────────────────────────────────
        'air-conditioner' => [
            'ac-no-cool' => ['name' => 'خنک نمی‌کند', 'icon' => 'thermometer-sun'],
            'ac-weak-cool' => ['name' => 'سرمای کافی ندارد', 'icon' => 'thermometer'],
            'ac-no-heat' => ['name' => 'گرم نمی‌کند (در حالت گرمایش)', 'icon' => 'thermometer-snowflake'],
            'ac-water-drip' => ['name' => 'چکه می‌کند / نشتی آب', 'icon' => 'droplets'],
            'ac-noise' => ['name' => 'صدای بلند', 'icon' => 'volume-2'],
            'ac-bad-smell' => ['name' => 'بوی بد می‌دهد', 'icon' => 'wind'],
            'ac-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'ac-remote-broken' => ['name' => 'ریموت کنترل کار نمی‌کند', 'icon' => 'tv'],
            'ac-low-gas' => ['name' => 'احتمال کم‌گاز بودن', 'icon' => 'fuel'],
            'ac-ice-forming' => ['name' => 'روی لوله‌ها یخ می‌زند', 'icon' => 'snowflake'],
            'ac-installation' => ['name' => 'نیاز به نصب / جابه‌جایی', 'icon' => 'wrench'],
        ],

        // ── پکیج / آبگرمکن دیواری ──────────────────────────────────
        'water-heater' => [
            'wh-no-hot' => ['name' => 'آب گرم نمی‌کند', 'icon' => 'thermometer-snowflake'],
            'wh-no-ignite' => ['name' => 'روشن نمی‌شود / جرقه نمی‌زند', 'icon' => 'flame'],
            'wh-water-leak' => ['name' => 'نشتی آب', 'icon' => 'droplets'],
            'wh-pressure-low' => ['name' => 'فشار آب پایین', 'icon' => 'gauge'],
            'wh-noise' => ['name' => 'صدای غیرعادی هنگام کار', 'icon' => 'volume-2'],
            'wh-weak-flame' => ['name' => 'شعله ضعیف', 'icon' => 'flame'],
            'wh-error-code' => ['name' => 'نمایش کد خطا', 'icon' => 'alert-triangle'],
            'wh-radiator-cold' => ['name' => 'رادیاتورها گرم نمی‌شوند', 'icon' => 'thermometer'],
            'wh-service' => ['name' => 'سرویس دوره‌ای / شست‌وشو', 'icon' => 'settings-2'],
        ],

        // ── اجاق گاز / فر ─────────────────────────────────────────────
        'gas-stove' => [
            'gs-no-ignite' => ['name' => 'فندک کار نمی‌کند', 'icon' => 'flame'],
            'gs-weak-flame' => ['name' => 'شعله ضعیف', 'icon' => 'flame'],
            'gs-gas-smell' => ['name' => 'بوی گاز می‌دهد', 'icon' => 'alert-triangle'],
            'gs-burner-clog' => ['name' => 'شعله یک یا چند بَرنر در نمی‌آید', 'icon' => 'flame-kindling'],
            'gs-oven-no-heat' => ['name' => 'فر گرم نمی‌شود', 'icon' => 'thermometer-snowflake'],
            'gs-oven-uneven' => ['name' => 'گرمای فر یکنواخت نیست', 'icon' => 'thermometer'],
            'gs-knob-broken' => ['name' => 'دکمه/ولوم شکسته یا گیر کرده', 'icon' => 'circle-dot'],
        ],

        // ── مایکروویو ──────────────────────────────────────────────────
        'microwave' => [
            'mw-no-heat' => ['name' => 'گرم نمی‌کند', 'icon' => 'thermometer-snowflake'],
            'mw-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'mw-noise' => ['name' => 'صدای بلند هنگام کار', 'icon' => 'volume-2'],
            'mw-sparks' => ['name' => 'جرقه می‌زند داخل', 'icon' => 'zap'],
            'mw-plate-not-spin' => ['name' => 'سینی نمی‌چرخد', 'icon' => 'rotate-cw'],
            'mw-door-issue' => ['name' => 'درب درست بسته نمی‌شود', 'icon' => 'door-closed'],
        ],

        // ── خشک‌کن ────────────────────────────────────────────────────
        'dryer' => [
            'dr-no-dry' => ['name' => 'لباس خشک نمی‌شود', 'icon' => 'wind'],
            'dr-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'dr-no-heat' => ['name' => 'گرم نمی‌شود', 'icon' => 'thermometer-snowflake'],
            'dr-noise' => ['name' => 'صدای بلند', 'icon' => 'volume-2'],
            'dr-drum-not-spin' => ['name' => 'دلو نمی‌چرخد', 'icon' => 'rotate-cw'],
            'dr-error-code' => ['name' => 'کد خطا روی نمایشگر', 'icon' => 'alert-triangle'],
        ],

        // ── جاروبرقی ──────────────────────────────────────────────────
        'vacuum-cleaner' => [
            'vc-weak-suction' => ['name' => 'مکش ضعیف شده', 'icon' => 'wind'],
            'vc-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'vc-noise' => ['name' => 'صدای بلند یا سوت می‌کشد', 'icon' => 'volume-2'],
            'vc-overheating' => ['name' => 'داغ می‌کند و خاموش می‌شود', 'icon' => 'thermometer-sun'],
            'vc-cord-issue' => ['name' => 'سیم/جمع‌کن سیم خراب است', 'icon' => 'cable'],
        ],

        // ── تلویزیون ──────────────────────────────────────────────────
        'television' => [
            'tv-no-power' => ['name' => 'روشن نمی‌شود', 'icon' => 'power-off'],
            'tv-no-image' => ['name' => 'صدا دارد ولی تصویر ندارد', 'icon' => 'image-off'],
            'tv-no-sound' => ['name' => 'تصویر دارد ولی صدا ندارد', 'icon' => 'volume-x'],
            'tv-lines' => ['name' => 'خط‌های افقی/عمودی روی صفحه', 'icon' => 'minus'],
            'tv-color-issue' => ['name' => 'رنگ‌ها به‌هم ریخته', 'icon' => 'palette'],
            'tv-remote-broken' => ['name' => 'ریموت کار نمی‌کند', 'icon' => 'tv'],
            'tv-backlight' => ['name' => 'تصویر تار / نور پس‌زمینه مشکل دارد', 'icon' => 'sun-dim'],
        ],

        // ── تصفیه‌ی آب ─────────────────────────────────────────────────
        'water-purifier' => [
            'wp-water-leak' => ['name' => 'نشتی آب', 'icon' => 'droplets'],
            'wp-low-flow' => ['name' => 'دبی آب خروجی کم شده', 'icon' => 'gauge'],
            'wp-bad-taste' => ['name' => 'مزه‌ی آب تغییر کرده', 'icon' => 'cup-soda'],
            'wp-filter-change' => ['name' => 'تعویض فیلتر / سرویس دوره‌ای', 'icon' => 'settings-2'],
            'wp-no-water' => ['name' => 'آب از شیر خارج نمی‌شود', 'icon' => 'droplet'],
        ],

        // ── همه دستگاه‌ها (در صورتی که کاربر دستگاه را نمی‌داند) ─────
        'all' => [
            'generic-other' => ['name' => 'مشکل دیگری دارم (در توضیحات بنویسید)', 'icon' => 'message-circle'],
            'generic-installation' => ['name' => 'نیاز به نصب / راه‌اندازی', 'icon' => 'wrench'],
            'generic-service' => ['name' => 'سرویس و نگهداری دوره‌ای', 'icon' => 'settings-2'],
            'generic-consultation' => ['name' => 'مشاوره و عیب‌یابی', 'icon' => 'message-square'],
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $skippedAttach = 0;
        $sortOrder = 0;
        $missingDeviceSlugs = [];

        // device slugs → ids (یک کوئری)
        $deviceMap = Device::query()->pluck('id', 'slug')->all();
        $allDeviceIds = array_values($deviceMap);

        DB::transaction(function () use ($deviceMap, $allDeviceIds, &$created, &$skippedAttach, &$sortOrder, &$missingDeviceSlugs) {
            foreach (self::ITEMS as $deviceSlug => $items) {
                foreach ($items as $objectionSlug => $payload) {
                    $sortOrder++;
                    $objection = Objection::firstOrCreate(
                        ['slug' => $objectionSlug],
                        [
                            'name' => $payload['name'],
                            'description' => $payload['description'] ?? null,
                            'icon' => $payload['icon'] ?? null,
                            'sort_order' => $sortOrder,
                            'is_active' => true,
                        ]
                    );
                    if ($objection->wasRecentlyCreated) {
                        $created++;
                    }

                    // اتصال به دستگاه‌ها
                    if ($deviceSlug === 'all') {
                        // برای 'all' یعنی به تمام دستگاه‌های فعلی متصل می‌شود
                        if (! empty($allDeviceIds)) {
                            $sync = [];
                            foreach ($allDeviceIds as $i => $deviceId) {
                                $sync[$deviceId] = ['sort_order' => $i];
                            }
                            $objection->devices()->syncWithoutDetaching($sync);
                        }

                        continue;
                    }

                    if (! isset($deviceMap[$deviceSlug])) {
                        $missingDeviceSlugs[$deviceSlug] = true;
                        $skippedAttach++;

                        continue;
                    }

                    $objection->devices()->syncWithoutDetaching([
                        $deviceMap[$deviceSlug] => ['sort_order' => $sortOrder],
                    ]);
                }
            }
        });

        $totalDefined = collect(self::ITEMS)->sum(fn ($items) => count($items));

        $this->command?->info(sprintf(
            'Objections seeded — %d created, %d already existed, total defined: %d',
            $created,
            $totalDefined - $created,
            $totalDefined
        ));

        if (! empty($missingDeviceSlugs)) {
            $this->command?->warn('این slug های دستگاه در DB پیدا نشدند (ایراد ساخته شد ولی attach رد شد):');
            foreach (array_keys($missingDeviceSlugs) as $slug) {
                $this->command?->line("  - {$slug}");
            }
            $this->command?->line('برای اتصال دستی، در /admin/crm/objections بروید.');
        }
    }
}
