<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class HappyCallQuestion extends Model
{
    protected $table = 'crm_happycall_questions';

    protected $fillable = [
        'audience',
        'question_text',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeForAudience($query, string $audience)
    {
        return $query->where('audience', $audience);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function audienceLabel(): string
    {
        return $this->audience === 'technician' ? 'تکنسین' : 'مشتری';
    }
}
