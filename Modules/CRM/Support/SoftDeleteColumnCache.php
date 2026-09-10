<?php

namespace Modules\CRM\Support;

use Illuminate\Support\Facades\Schema;

/**
 * کشِ «آیا جدولِ X ستونِ deleted_at دارد؟» — تا اسکوپِ soft-delete در هر
 * کوئری یک‌بار هزینهٔ Schema::hasColumn را ندهد.
 *
 * در production همیشه true است (مهاجرت ستون را ساخته). تنها کاربردِ واقعیِ
 * این کش، تست‌هایی‌اند که جدول‌ها را دستی و بدونِ deleted_at می‌سازند؛ آن‌جا
 * اسکوپ باید بی‌اثر شود تا تست‌های موجود نشکنند. کش در TestCase::setUp پاک
 * می‌شود تا به ترتیبِ اجرا وابسته نباشد.
 */
class SoftDeleteColumnCache
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function has(string $table): bool
    {
        if (! array_key_exists($table, self::$cache)) {
            try {
                self::$cache[$table] = Schema::hasColumn($table, 'deleted_at');
            } catch (\Throwable $e) {
                self::$cache[$table] = false;
            }
        }

        return self::$cache[$table];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
