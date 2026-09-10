<?php

namespace Modules\CRM\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Scopes\ConditionalSoftDeletingScope;
use Modules\CRM\Support\SoftDeleteColumnCache;

/**
 * مثلِ SoftDeletes، اما اسکوپِ حذف را فقط وقتی اعمال می‌کند که ستونِ deleted_at
 * در جدول باشد (نگاه کنید به ConditionalSoftDeletingScope). همهٔ امکاناتِ
 * SoftDeletes (trashed/withTrashed/restore/forceDelete) حفظ می‌شود.
 *
 * علتِ وجود: تست‌ها جدول‌ها را دستی و بی‌deleted_at می‌سازند؛ بدونِ این شرط،
 * global scopeِ استاندارد همهٔ آن تست‌ها را می‌شکست. در production رفتار دقیقاً
 * همان soft-delete معمول است.
 *
 * متدِ bootSoftDeletes این‌جا بازتعریف شده و نسخهٔ تراِیتِ SoftDeletes را
 * override می‌کند (قانونِ «متدِ تراِیتِ بیرونی بر تراِیتِ درونی مقدم است»)، تا
 * اسکوپِ استانداردِ همیشگی ثبت نشود و جایش اسکوپِ شرطی بنشیند.
 */
trait SoftDeletesIfSupported
{
    use SoftDeletes;

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new ConditionalSoftDeletingScope);
    }

    /**
     * هنگامِ حذف: اگر ستونِ deleted_at نبود (فقط در تست‌های قدیمی)، حذفِ
     * واقعی انجام شود تا رفتار نشکند؛ در production همیشه soft-delete است.
     */
    protected function performDeleteOnModel()
    {
        if (! $this->forceDeleting && ! SoftDeleteColumnCache::has($this->getTable())) {
            $this->exists = false;

            return $this->setKeysForSaveQuery($this->newModelQuery())->forceDelete();
        }

        if ($this->forceDeleting) {
            return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function () {
                $this->exists = false;
            });
        }

        return $this->runSoftDelete();
    }
}
