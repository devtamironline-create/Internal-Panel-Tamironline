<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExpenseTag extends Model
{
    protected $table = 'crm_expense_tags';

    protected $fillable = ['name'];

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'crm_expense_tag', 'tag_id', 'expense_id');
    }
}
