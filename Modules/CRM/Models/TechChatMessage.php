<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * پیام چت بین تکنسین و اپراتورِ assign‌شده.
 *
 * @property string $sender_type 'tech' | 'operator'
 */
class TechChatMessage extends Model
{
    protected $table = 'crm_tech_chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'technician_id', 'sender_type', 'sender_id', 'body', 'read_at', 'created_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public const SENDER_TECH = 'tech';
    public const SENDER_OPERATOR = 'operator';

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function senderOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function senderTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'sender_id');
    }

    public function isFromTech(): bool
    {
        return $this->sender_type === self::SENDER_TECH;
    }

    public function isFromOperator(): bool
    {
        return $this->sender_type === self::SENDER_OPERATOR;
    }
}
