<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک خرید در صندوق سرمایه — صندوق مشترک است؛ created_by فقط ردِ audit است.
 */
class InvestmentAsset extends Model
{
    protected $fillable = ['asset', 'amount', 'buy_unit_price', 'bought_at', 'note', 'created_by'];

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
}
