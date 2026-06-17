@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');
    $logoPath        = CrmSetting::get('invoice_provider_logo_path', '');
    $stampPath       = CrmSetting::get('invoice_print_stamp_path', '');
    $logoUrl         = $logoPath  ? route('crm.invoice.asset', 'logo')  : null;
    $stampUrl        = $stampPath ? route('crm.invoice.asset', 'stamp') : null;

    $customer = $invoice->customer;
    $order = $invoice->order;

    $custName   = $order?->customer_name   ?: ($customer->display_name ?? '—');
    $custMobile = $order?->customer_mobile ?: ($customer->mobile ?? '');
    $custPhone  = $order?->customer_phone  ?: ($customer->phone ?? '');

    $cityName     = optional($order?->city)->name ?? '';
    $provinceName = optional($order?->province ?? null)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    // اطلاعات خدمت
    $serviceTypeMap = ['repair' => 'تعمیر', 'service' => 'سرویس'];
    $serviceType = $serviceTypeMap[$order?->order_type] ?? ($order?->order_type ?? '—');
    $deviceName  = optional($order?->device)->name ?: '—';
    $brandName   = optional($order?->brand)->name ?: '—';

    $customerDesc = trim((string) ($order->invoice_descripotion ?? ''));
    $grandTotal = (int) ($invoice->total_amount ?? 0);

    $rows = collect();
    if ($customerDesc !== '') {
        $rows->push(['title' => $customerDesc, 'total' => $grandTotal]);
    } elseif ($order && $order->items->isNotEmpty()) {
        foreach ($order->items as $item) {
            $rows->push(['title' => $item->title, 'total' => (int) $item->total_price]);
        }
    } elseif ($order) {
        $titles = is_array($order->piece_list) ? $order->piece_list : [];
        $sells  = is_array($order->customer_price_list) ? $order->customer_price_list : [];
        foreach ($titles as $i => $title) {
            if ($title === '' || $title === null) continue;
            $unit = (int) ($sells[$i] ?? 0);
            $rows->push(['title' => is_string($title) ? $title : (string) ($title['title'] ?? ''), 'total' => $unit]);
        }
    }
    if ($rows->isEmpty()) {
        $rows->push(['title' => 'انجام خدمات', 'total' => $grandTotal]);
    }
    if ($rows->sum('total') === 0 && $grandTotal > 0) {
        $combinedTitle = $rows->pluck('title')->filter()->implode('، ');
        $rows = collect([['title' => $combinedTitle !== '' ? $combinedTitle : 'انجام خدمات', 'total' => $grandTotal]]);
    }
    if ($grandTotal === 0 && $rows->sum('total') > 0) {
        $grandTotal = (int) $rows->sum('total');
    }

    $faNum = fn($n) => str_replace(
        ['0','1','2','3','4','5','6','7','8','9'],
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        number_format((int) $n)
    );

    $receiptUrl = route('crm.invoice.public', $invoice->invoice_code);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صورتحساب {{ $invoice->invoice_code }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 11px; color: #1f2937; }
        table { border-collapse: collapse; }
        .frame { border: 2px solid #1e3a5f; padding: 6px; }

        .top { width: 100%; }
        .top td { vertical-align: middle; }
        .brand-name { font-size: 18px; font-weight: bold; color: #1e3a5f; }
        .brand-sub { font-size: 10px; color: #64748b; }
        .title { font-size: 22px; font-weight: bold; color: #1e3a5f; text-align: center; }
        .metabox { border: 1px solid #1e3a5f; border-radius: 4px; font-size: 10px; margin-bottom: 4px; }
        .metabox td { padding: 4px 8px; }
        .metabox .ml { background: #eef2f7; font-weight: bold; border-left: 1px solid #1e3a5f; }

        /* بخش‌های اطلاعاتی با برچسب عمودی */
        .sec { width: 100%; border: 1px solid #1e3a5f; border-bottom: none; }
        .sec.last { border-bottom: 1px solid #1e3a5f; }
        .sec td { vertical-align: middle; }
        .vlabel { width: 30px; background: #1e3a5f; text-align: center; }
        .vtext { display: inline-block; color: #fff; font-weight: bold; font-size: 11px;
                 white-space: nowrap; transform: rotate(-90deg); }
        .fields { padding: 8px 10px; line-height: 1.9; font-size: 11px; }
        .fields .lbl { font-weight: bold; color: #1e3a5f; }

        .sec-head { background: #e8eef5; border: 1px solid #1e3a5f; border-bottom: none;
                    text-align: center; font-weight: bold; font-size: 12px; padding: 6px; }
        .items { width: 100%; border: 1px solid #1e3a5f; font-size: 11px; }
        .items th, .items td { border: 1px solid #1e3a5f; padding: 8px 6px; text-align: center; }
        .items th { background: #eef2f7; font-weight: bold; }
        .items td.desc { text-align: right; }
        .items tr.total td { background: #eef2f7; font-weight: bold; }

        .bottom { width: 100%; margin-top: 10px; }
        .bottom td { vertical-align: top; }
        .qrbox { border: 1px solid #1e3a5f; padding: 6px; text-align: center; width: 120px; }
        .qrbox .qrlabel { font-size: 9px; color: #1e3a5f; font-weight: bold; margin-bottom: 4px; }
        .qrbox .qrcode { font-size: 8px; color: #64748b; margin-top: 2px; }
        .stamp { width: 130px; }
    </style>
</head>
<body>
<div class="frame">

    {{-- سربرگ: لوگو راست، عنوان وسط، شماره/تاریخ چپ --}}
    <table class="top">
        <tr>
            <td style="width: 32%; text-align: right;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="height: 52px;" alt="">
                @else
                    <span class="brand-name">{{ $providerName }}</span><br>
                    <span class="brand-sub">{{ $providerTagline }}</span>
                @endif
            </td>
            <td style="width: 36%;"><div class="title">صورتحساب خدمات</div></td>
            <td style="width: 32%; text-align: left;">
                <table class="metabox" style="margin-right: auto;">
                    <tr><td class="ml">شماره فاکتور</td><td dir="ltr">{{ $invoice->invoice_code }}</td></tr>
                </table>
                <table class="metabox" style="margin-right: auto;">
                    <tr><td class="ml">تاریخ</td><td dir="ltr">@jdate($invoice->issued_at ?? $invoice->created_at)</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 6px;"></div>

    {{-- ارائه‌دهنده --}}
    <table class="sec">
        <tr>
            <td class="vlabel"><span class="vtext">ارائه‌دهنده</span></td>
            <td class="fields">
                <span class="lbl">نام:</span> {{ $providerName }} &nbsp;&nbsp;
                <span class="lbl">شماره تلفن:</span> <span dir="ltr">{{ $providerPhone }}</span> &nbsp;&nbsp;
                <span class="lbl">آدرس:</span> {{ $providerAddress }} &nbsp;&nbsp;
                <span class="lbl">کد پستی:</span> <span dir="ltr">{{ $providerPostal }}</span>
            </td>
        </tr>
    </table>

    {{-- مشتری --}}
    <table class="sec">
        <tr>
            <td class="vlabel"><span class="vtext">مشتری</span></td>
            <td class="fields">
                <span class="lbl">نام:</span> {{ $custName }} &nbsp;&nbsp;
                <span class="lbl">شماره تلفن:</span> <span dir="ltr">{{ $custMobile ?: $custPhone }}</span><br>
                <span class="lbl">آدرس:</span> {{ $custAddr !== '' ? $custAddr : '—' }}
            </td>
        </tr>
    </table>

    {{-- اطلاعات خدمت --}}
    <table class="sec last">
        <tr>
            <td class="vlabel"><span class="vtext">اطلاعات خدمت</span></td>
            <td class="fields">
                <span class="lbl">نوع خدمت:</span> {{ $serviceType }} &nbsp;&nbsp;
                <span class="lbl">دستگاه:</span> {{ $deviceName }} &nbsp;&nbsp;
                <span class="lbl">برند:</span> {{ $brandName }}
            </td>
        </tr>
    </table>

    <div style="height: 8px;"></div>

    {{-- شرح خدمات --}}
    <div class="sec-head">شرح خدمات صورت گرفته</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 42px;">ردیف</th>
                <th>شرح کالا/خدمت</th>
                <th style="width: 160px;">مبلغ کل (تومان)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $faNum($i + 1) }}</td>
                    <td class="desc">{{ $row['title'] }}</td>
                    <td>{{ $faNum($row['total']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">جمع کل</td>
                <td>{{ $faNum($grandTotal) }} تومان</td>
            </tr>
        </tbody>
    </table>

    {{-- پاورقی: QR راست، مهر چپ --}}
    <table class="bottom">
        <tr>
            <td style="width: 140px; text-align: right;">
                <div class="qrbox">
                    <div class="qrlabel">اعتبارسنجی QR-CODE</div>
                    @if(! empty($qrDataUri))
                        <img src="{{ $qrDataUri }}" style="width: 96px; height: 96px;" alt="QR">
                    @endif
                    <div class="qrcode" dir="ltr">{{ $invoice->invoice_code }}</div>
                </div>
            </td>
            <td></td>
            <td style="width: 150px; text-align: left; vertical-align: bottom;">
                @if($stampUrl)
                    <img src="{{ $stampUrl }}" class="stamp" alt="">
                @else
                    <div style="font-size: 10px; color: #1e3a5f; font-weight: bold;">{{ $providerName }}</div>
                    <div style="font-size: 9px; color: #64748b;">{{ $providerTagline }}</div>
                @endif
            </td>
        </tr>
    </table>

</div>
</body>
</html>
