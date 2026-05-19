<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یادداشت اپراتور پنل لاراول روی یک سفارش — جدا از order_note_content
 * که از WP CRM می‌آید. هر یادداشت با اسم اپراتور و زمان ثبت می‌شود.
 */
class OrderAdminNote extends Model
{
    protected $table = 'crm_order_admin_notes';

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'user_id',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
