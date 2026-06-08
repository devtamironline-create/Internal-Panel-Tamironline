<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUlids;

    protected $table = 'contact_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'full_name',
        'mobile',
        'email',
        'topic',
        'message',
        'ip',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_NEW     = 'new';
    public const STATUS_SEEN    = 'seen';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_SPAM    = 'spam';
}
