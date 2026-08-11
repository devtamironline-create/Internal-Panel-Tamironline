<?php

/*
|--------------------------------------------------------------------------
| صندوق سرمایه
|--------------------------------------------------------------------------
| قیمت لحظه‌ای از وب‌سرویس نوسان (api.navasan.tech) خوانده می‌شود.
| کلید API فقط در .env سرور: NAVASAN_API_KEY
|
| «item» نامِ آیتم در وب‌سرویس نوسان است — اگر نام آیتمی در پنل نوسان
| فرق داشت، فقط همین‌جا اصلاح شود؛ به کد دست نزنید.
*/

return [
    'navasan' => [
        'base_url' => env('NAVASAN_BASE_URL', 'http://api.navasan.tech'),
        'api_key' => env('NAVASAN_API_KEY', ''),
        'cache_seconds' => (int) env('NAVASAN_CACHE_SECONDS', 300),
    ],

    'assets' => [
        'gold_18k' => ['label' => 'طلای ۱۸ عیار', 'unit' => 'گرم', 'item' => '18ayar', 'step' => '0.001'],
        'sekkeh_emami' => ['label' => 'سکهٔ امامی', 'unit' => 'عدد', 'item' => 'sekkeh', 'step' => '1'],
        'nim_sekkeh' => ['label' => 'نیم‌سکه', 'unit' => 'عدد', 'item' => 'nim', 'step' => '1'],
        'rob_sekkeh' => ['label' => 'ربع‌سکه', 'unit' => 'عدد', 'item' => 'rob', 'step' => '1'],
        'usd' => ['label' => 'دلار', 'unit' => 'دلار', 'item' => 'usd_sell', 'step' => '0.01'],
        'usdt' => ['label' => 'تتر', 'unit' => 'تتر', 'item' => 'usdt', 'step' => '0.01'],
        'btc' => ['label' => 'بیت‌کوین', 'unit' => 'BTC', 'item' => 'btc', 'step' => '0.00000001'],
    ],
];
