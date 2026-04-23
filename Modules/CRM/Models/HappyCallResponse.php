<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HappyCallResponse extends Model
{
    protected $table = 'crm_happycall_responses';

    protected $fillable = [
        'order_id',
        'audience',
        'answers',
        'overall_rating',
        'note',
        'called_by',
        'called_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'overall_rating' => 'integer',
        'called_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by');
    }

    public function audienceLabel(): string
    {
        return $this->audience === 'technician' ? 'تکنسین' : 'مشتری';
    }

    public function ratingStars(): string
    {
        if (! $this->overall_rating) {
            return '—';
        }

        return str_repeat('★', $this->overall_rating) . str_repeat('☆', 5 - $this->overall_rating);
    }
}
