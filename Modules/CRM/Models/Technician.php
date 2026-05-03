<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تکنسین — هم‌سو با libs/technician.php در CRM وردپرسی.
 *
 * فیلدهای مرجع WP:
 *   first_name, firstname_tech, technician_id, national_code, mobile,
 *   phone, phone_force, address, description, percent, max_order,
 *   max_price, status, type_tech, cart_img, province, specialty,
 *   ready_for_delivery (در WP: ready_for_derliver), type_of_calc_tech,
 *   tech_per_of_all, img_personal (در WP: img_Personal)
 */
class Technician extends Model
{
    protected $table = 'crm_technicians';

    protected $fillable = [
        'wp_id',
        'user_id',

        // مشخصات
        'first_name',
        'firstname_tech',
        'technician_id',
        'national_code',
        'mobile',
        'phone',
        'phone_force',

        // آدرس
        'province',
        'address',

        // تخصص و توضیحات
        'specialty',
        'type_tech',
        'description',

        // تصاویر
        'img_personal',
        'cart_img',

        // قوانین مالی/کاری
        'percent',
        'tech_per_of_all',
        'max_order',
        'max_price',
        'type_of_calc_tech',

        // وضعیت
        'status',
        'ready_for_delivery',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'percent' => 'integer',
        'tech_per_of_all' => 'integer',
        'max_order' => 'integer',
        'max_price' => 'integer',
        'ready_for_delivery' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * مجموع سهم شرکت از فاکتورهای این تکنسین — بدهی تکنسین به شرکت.
     * این عدد مستقل از تراکنش‌های wallet ذخیره می‌شود (در crm_invoices).
     *
     * اگر مدل با ->withSum('invoices', 'company_share') بارگذاری شده
     * باشد (برای جلوگیری از N+1 در لیست‌ها)، از همان attribute استفاده
     * می‌کنیم؛ وگرنه یک query جداگانه می‌زنیم.
     */
    public function getInvoiceDebtAttribute(): int
    {
        if (array_key_exists('invoices_sum_company_share', $this->attributes)) {
            return (int) ($this->attributes['invoices_sum_company_share'] ?? 0);
        }
        return (int) $this->invoices()->sum('company_share');
    }

    /**
     * مانده نهایی کیف‌پول = wallet_balance - invoice_debt.
     *
     * wallet_balance running sum تراکنش‌های wallet (شارژ/جایزه/جریمه/...)
     * را نگه می‌دارد ولی سهم شرکت از فاکتورها به‌صورت رخداد wallet ثبت
     * نمی‌شود (هم‌ارز جریان WP). برای دیدن مانده واقعی، سهم شرکت کل
     * فاکتورها از wallet_balance کسر می‌شود.
     *
     * + = شرکت به تکنسین بدهکار / − = تکنسین به شرکت بدهکار.
     */
    public function getTrueBalanceAttribute(): int
    {
        return (int) $this->wallet_balance - $this->invoice_debt;
    }

    /**
     * نام نمایشی — اولویت با firstname_tech (نام تجاری/کامل WP)،
     * fallback به first_name + last_name (داده‌ی legacy لاراولی)،
     * در نهایت موبایل.
     */
    public function getFullNameAttribute(): string
    {
        $tech = trim((string) ($this->firstname_tech ?? ''));
        if ($tech !== '') {
            return $tech;
        }

        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $name !== '' ? $name : (string) $this->mobile;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'active')->where('ready_for_delivery', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('firstname_tech', 'like', "%{$term}%")
                ->orWhere('technician_id', 'like', "%{$term}%")
                ->orWhere('national_code', 'like', "%{$term}%")
                ->orWhere('specialty', 'like', "%{$term}%");
        });
    }
}
