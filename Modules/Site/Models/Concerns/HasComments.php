<?php

namespace Modules\Site\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Site\Models\Comment;

/**
 * trait برای هر مدلی که قرار است کامنت بپذیرد.
 *
 * استفاده: روی مدل Article (و آینده Device/Brand) فقط `use HasComments;`
 *
 * @method MorphMany morphMany(string $related, string $name, ...$rest)
 */
trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function approvedComments(): MorphMany
    {
        return $this->comments()->approved();
    }
}
