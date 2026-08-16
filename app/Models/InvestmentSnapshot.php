<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ارزشِ روزانهٔ سبدِ صندوق سرمایه — یک ردیف برای هر روز (snap_date یکتا).
 * چون قیمتِ تاریخی از نوسان در دسترس نیست، این جدول تنها منبعِ روندِ
 * ارزش است؛ ردیفِ روزهای گذشته بازنویسی نمی‌شود (فقط ردیفِ امروز upsert).
 */
class InvestmentSnapshot extends Model
{
    protected $fillable = ['snap_date', 'total_value', 'total_cost', 'breakdown'];

    protected $casts = [
        'snap_date' => 'date',
        'total_value' => 'integer',
        'total_cost' => 'integer',
        'breakdown' => 'array',
    ];
}
