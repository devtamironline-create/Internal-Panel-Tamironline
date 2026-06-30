<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;

/**
 * ایمپورتِ قیمتِ خدمات از CSV (ستون‌ها: دسته‌بندی، خدمت، هزینه، مبلغ عددی، قیمت جدید).
 *
 * دسته‌بندی → دستگاه (تطبیق با نامِ فارسی یا نقشهٔ alias). قیمتِ زنده پیش‌فرض از
 * ستونِ «قیمت جدید» خوانده می‌شود (با --price=current می‌توان «هزینهٔ فعلی» را گذاشت)؛
 * ستونِ دیگر در compare_at_price ذخیره می‌شود. «رایگان» → is_free.
 *
 * idempotent: کلید = device_id + title. Dry-run by default.
 *
 *   php artisan crm:import-service-prices /path/to/prices.csv
 *   php artisan crm:import-service-prices /path/to/prices.csv --apply
 *   php artisan crm:import-service-prices /path/to/prices.csv --apply --price=current
 */
class ImportServicePrices extends Command
{
    protected $signature = 'crm:import-service-prices
                            {file? : مسیرِ فایلِ CSV}
                            {--apply : اعمالِ واقعی (پیش‌فرض dry-run)}
                            {--price=new : ستونِ قیمتِ زنده: new (قیمت جدید) یا current (هزینهٔ فعلی)}
                            {--list-devices : فقط لیستِ دستگاه‌ها (id، نام، slug) را چاپ کن و خارج شو}';

    protected $description = 'ایمپورتِ قیمتِ خدمات به‌ازای دستگاه از فایل CSV';

    /** @var \Illuminate\Support\Collection<int,\Modules\CRM\Models\Device> */
    private $deviceList;

    /** دسته‌بندیِ CSV → slugِ دستگاه (fallback وقتی تطبیقِ نام نشد). */
    private const ALIAS = [
        'لباسشویی' => 'washing-machine',
        'ظرفشویی' => 'dishwasher',
        'یخچال - ساید بای ساید' => 'refrigerator-freezer',
        'اجاق گاز' => 'gaz',
        'فر گازی' => 'gas-oven',
        'هود' => 'hood',
        'خشک کن' => 'dryer',
        'کولر گازی' => 'split-air-conditioner',
        'اسپیلت' => 'split-air-conditioner',
        'اسپلیت' => 'split-air-conditioner',
        'ماکروفر' => 'microwave',
        'پکیج' => 'wall-mounted-boiler',
        'آبگرمکن' => 'water-heater',
        'تصفیه آب' => 'water-purifier',
        'تلویزیون' => 'tv',
        'تلویزیون (lcd / led)' => 'tv',
    ];

    public function handle(): int
    {
        if ($this->option('list-devices')) {
            $rows = Device::query()->ordered()->get(['id', 'name', 'slug', 'is_active'])
                ->map(fn ($d) => [$d->id, $d->name, $d->slug, $d->is_active ? 'فعال' : 'غیرفعال'])->all();
            $this->table(['id', 'نام', 'slug', 'وضعیت'], $rows);

            return self::SUCCESS;
        }

        $file = (string) $this->argument('file');
        if ($file === '' || ! is_file($file)) {
            $this->error("فایل پیدا نشد: {$file}");

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $useNew = $this->option('price') !== 'current';
        $this->info(($apply ? 'اعمالِ واقعی' : 'Dry-run').' | قیمتِ زنده از ستونِ: '.($useNew ? '«قیمت جدید»' : '«هزینهٔ فعلی»'));

        $rows = array_map('str_getcsv', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        array_shift($rows); // header

        // کشِ دستگاه‌ها برای تطبیق
        $devices = Device::query()->get(['id', 'name', 'slug']);
        $byName = [];
        $bySlug = [];
        foreach ($devices as $d) {
            $byName[$this->norm($d->name)] = $d;
            $bySlug[$d->slug] = $d;
        }
        $this->deviceList = $devices;

        $created = 0;
        $updated = 0;
        $unmatched = [];
        $sortByDevice = [];

        foreach ($rows as $r) {
            $cat = trim((string) ($r[0] ?? ''));
            $title = trim((string) ($r[1] ?? ''));
            if ($cat === '' || $title === '') {
                continue;
            }

            $device = $this->resolveDevice($cat, $byName, $bySlug);
            if (! $device) {
                $unmatched[$cat] = ($unmatched[$cat] ?? 0) + 1;

                continue;
            }

            [$price, $isFree] = $this->parsePrice((string) ($r[$useNew ? 4 : 3] ?? ''));
            [$compare] = $this->parsePrice((string) ($r[$useNew ? 3 : 4] ?? ''));

            $sortByDevice[$device->id] = ($sortByDevice[$device->id] ?? 0) + 1;
            $payload = [
                'price' => $price,
                'compare_at_price' => $compare,
                'is_free' => $isFree,
                'sort_order' => $sortByDevice[$device->id],
                'is_active' => true,
            ];

            if (! $apply) {
                $created++; // در dry-run فقط شمارش
                continue;
            }

            $existing = DeviceServicePrice::where('device_id', $device->id)->where('title', $title)->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                DeviceServicePrice::create(array_merge($payload, ['device_id' => $device->id, 'title' => $title]));
                $created++;
            }
        }

        $this->newLine();
        $this->info("ساخته: {$created} | به‌روز: {$updated}");
        if (! empty($unmatched)) {
            $this->newLine();
            $this->warn('دسته‌بندی‌های بدونِ دستگاهِ متناظر (این خدمات وارد نشدند):');
            foreach ($unmatched as $cat => $n) {
                $this->line("  • «{$cat}» ({$n} خدمت) — دستگاهی با این نام/الگو پیدا نشد. نامِ دستگاه را در پنل اصلاح کنید یا alias اضافه شود.");
            }
        }
        if (! $apply) {
            $this->comment('این dry-run بود؛ چیزی ذخیره نشد. برای اعمال --apply بزن.');
        }

        return self::SUCCESS;
    }

    private function resolveDevice(string $cat, array $byName, array $bySlug): ?Device
    {
        $n = $this->norm($cat);

        // ۱) تطبیقِ دقیقِ نام
        if (isset($byName[$n])) {
            return $byName[$n];
        }

        // ۲) alias (با کلیدِ خام یا نرمال‌شده)
        $slug = self::ALIAS[$cat] ?? self::ALIAS[mb_strtolower($n)] ?? self::ALIAS[$n] ?? null;
        if ($slug && isset($bySlug[$slug])) {
            return $bySlug[$slug];
        }

        // ۳) تطبیقِ هستهٔ کلمه: اولین واژهٔ دسته (قبل از «(» یا «-») داخلِ نامِ دستگاه
        //    باشد. مثلاً «تلویزیون (LCD / LED)» → دستگاهی که نامش «تلویزیون» دارد.
        $core = $this->coreWord($cat);
        if (mb_strlen($core) >= 3) {
            foreach ($this->deviceList as $d) {
                $dn = $this->norm($d->name);
                if ($dn === $core || str_contains($dn, $core) || str_contains($core, $dn)) {
                    return $d;
                }
            }
        }

        return null;
    }

    private function coreWord(string $cat): string
    {
        $head = (string) (preg_split('/[\(\-]/u', $cat)[0] ?? $cat);
        $head = $this->norm($head);
        $parts = preg_split('/\s+/u', $head) ?: [];

        return (string) ($parts[0] ?? '');
    }

    /** @return array{0:?int,1:bool} [price, is_free] */
    private function parsePrice(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, false];
        }
        if (str_contains($raw, 'رایگان')) {
            return [null, true];
        }
        // ارقامِ فارسی → لاتین، سپس فقط ارقام
        $digits = preg_replace('/\D+/u', '', $this->toLatinDigits($raw));

        return [$digits === '' ? null : (int) $digits, false];
    }

    private function toLatinDigits(string $s): string
    {
        return strtr($s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    private function norm(string $s): string
    {
        $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', 'ـ' => '']);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim((string) $s);
    }
}
