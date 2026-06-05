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
        'kavenegar_template',
        'token_vars',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_vars' => 'array',
    ];

    public static function forTrigger(string $triggerKey): ?self
    {
        return static::where('trigger_key', $triggerKey)->where('is_active', true)->first();
    }

    /**
     * رندر body با جایگذاری متغیرها {key} → value.
     * فقط برای حالت fallback (وقتی kavenegar_template ست نیست).
     */
    public function render(array $vars): string
    {
        $body = $this->body;
        foreach ($vars as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return $body;
    }

    /**
     * رندر مقادیر token1/2/3 از روی token_vars + متغیرها.
     * mapping: ['token' => '{customer_name}', 'token2' => '{order_code}', ...]
     * → ['token' => 'احمد', 'token2' => 'ORD-001', ...]
     */
    public function renderTokens(array $vars): array
    {
        $tokens = ['token' => '', 'token2' => '', 'token3' => ''];
        $mapping = $this->token_vars ?: [];

        foreach ($tokens as $key => $_) {
            $expr = (string) ($mapping[$key] ?? '');
            if ($expr === '') continue;
            // جایگذاری همه {var} ها
            foreach ($vars as $varKey => $varValue) {
                $expr = str_replace('{' . $varKey . '}', (string) $varValue, $expr);
            }
            $tokens[$key] = $expr;
        }

        return $tokens;
    }

    public function recipientLabel(): string
    {
        return $this->recipient === 'technician' ? 'تکنسین' : 'مشتری';
    }
}
