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

    $fa = fn($s) => strtr((string) $s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    $money = fn($n) => $fa(number_format((int) $n));
    $dateStr = $fa(\Morilog\Jalali\Jalalian::fromDateTime($invoice->issued_at ?? $invoice->created_at)->format('Y/m/d'));
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صورتحساب {{ $invoice->invoice_code }}</title>
    <style>
        * { font-family: vazir, sans-serif; }
        body { color: #2a3441; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; }
        .navy { color: #14315a; }

        /* ── سربرگ ── */
        .head td { vertical-align: middle; padding-bottom: 14px; }
        .bname { font-size: 15pt; font-weight: bold; color: #14315a; }
        .bsub  { font-size: 8pt; color: #98a2b3; }
        .title { font-size: 18pt; font-weight: bold; color: #14315a; text-align: center; }
        .title-sub { text-align: center; font-size: 7.5pt; color: #98a2b3; letter-spacing: 1px; padding-top: 2px; }
        .meta { width: auto; }
        .meta td { padding: 4px 9px; font-size: 8.5pt; white-space: nowrap; border: 0.5pt solid #d7dde6; }
        .meta .k { color: #14315a; font-weight: bold; background: #f4f7fb; }

        /* ── بلوک اطلاعات با برچسب عمودی ── */
        .info { border: 0.5pt solid #cdd6e2; margin-top: 2px; }
        .info > tbody > tr > td { border-bottom: 0.5pt solid #e4e9f0; }
        .info > tbody > tr:last-child > td { border-bottom: 0; }
        .vlabel { width: 26px; background: #14315a; text-align: center; vertical-align: middle; }
        .vlabel .vt { display: block; color: #fff; font-weight: bold; font-size: 8.5pt;
                      white-space: nowrap; text-align: center; transform: rotate(-90deg); }
        .fld { padding: 0; }
        .fld td { border: 0; border-right: 0.5pt solid #eef1f6; padding: 9px 11px; font-size: 9pt; line-height: 1.7; }
        .fld td:first-child { border-right: 0; }
        .fld .k { color: #14315a; font-weight: bold; }

        /* ── جدول خدمات ── */
        .svc-h { color: #14315a; font-weight: bold; font-size: 10pt; padding: 12px 2px 6px; }
        .items { border: 0.5pt solid #cdd6e2; font-size: 9pt; }
        .items th { background: #14315a; color: #fff; font-weight: bold; padding: 8px 6px; }
        .items td { border-top: 0.5pt solid #e4e9f0; padding: 9px 7px; text-align: center; }
        .items td.desc { text-align: right; line-height: 1.8; }
        .items tr.sum td { background: #f4f7fb; font-weight: bold; color: #14315a; border-top: 0.8pt solid #14315a; }

        /* ── پاورقی ── */
        .foot { margin-top: 16px; }
        .foot td { vertical-align: bottom; }
        .qrbox { width: 112px; border: 0.5pt solid #cdd6e2; }
        .qrbox .ql { background: #f4f7fb; color: #14315a; font-weight: bold; font-size: 7pt; text-align: center; padding: 4px; }
        .qrbox .qi { text-align: center; padding: 7px 0 2px; }
        .qrbox .qc { text-align: center; font-size: 6.5pt; color: #98a2b3; padding-bottom: 5px; }
        .stamp { width: 118px; }
    </style>
</head>
<body>

    {{-- سربرگ: برند راست، عنوان وسط، شماره/تاریخ (کنار هم) چپ --}}
    <table class="head">
        <tr>
            <td style="width: 32%; text-align: right;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="height: 44px;" alt="">
                @else
                    <span class="bname">{{ $providerName }}</span><br>
                    <span class="bsub">{{ $providerTagline }}</span>
                @endif
            </td>
            <td style="width: 30%;">
                <div class="title">صورتحساب خدمات</div>
                <div class="title-sub">SERVICE&nbsp;INVOICE</div>
            </td>
            <td style="width: 38%;">
                <table class="meta" style="margin-right: auto;">
                    <tr>
                        <td class="k">شماره فاکتور</td>
                        <td dir="ltr">{{ $invoice->invoice_code }}</td>
                        <td class="k">تاریخ</td>
                        <td dir="ltr">{{ $dateStr }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- اطلاعات --}}
    <table class="info">
        <tr>
            <td class="vlabel"><div class="vt">ارائه‌دهنده</div></td>
            <td class="fld">
                <table><tr>
                    <td><span class="k">نام:</span> {{ $providerName }}</td>
                    <td><span class="k">تلفن:</span> <span dir="ltr">{{ $fa($providerPhone) ?: '—' }}</span></td>
                    <td><span class="k">آدرس:</span> {{ $providerAddress ?: '—' }}</td>
                    <td><span class="k">کد پستی:</span> <span dir="ltr">{{ $fa($providerPostal) ?: '—' }}</span></td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td class="vlabel"><div class="vt">مشتری</div></td>
            <td class="fld">
                <table><tr>
                    <td style="width: 34%;"><span class="k">نام:</span> {{ $custName }}</td>
                    <td style="width: 28%;"><span class="k">تلفن:</span> <span dir="ltr">{{ $fa($custMobile ?: $custPhone) ?: '—' }}</span></td>
                    <td><span class="k">آدرس:</span> {{ $custAddr !== '' ? $custAddr : '—' }}</td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td class="vlabel"><div class="vt">اطلاعات خدمت</div></td>
            <td class="fld">
                <table><tr>
                    <td style="width: 34%;"><span class="k">نوع خدمت:</span> {{ $serviceType }}</td>
                    <td style="width: 33%;"><span class="k">دستگاه:</span> {{ $deviceName }}</td>
                    <td><span class="k">برند:</span> {{ $brandName }}</td>
                </tr></table>
            </td>
        </tr>
    </table>

    {{-- خدمات --}}
    <div class="svc-h">شرح خدمات صورت‌گرفته</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 40px;">ردیف</th>
                <th>شرح کالا / خدمت</th>
                <th style="width: 150px;">مبلغ کل (تومان)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $fa($i + 1) }}</td>
                    <td class="desc">{{ $row['title'] }}</td>
                    <td>{{ $money($row['total']) }}</td>
                </tr>
            @endforeach
            <tr class="sum">
                <td colspan="2">جمع کل</td>
                <td>{{ $money($grandTotal) }} تومان</td>
            </tr>
        </tbody>
    </table>

    {{-- پاورقی --}}
    <table class="foot">
        <tr>
            <td style="width: 122px; text-align: right; vertical-align: top;">
                <table class="qrbox">
                    <tr><td class="ql">اعتبارسنجی QR-CODE</td></tr>
                    @if(! empty($qrDataUri))
                        <tr><td class="qi"><img src="{{ $qrDataUri }}" style="width: 88px; height: 88px;" alt="QR"></td></tr>
                    @endif
                    <tr><td class="qc" dir="ltr">{{ $invoice->invoice_code }}</td></tr>
                </table>
            </td>
            <td></td>
            <td style="width: 150px; text-align: left;">
                @if($stampUrl)
                    <img src="{{ $stampUrl }}" class="stamp" alt="">
                @else
                    <span class="bname" style="font-size: 11pt;">{{ $providerName }}</span><br>
                    <span class="bsub">{{ $providerTagline }}</span>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
