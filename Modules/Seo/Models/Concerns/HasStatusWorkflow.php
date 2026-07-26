<?php

namespace Modules\Seo\Models\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * اعتبارسنجی گذار وضعیت — مدل باید ثابت STATUS_TRANSITIONS (نقشهٔ from→[to])
 * داشته باشد. پرش نامعتبر ValidationException می‌دهد تا UI پیام فارسی نشان دهد.
 */
trait HasStatusWorkflow
{
    /**
     * @throws ValidationException وقتی گذار مجاز نیست
     */
    public function transitionStatusTo(string $newStatus, string $column = 'status'): void
    {
        $current = (string) $this->getAttribute($column);
        $map = static::STATUS_TRANSITIONS;

        if ($newStatus === $current) {
            return;
        }

        if (! array_key_exists($newStatus, $map) && ! in_array($newStatus, array_merge(...array_values($map)), true)) {
            throw ValidationException::withMessages([
                $column => "وضعیت «{$newStatus}» معتبر نیست.",
            ]);
        }

        $allowed = $map[$current] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                $column => 'این تغییر وضعیت مجاز نیست (از «'.$current.'» به «'.$newStatus.'»).',
            ]);
        }

        $this->setAttribute($column, $newStatus);
    }

    /** وضعیت‌های مجاز بعدی از وضعیت فعلی — برای ساختن دکمه‌های UI. */
    public function allowedNextStatuses(string $column = 'status'): array
    {
        return static::STATUS_TRANSITIONS[(string) $this->getAttribute($column)] ?? [];
    }
}
