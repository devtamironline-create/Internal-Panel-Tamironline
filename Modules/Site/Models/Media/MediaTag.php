<?php

namespace Modules\Site\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaTag extends Model
{
    protected $table = 'site_media_tags';

    protected $fillable = ['slug', 'name', 'color'];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'site_media_tag_assignments', 'tag_id', 'media_id');
    }
}
