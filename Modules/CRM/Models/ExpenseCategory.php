<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دسته‌بندی هزینه — درخت دو سطحی: دسته اصلی (parent_id=null) و
 * زیر دسته. هزینه فقط به زیر دسته متصل می‌شود.
 */
class ExpenseCategory extends Model
{
    protected $table = 'crm_expense_categories';

    protected $fillable = ['parent_id', 'name', 'sort_order'];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function scopeRoots($q)
    {
        return $q->whereNull('parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
