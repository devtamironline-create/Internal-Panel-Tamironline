<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌فاکتور {{ $proforma->proforma_code }}</title>
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; background: #f1f5f9; color: #1e293b; padding: 16px; line-height: 1.9; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); overflow: hidden; }
        .head { background: linear-gradient(135deg,#1a5276,#2874a6); color: #fff; padding: 20px; text-align: center; }
        .head h1 { font-size: 18px; }
        .head .sub { font-size: 12px; opacity: .85; margin-top: 4px; }
        .badge { display: inline-block; margin-top: 8px; font-size: 11px; background: rgba(255,255,255,.2); padding: 3px 10px; border-radius: 20px; }
        .meta { padding: 16px 20px; border-bottom: 1px solid #eef2f6; font-size: 13px; }
        .meta .row { display: flex; justify-content: space-between; padding: 3px 0; }
        .meta .row span:first-child { color: #64748b; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 9px 12px; text-align: right; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 600; }
        td.num, th.num { text-align: left; direction: ltr; }
        .totals { padding: 8px 20px 16px; font-size: 13px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .final { font-size: 17px; font-weight: 700; color: #1a5276; border-top: 2px solid #eef2f6; margin-top: 6px; padding-top: 10px; }
        .desc { padding: 0 20px 16px; font-size: 12px; color: #475569; }
        .foot { padding: 14px 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #eef2f6; }
        .note { max-width: 480px; margin: 12px auto 0; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>{{ $provider['name'] ?? 'تعمیرآنلاین' }}</h1>
            <div class="sub">{{ $provider['tagline'] ?? '' }}</div>
            <div class="badge">پیش‌فاکتور · {{ $proforma->proforma_code }}</div>
        </div>

        <div class="meta">
            @if($proforma->customer_name)<div class="row"><span>مشتری</span><span>{{ $proforma->customer_name }}</span></div>@endif
            @if($proforma->device_name)<div class="row"><span>دستگاه</span><span>{{ $proforma->device_name }} {{ $proforma->brand_name }}</span></div>@endif
            <div class="row"><span>وضعیت</span><span>{{ $proforma->statusLabel() }}</span></div>
            @if($proforma->valid_until)<div class="row"><span>معتبر تا</span><span dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($proforma->valid_until)->format('Y/m/d') }}</span></div>@endif
        </div>

        <table>
            <thead>
                <tr><th>شرح</th><th class="num">تعداد</th><th class="num">قیمت واحد</th><th class="num">جمع</th></tr>
            </thead>
            <tbody>
                @foreach($proforma->items as $item)
                <tr>
                    <td>{{ $item['title'] ?? '' }}</td>
                    <td class="num">{{ (int) ($item['quantity'] ?? 1) }}</td>
                    <td class="num">{{ number_format((int) ($item['unit_price'] ?? 0)) }}</td>
                    <td class="num">{{ number_format((int) ($item['total'] ?? 0)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>جمع اقلام</span><span dir="ltr">{{ number_format($proforma->subtotal) }}</span></div>
            @if($proforma->discount > 0)<div class="row"><span>تخفیف</span><span dir="ltr">{{ number_format($proforma->discount) }}−</span></div>@endif
            <div class="row final"><span>مبلغ نهایی (تومان)</span><span dir="ltr">{{ number_format($proforma->total) }}</span></div>
        </div>

        @if($proforma->description)
            <div class="desc">{{ $proforma->description }}</div>
        @endif

        <div class="foot">
            این سند یک «پیش‌فاکتور» (برآورد قیمت) است و فاکتور نهایی محسوب نمی‌شود.
            @if($provider['phone'] ?? null)<br>تماس: {{ $provider['phone'] }}@endif
        </div>
    </div>
    <p class="note">تعمیرآنلاین — {{ $proforma->proforma_code }}</p>
</body>
</html>
