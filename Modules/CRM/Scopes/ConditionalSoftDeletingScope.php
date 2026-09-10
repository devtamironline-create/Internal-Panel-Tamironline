<?php

namespace Modules\CRM\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\CRM\Support\SoftDeleteColumnCache;

/**
 * مثلِ SoftDeletingScope استاندارد، اما شرطِ whereNull(deleted_at) را فقط وقتی
 * اعمال می‌کند که ستونِ deleted_at واقعاً در جدول باشد.
 *
 * در production همیشه هست → رفتارْ مو‌به‌مو همان soft-delete معمول. فقط در
 * تست‌هایی که جدول را دستی و بدونِ deleted_at می‌سازند، اسکوپ بی‌اثر می‌شود تا
 * آن تست‌ها (که به soft-delete کاری ندارند) نشکنند. extendها (withTrashed/
 * onlyTrashed/restore) از کلاسِ پدر می‌آیند.
 */
class ConditionalSoftDeletingScope extends SoftDeletingScope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (SoftDeleteColumnCache::has($model->getTable())) {
            $builder->whereNull($model->getQualifiedDeletedAtColumn());
        }
    }
}
