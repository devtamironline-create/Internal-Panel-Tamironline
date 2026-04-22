<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $table = 'crm_sms_templates';

    protected $fillable = [
        'trigger_key',
        'title',
        'recipient',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function forTrigger(string $triggerKey): ?self
    {
        return static::where('trigger_key', $triggerKey)->where('is_active', true)->first();
    }

    public function render(array $vars): string
    {
        $body = $this->body;
        foreach ($vars as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return $body;
    }

    public function recipientLabel(): string
    {
        return $this->recipient === 'technician' ? 'تکنسین' : 'مشتری';
    }
}
