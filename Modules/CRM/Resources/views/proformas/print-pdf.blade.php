@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');

    $order = $proforma->order;
    $custName   = $proforma->customer_name ?: ($order?->customer_name ?: '—');
    $custMobile = $proforma->customer_mobile ?: ($order?->customer_mobile ?: '');
    $device = trim(($proforma->device_name ?? '').' '.($proforma->brand_name ?? ''));
    $items = is_array($proforma->items) ? $proforma->items : [];

    $expiresAt = $proforma->expiresAt();
    $isExpired = $proforma->isExpired();

    $fa = fn ($s) => strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    $money = fn ($n) => $fa(number_format((int) $n));
    $issued = $fa(\Morilog\Jalali\Jalalian::fromCarbon($proforma->created_at)->format('Y/m/d H:i'));
    $expiryStr = $expiresAt ? $fa(\Morilog\Jalali\Jalalian::fromCarbon($expiresAt)->format('Y/m/d H:i')) : '—';
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پیش‌فاکتور - {{ $proforma->proforma_code }}</title>
    <style>
        * { font-family: vazir, sans-serif; }
        body { color: #1b2340; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; }
        .frame { padding: 2mm 1mm 4mm; }
        .top td { vertical-align: middle; padding-bottom: 6px; }
        .pill { border: 1.2pt solid #1e40af; border-radius: 16px; }
        .pill td { padding: 4px 12px; font-size: 9pt; white-space: nowrap; }
        .pill .k { font-weight: bold; color: #1b2340; }
        .bname { font-size: 16pt; font-weight: bold; color: #1e40af; }
        .bsub  { font-size: 8pt; color: #3a4160; }
        .title { font-size: 20pt; font-weight: bold; color: #1b2340; text-align: center; white-space: nowrap; }
        .hint { text-align: center; font-size: 8.5pt; color: #64748b; padding: 4px 0 2px; }
        .expired { text-align: center; font-size: 10pt; font-weight: bold; color: #b91c1c; padding: 4px 0; }
        .info { border: 1.2pt solid #9aa3c4; border-radius: 5px; margin-top: 6px; }
        .info td { border-bottom: 1pt solid #c5cbe0; padding: 9px 12px; font-size: 9.5pt; }
        .info tr:last-child td { border-bottom: 0; }
        .info .k { color: #3a4160; font-weight: 600; }
        .info .v { font-weight: bold; color: #1b2340; }
        .svc { margin-top: 14px; }
        .svc-bar { background: #dfe3f3; text-align: center; font-weight: bold; font-size: 10.5pt; color: #1b2340; padding: 8px; border: 1.2pt solid #9aa3c4; border-bottom: 0; }
        .items th, .items td { border: 0.8pt solid #9aa3c4; padding: 9px 8px; font-size: 9pt; text-align: center; }
        .items th { background: #eef0f9; font-weight: bold; }
        .items td.desc { text-align: right; line-height: 1.7; }
        .items .sum td { background: #f7f8fc; }
        .items .total td { background: #eef0f9; font-weight: bold; font-size: 10.5pt; }
        .desc { border: 1pt solid #9aa3c4; border-top: 0; padding: 10px 12px; font-size: 9pt; line-height: 1.8; }
        .foot { margin-top: 16px; text-align: center; font-size: 8.5pt; color: #64748b; }
    </style>
</head>
<body>
<div class="frame">
    {{-- هدر --}}
    <table class="top">
        <tr>
            <td style="width: 45%; text-align: right;">
                <table class="pill" style="width: auto;">
                    <tr><td class="k">شماره پیش‌فاکتور</td><td dir="ltr">{{ $proforma->proforma_code }}</td></tr>
                    <tr><td class="k">تاریخ صدور</td><td dir="ltr">{{ $issued }}</td></tr>
                    <tr><td class="k">اعتبار تا</td><td dir="ltr">{{ $expiryStr }}</td></tr>
                </table>
            </td>
            <td style="width: 55%; text-align: left;">
                <div class="bname">{{ $providerName }}</div>
                <div class="bsub">{{ $providerTagline }}</div>
            </td>
        </tr>
    </table>

    <div class="title">پیش‌فاکتور خدمات</div>
    <div class="hint">این سند «برآوردِ قیمت» و «غیرقابل استناد» است و فاکتورِ نهایی نیست. اعتبارِ آن ۲۴ ساعت از زمانِ صدور است.</div>
    @if($isExpired)<div class="expired">این پیش‌فاکتور منقضی شده است.</div>@endif

    {{-- ارائه‌دهنده / مشتری --}}
    <table class="info">
        <tr>
            <td style="width: 50%;"><span class="k">ارائه‌دهنده: </span><span class="v">{{ $providerName }}</span></td>
            <td style="width: 50%;"><span class="k">تلفن: </span><span class="v" dir="ltr">{{ $providerPhone }}</span></td>
        </tr>
        <tr>
            <td><span class="k">مشتری: </span><span class="v">{{ $custName }}</span></td>
            <td><span class="k">موبایل: </span><span class="v" dir="ltr">{{ $custMobile }}</span></td>
        </tr>
        @if($device !== '' || $providerAddress)
        <tr>
            <td>@if($device !== '')<span class="k">دستگاه: </span><span class="v">{{ $device }}</span>@endif</td>
            <td>@if($providerAddress)<span class="k">آدرس: </span><span class="v">{{ $providerAddress }}</span>@endif</td>
        </tr>
        @endif
    </table>

    {{-- اقلام --}}
    <div class="svc">
        <div class="svc-bar">مشخصات کالا یا خدمات مورد برآورد</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 40px;">ردیف</th>
                    <th>شرح</th>
                    <th style="width: 60px;">تعداد</th>
                    <th style="width: 110px;">قیمت واحد</th>
                    <th style="width: 120px;">مبلغ کل</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr>
                        <td>{{ $fa($i + 1) }}</td>
                        <td class="desc">{{ $item['title'] ?? '' }}</td>
                        <td>{{ $fa((int) ($item['quantity'] ?? 1)) }}</td>
                        <td dir="ltr">{{ $money($item['unit_price'] ?? 0) }}</td>
                        <td dir="ltr">{{ $money($item['total'] ?? 0) }}</td>
                    </tr>
                @endforeach
                <tr class="sum"><td colspan="4">جمع اقلام</td><td dir="ltr">{{ $money($proforma->subtotal) }}</td></tr>
                @if($proforma->discount > 0)
                    <tr class="sum"><td colspan="4">تخفیف</td><td dir="ltr">{{ $money($proforma->discount) }}−</td></tr>
                @endif
                <tr class="total"><td colspan="4">مبلغ نهایی (تومان)</td><td dir="ltr">{{ $money($proforma->total) }}</td></tr>
            </tbody>
        </table>
        @if($proforma->description)
            <div class="desc"><b>توضیحات:</b> {{ $proforma->description }}</div>
        @endif
    </div>

    <div class="foot">
        وضعیت: {{ $isExpired ? 'منقضی' : $proforma->statusLabel() }}
        @if($providerPhone) — تماس: <span dir="ltr">{{ $providerPhone }}</span>@endif
    </div>
</div>
</body>
</html>
