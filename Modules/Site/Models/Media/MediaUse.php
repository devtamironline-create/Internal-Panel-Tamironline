<?php

namespace Modules\Site\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * reverse-lookup polymorphic: «این Media کجاها استفاده شده».
 */
class MediaUse extends Model
{
    protected $table = 'site_media_uses';

    public $timestamps = false;

    protected $fillable = ['media_id', 'useable_type', 'useable_id', 'field', 'used_at'];

    protected $casts = ['used_at' => 'datetime'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function useable(): MorphTo
    {
        return $this->morphTo();
    }
}
