<?php

namespace Modules\Site\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaVariant extends Model
{
    protected $table = 'site_media_variants';

    protected $fillable = ['media_id', 'variant', 'path', 'width', 'height', 'size_bytes', 'mime'];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
