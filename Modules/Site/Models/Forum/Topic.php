<?php

namespace Modules\Site\Models\Forum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * موضوعِ (تاکسونومیِ) انجمن — برای فیلترِ «جستجو بر اساس موضوع».
 * انتسابِ موضوع به سوال از پنل هنگامِ داوری انجام می‌شود (topic_id).
 */
class Topic extends Model
{
    protected $table = 'site_forum_topics';

    protected $fillable = [
        'slug', 'label', 'description', 'icon', 'tone', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'topic_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
