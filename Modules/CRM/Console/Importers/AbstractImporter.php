<?php

namespace Modules\CRM\Console\Importers;

use Illuminate\Support\Facades\DB;

/**
 * پایه importerهای CRM وردپرسی → Laravel.
 *
 * هر importer مسئول یک «section» (مثل customers) است و کار را به‌صورت
 * batch-based انجام می‌دهد تا UI بتواند با چند درخواست AJAX متوالی
 * پیشرفت را گزارش کند.
 */
abstract class AbstractImporter
{
    /** نام بخش (مثل customers). */
    abstract public function name(): string;

    /** برچسب فارسی برای نمایش در UI. */
    abstract public function label(): string;

    /** تعداد کل رکوردهای WP که قرار است پردازش شود. */
    abstract public function totalCount(): int;

    /**
     * پردازش یک دسته رکورد با offset و limit.
     *
     * @return array{processed:int, created:int, updated:int, skipped:int}
     */
    abstract public function processBatch(int $offset, int $limit): array;

    /** اتصال به دیتابیس قدیمی WP (read-only). */
    protected function legacy()
    {
        return DB::connection('legacy_wp');
    }

    /** اتصال به دیتابیس Laravel (مقصد). */
    protected function target()
    {
        return DB::connection(config('database.default'));
    }

    /** نام جدول WP با پیشوند پیکربندی‌شده (مثلاً wp_users). */
    protected function wpTable(string $name): string
    {
        $prefix = config('database.connections.legacy_wp.prefix', 'wp_');

        return $prefix . $name;
    }
}
