<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;
use Modules\CRM\Support\MotherDevicesSeedData;

/**
 * تکمیلِ ۵ صفحهٔ مادرِ نامهٔ نقشهٔ ایندکس برای ردیف‌های ازقبل‌موجود.
 *
 * migration قبلی (seed_missing_mother_devices) عمداً ردیف‌های موجود را رد می‌کرد؛
 * اما در production بعضی از این slugها از قبل وجود داشتند (مثلاً iron که صفحات
 * ترکیبیِ فعال داشت) — غیرفعال یا با محتوای خالی. نتیجه: صفحهٔ مادر یا در
 * سایت‌مپ نبود یا خالی رندر می‌شد.
 *
 * این migration برای هر ۵ slug:
 *   - is_active را روشن می‌کند (خواستهٔ صریح نامه: صفحهٔ مادرِ ۲۰۰ با محتوا)
 *   - «فقط فیلدهای خالی» را با محتوای MotherDevicesSeedData پر می‌کند —
 *     هر چیزی که ادمین قبلاً نوشته دست‌نخورده می‌ماند
 *   - اگر دستگاه هیچ ردیف تعرفه ندارد، ردیف‌های پیش‌فرض را می‌سازد
 * idempotent: اجرای دوباره هیچ تغییری نمی‌دهد.
 */
return new class extends Migration
{
    /** فیلدهای متنی که در صورتِ خالی‌بودن پر می‌شوند. */
    private const TEXT_FIELDS = [
        'short_name', 'service_name', 'technician_name', 'eyebrow', 'subtitle',
        'caption', 'description', 'warranty_text', 'support_info',
    ];

    /** فیلدهای JSON که در صورتِ خالی‌بودن پر می‌شوند. */
    private const JSON_FIELDS = ['issues', 'faq'];

    public function up(): void
    {
        if (! Schema::hasTable('crm_devices')) {
            return;
        }

        foreach (MotherDevicesSeedData::all() as $data) {
            $prices = $data['service_prices'];
            unset($data['service_prices']);

            $device = Device::query()->where('slug', $data['slug'])->first();
            if (! $device) {
                continue; // ساختِ ردیفِ جدید کارِ migration قبلی است.
            }

            foreach (self::TEXT_FIELDS as $field) {
                if (trim((string) $device->{$field}) === '' && filled($data[$field] ?? null)) {
                    $device->{$field} = $data[$field];
                }
            }
            foreach (self::JSON_FIELDS as $field) {
                $current = $device->{$field};
                if ((! is_array($current) || $current === []) && ! empty($data[$field])) {
                    $device->{$field} = $data[$field];
                }
            }

            $device->is_active = true;
            $device->save();

            if (Schema::hasTable('crm_device_service_prices')
                && ! DeviceServicePrice::query()->where('device_id', $device->id)->exists()) {
                foreach ($prices as $i => $row) {
                    DeviceServicePrice::query()->create($row + [
                        'device_id' => $device->id,
                        'sort_order' => $i,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // برگرداندنِ «فقط فیلدهای خالیِ پرشده» قابلِ تفکیک از ویرایش‌های بعدیِ
        // ادمین نیست؛ down عمداً no-op است (دادهٔ محتوایی از پنل قابل ویرایش است).
    }
};
