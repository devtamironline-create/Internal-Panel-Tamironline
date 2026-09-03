<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * انتقالِ دیتای فعلی به «منبعِ واحدِ نوع خدمت» (صفحهٔ service-matrix).
 *
 * چرا لازم است: پخشِ خودکارِ سفارش و پیشنهادِ تکنسین، تکنسینِ بدونِ
 * service_types را با دلیلِ «no_service_types» کنار می‌گذارند. اگر این فیلد
 * برای تکنسین‌های فعلی خالی باشد، توزیع می‌شکند. پس هر تکنسین/دستگاهی که
 * نوع خدمتش تعیین‌نشده (null/خالی) است را به «همهٔ نوع‌ها» مقداردهی می‌کنیم
 * تا رفتار فعلی (همه‌کاره) حفظ و توزیع سالم بماند؛ ادمین بعداً از همان صفحه
 * محدود می‌کند.
 *
 * افزایشی و بی‌خطر: فقط ردیف‌های null/خالی پر می‌شوند؛ تنظیمِ صریحِ قبلی
 * دست‌نخورده می‌ماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $slugs = $this->activeSlugs();
        $json = json_encode($slugs, JSON_UNESCAPED_UNICODE);

        $this->fillEmpty('crm_devices', 'order_types', $json);
        $this->fillEmpty('crm_technicians', 'service_types', $json);

        // کشِ کاتالوگِ اپ باطل شود تا نوع‌ها فوراً به‌روز شوند.
        if (class_exists(\Modules\CustomerApp\Support\AppCacheVersion::class)) {
            try {
                \Modules\CustomerApp\Support\AppCacheVersion::bump();
            } catch (\Throwable $e) {
                // بی‌اهمیت.
            }
        }
    }

    /** @return array<int, string> */
    private function activeSlugs(): array
    {
        try {
            if (Schema::hasTable('crm_service_types')) {
                $rows = DB::table('crm_service_types')->where('is_active', true)
                    ->orderBy('sort_order')->pluck('slug')->all();
                if ($rows !== []) {
                    return $rows;
                }
            }
        } catch (\Throwable $e) {
            // fallback پایین.
        }

        return ['repair', 'service', 'install'];
    }

    private function fillEmpty(string $table, string $column, string $json): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select('id', $column)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table, $column, $json) {
                foreach ($rows as $row) {
                    $val = $row->{$column};
                    $decoded = is_string($val) && $val !== '' ? json_decode($val, true) : $val;
                    $isEmpty = ! is_array($decoded) || $decoded === [];
                    if ($isEmpty) {
                        DB::table($table)->where('id', $row->id)->update([$column => $json]);
                    }
                }
            });
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست — نمی‌دانیم کدام‌ها قبلاً خالی بوده‌اند.
    }
};
