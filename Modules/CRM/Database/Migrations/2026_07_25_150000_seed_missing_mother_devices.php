<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;

/**
 * ساختِ ۵ صفحهٔ مادرِ گمشدهٔ کاتالوگ (نامهٔ نقشهٔ ایندکس — بند ۳):
 * iron / teamaker / cleaning-steamer / coffeemaker / evaporative-cooler.
 *
 * این دستگاه‌ها در کوئری‌های عمومی ترافیک دارند اما /services/{slug} برایشان
 * ۴۰۴ بود؛ تا ساخته نشوند، ۳۰۱ کردنِ مسیرهای قدیمیِ /repair/* به آن‌ها ممنوع است.
 * محتوا (hero/description/FAQ/ایرادها/تعرفه) واقعی و قابلِ ویرایش از پنل است.
 * idempotent: اگر slug از قبل وجود داشته باشد دست نمی‌زند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_devices')) {
            return;
        }

        foreach (\Modules\CRM\Support\MotherDevicesSeedData::all() as $data) {
            $prices = $data['service_prices'];
            unset($data['service_prices']);

            if (Device::query()->where('slug', $data['slug'])->exists()) {
                continue;
            }

            $device = Device::query()->create($data);

            if (Schema::hasTable('crm_device_service_prices')) {
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
        if (! Schema::hasTable('crm_devices')) {
            return;
        }

        // فقط اگر سفارشی به دستگاه وصل نشده حذف می‌کنیم (ایمنی داده).
        foreach (['iron', 'teamaker', 'cleaning-steamer', 'coffeemaker', 'evaporative-cooler'] as $slug) {
            $device = Device::query()->where('slug', $slug)->first();
            if (! $device) {
                continue;
            }
            $hasOrders = Schema::hasTable('crm_order_items')
                && \Illuminate\Support\Facades\DB::table('crm_order_items')->where('device_id', $device->id)->exists();
            if (! $hasOrders) {
                DeviceServicePrice::query()->where('device_id', $device->id)->delete();
                $device->delete();
            }
        }
    }
};
