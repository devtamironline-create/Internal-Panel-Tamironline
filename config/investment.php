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

    // «multiplier» = ضریبِ تبدیلِ مقدارِ خامِ نوسان به تومان. نوسان قیمتِ
    // سکه‌ها را به «هزار تومان» می‌دهد (مثلاً 189500 یعنی ۱۸۹٫۵ میلیون تومان)؛
    // اگر در پلن شما واحدِ آیتمی فرق داشت فقط همین ضریب را اصلاح کنید.
    'assets' => [
        'gold_18k' => ['label' => 'طلای ۱۸ عیار', 'unit' => 'گرم', 'item' => '18ayar', 'step' => '0.001', 'multiplier' => 1],
        'sekkeh_emami' => ['label' => 'سکهٔ امامی', 'unit' => 'عدد', 'item' => 'sekkeh', 'step' => '1', 'multiplier' => 1000],
        'nim_sekkeh' => ['label' => 'نیم‌سکه', 'unit' => 'عدد', 'item' => 'nim', 'step' => '1', 'multiplier' => 1000],
        'rob_sekkeh' => ['label' => 'ربع‌سکه', 'unit' => 'عدد', 'item' => 'rob', 'step' => '1', 'multiplier' => 1000],
        'usd' => ['label' => 'دلار', 'unit' => 'دلار', 'item' => 'usd_sell', 'step' => '0.01', 'multiplier' => 1],
        'usdt' => ['label' => 'تتر', 'unit' => 'تتر', 'item' => 'usdt', 'step' => '0.01', 'multiplier' => 1],
        'btc' => ['label' => 'بیت‌کوین', 'unit' => 'BTC', 'item' => 'btc', 'step' => '0.00000001', 'multiplier' => 1],
        'doge' => ['label' => 'دوج‌کوین', 'unit' => 'DOGE', 'item' => 'doge', 'step' => '0.00000001', 'multiplier' => 1],
        // پول نقد: قیمتِ واحد ثابت است (۱ تومان به ازای هر تومان) و از نوسان
        // خوانده نمی‌شود — «fixed_price» به‌جای «item». مقدار = مبلغ به تومان.
        'cash' => ['label' => 'پول نقد', 'unit' => 'تومان', 'item' => null, 'fixed_price' => 1, 'step' => '1', 'multiplier' => 1],
    ],
];
