<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Faq extends Model
{
    use HasUlids;

    protected $table = 'faqs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'question',
        'answer',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'site_faq_taxonomies', 'faq_id', 'taxonomy_id')
            ->where('site_taxonomies.type', Taxonomy::TYPE_FAQ);
    }
}
