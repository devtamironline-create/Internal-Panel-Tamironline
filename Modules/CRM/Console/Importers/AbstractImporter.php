<?php

namespace Modules\CRM\Console\Importers;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

/**
 * پایه importerهای CRM وردپرسی → Laravel.
 *
 * هر importer مسئول یک «section» (مثل customers) است و باید بتواند
 * به‌صورت chunk-based کار کند تا حافظه پر نشود.
 */
abstract class AbstractImporter
{
    public function __construct(protected OutputStyle $output)
    {
    }

    /** نام بخش (برای آرگومان --section). */
    abstract public function name(): string;

    /** اجرای ایمپورت با اندازه chunk داده‌شده. */
    abstract public function run(int $chunk): array;

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

    /** خواندن یک متا از usermeta (تک مقدار). */
    protected function userMeta(int $userId, string $key): ?string
    {
        $row = $this->legacy()
            ->table($this->wpTable('usermeta'))
            ->where('user_id', $userId)
            ->where('meta_key', $key)
            ->value('meta_value');

        return $row !== null ? (string) $row : null;
    }
}
