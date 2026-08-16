<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک خرید در صندوق سرمایه — صندوق مشترک است؛ created_by فقط ردِ audit است.
 */
class InvestmentAsset extends Model
{
    /** منبعِ سرمایهٔ خرید — کسب‌وکاری که پول از آن برداشته شده. */
    public const SOURCES = [
        'tamir' => 'تعمیر',
        'ganje' => 'گنجه',
    ];

    protected $fillable = ['asset', 'amount', 'buy_unit_price', 'bought_at', 'source', 'note', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:8',
        'buy_unit_price' => 'integer',
        'bought_at' => 'date',
    ];

    /** مشخصات این دارایی از config — null اگر کلید از config حذف شده باشد. */
    public function meta(): ?array
    {
        return config('investment.assets.'.$this->asset);
    }

    /** مبلغ خرید این ردیف به تومان. */
    public function cost(): int
    {
        return (int) round((float) $this->amount * $this->buy_unit_price);
    }

    /** برچسبِ فارسیِ منبعِ سرمایه — «نامشخص» برای خریدهای قدیمیِ بدونِ منبع. */
    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? 'نامشخص';
    }
}
