<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک ردیف تاریخچهٔ تغییر URL یک موجودیت (برای rollback و audit).
 */
class SeoSlugHistory extends Model
{
    protected $table = 'seo_slug_histories';

    public const UPDATED_AT = null;   // فقط created_at

    protected $fillable = [
        'model_type', 'model_id', 'old_url', 'new_url', 'redirect_id', 'changed_by',
    ];

    public function redirect()
    {
        return $this->belongsTo(SeoRedirect::class, 'redirect_id');
    }
}
