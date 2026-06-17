@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'تعمیرات تخصصی لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');
    $logoPath        = CrmSetting::get('invoice_provider_logo_path', '');
    $stampPath       = CrmSetting::get('invoice_print_stamp_path', '');
    $logoUrl         = $logoPath  ? route('crm.invoice.asset', 'logo')  : null;
    $stampUrl        = $stampPath ? route('crm.invoice.asset', 'stamp') : null;

    $customer = $invoice->customer;
    $order = $invoice->order;

    $custName   = $order?->customer_name   ?: ($customer->display_name ?? '');
    $custMobile = $order?->customer_mobile ?: ($customer->mobile ?? '');
    $custPhone  = $order?->customer_phone  ?: ($customer->phone ?? '');

    $cityName     = optional($order?->city)->name ?? '';
    $provinceName = optional($order?->province ?? null)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    $serviceTypeMap = ['repair' => 'تعمیر', 'service' => 'سرویس'];
    $serviceType = $serviceTypeMap[$order?->order_type] ?? ($order?->order_type ?? '');
    $deviceName  = optional($order?->device)->name ?: '';
    $brandName   = optional($order?->brand)->name ?: '';

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
    <title>صورتحساب خدمات - {{ $providerName }}</title>
    <style>
        /* پالتِ شما: navy #1e2a78 · navy2 #2b3a8c · ink #1b2340 · line #9aa3c4
           · lineSoft #c5cbe0 · headBg #dfe3f3 · headBg2 #eef0f9 · muted #3a4160 */
        * { font-family: vazir, sans-serif; }
        body { color: #1b2340; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; }

        .frame { padding: 2mm 1mm 4mm; }

        /* هدر */
        .top td { vertical-align: middle; padding-bottom: 6px; }
        .pill { border: 1.2pt solid #1e2a78; border-radius: 16px; }
        .pill td { padding: 4px 12px; font-size: 9pt; white-space: nowrap; }
        .pill .k { font-weight: bold; color: #1b2340; }
        .bname { font-size: 16pt; font-weight: bold; color: #1e2a78; }
        .bsub  { font-size: 8pt; color: #3a4160; }

        /* عنوان */
        .tl td { vertical-align: middle; }
        .tl .rule { border-bottom: 2pt solid #1e2a78; }
        .title { font-size: 22pt; font-weight: bold; color: #1b2340; text-align: center; white-space: nowrap; }

        /* بلوک اطلاعات */
        .info { border: 1.2pt solid #9aa3c4; border-radius: 5px; margin-top: 4px; }
        .info > tbody > tr > td { border-bottom: 1.2pt solid #9aa3c4; }
        .info > tbody > tr:last-child > td { border-bottom: 0; }
        .vlabel { width: 38px; text-align: center; vertical-align: middle; }
        .vlabel.n1 { background: #1e2a78; }
        .vlabel.n2 { background: #2b3a8c; }
        .vt { display: block; color: #fff; font-weight: bold; font-size: 9pt; white-space: nowrap; transform: rotate(-90deg); }
        .fld td { border: 0; border-right: 1pt solid #c5cbe0; padding: 15px 14px; font-size: 9.5pt; }
        .fld td:first-child { border-right: 0; }
        .fld .k { color: #3a4160; font-weight: 600; }
        .fld .v { font-weight: bold; color: #1b2340; }

        /* جدول خدمات */
        .svc { border: 1.2pt solid #9aa3c4; border-radius: 5px; margin-top: 16px; }
        .svc-bar { background: #dfe3f3; text-align: center; font-weight: bold; font-size: 11pt; color: #1b2340; padding: 9px; border-bottom: 1.2pt solid #9aa3c4; }
        .items th, .items td { border: 0.8pt solid #9aa3c4; padding: 11px 9px; font-size: 9.5pt; text-align: center; }
        .items th { background: #eef0f9; font-weight: bold; }
        .items td.desc { text-align: right; line-height: 1.8; }
        .items .total td { background: #eef0f9; font-weight: bold; font-size: 11pt; }

        /* فوتر */
        .foot { margin-top: 22px; }
        .foot td { vertical-align: bottom; }
        .qr { border: 1.2pt solid #9aa3c4; border-radius: 5px; width: 132px; }
        .qr .cap { text-align: center; font-size: 8pt; font-weight: bold; color: #3a4160; padding: 6px; border-bottom: 1.2pt solid #9aa3c4; }
        .qr .qi { text-align: center; padding: 8px 0 3px; }
        .qr .qc { text-align: center; font-size: 7pt; color: #98a2b3; padding-bottom: 5px; }
        .sign b { font-size: 13pt; font-weight: bold; color: #1e2a78; }
        .sign .web { font-size: 7.5pt; color: #3a4160; direction: ltr; }
    </style>
</head>
<body>
<div class="frame">

    {{-- هدر: شماره/تاریخ (چپ) — برند (راست) --}}
    <table class="top">
        <tr>
            <td style="width: 50%;">
                <table style="width: auto;"><tr>
                    <td style="padding-left: 8px;">
                        <table class="pill"><tr><td class="k">شماره فاکتور</td><td dir="ltr">{{ $invoice->invoice_code }}</td></tr></table>
                    </td>
                    <td>
                        <table class="pill"><tr><td class="k">تاریخ</td><td dir="ltr">{{ $dateStr }}</td></tr></table>
                    </td>
                </tr></table>
            </td>
            <td style="width: 50%; text-align: left;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="height: 58px;" alt="">
                @else
                    <span class="bname">{{ $providerName }}</span><br>
                    <span class="bsub">{{ $providerTagline }}</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- عنوان با خط‌های کناری --}}
    <table class="tl"><tr>
        <td class="rule"></td>
        <td style="white-space: nowrap; padding: 0 16px;"><div class="title">صورتحساب خدمات</div></td>
        <td class="rule"></td>
    </tr></table>

    {{-- اطلاعات --}}
    <table class="info">
        <tr>
            <td class="vlabel n1"><div class="vt">ارائه دهنده</div></td>
            <td>
                <table class="fld"><tr>
                    <td><span class="k">نام:</span> <span class="v">{{ $providerName }}</span></td>
                    <td><span class="k">شماره تلفن:</span> <span class="v" dir="ltr">{{ $fa($providerPhone) }}</span></td>
                    <td style="width: 42%;"><span class="k">آدرس:</span> <span class="v">{{ $providerAddress }}</span></td>
                    <td><span class="k">کد پستی:</span> <span class="v" dir="ltr">{{ $fa($providerPostal) }}</span></td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td class="vlabel n2"><div class="vt">مشتری</div></td>
            <td>
                <table class="fld"><tr>
                    <td style="width: 34%;"><span class="k">نام:</span> <span class="v">{{ $custName }}</span></td>
                    <td style="width: 28%;"><span class="k">شماره تلفن:</span> <span class="v" dir="ltr">{{ $fa($custMobile ?: $custPhone) }}</span></td>
                    <td><span class="k">آدرس:</span> <span class="v">{{ $custAddr }}</span></td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td class="vlabel n2"><div class="vt">اطلاعات خدمات</div></td>
            <td>
                <table class="fld"><tr>
                    <td style="width: 34%;"><span class="k">نوع خدمت:</span> <span class="v">{{ $serviceType }}</span></td>
                    <td style="width: 33%;"><span class="k">دستگاه:</span> <span class="v">{{ $deviceName }}</span></td>
                    <td><span class="k">برند:</span> <span class="v">{{ $brandName }}</span></td>
                </tr></table>
            </td>
        </tr>
    </table>

    {{-- جدول خدمات --}}
    <div class="svc">
        <div class="svc-bar">شرح خدمات صورت گرفته</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 44px;">ردیف</th>
                    <th>شرح خدمات</th>
                    <th style="width: 165px;">مبلغ کل (تومان)</th>
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
                <tr class="total">
                    <td colspan="2">جمع کل</td>
                    <td>{{ $money($grandTotal) }} تومان</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- فوتر: QR (راست) — هولوگرام (وسط) — مهر/برند (چپ) --}}
    <table class="foot">
        <tr>
            <td style="width: 140px; text-align: right;">
                <table class="qr">
                    <tr><td class="cap">اعتبارسنجی QR-CODE</td></tr>
                    @if(! empty($qrDataUri))
                        <tr><td class="qi"><img src="{{ $qrDataUri }}" style="width: 100px; height: 100px;" alt="QR"></td></tr>
                    @endif
                    <tr><td class="qc" dir="ltr">{{ $invoice->invoice_code }}</td></tr>
                </table>
            </td>
            <td style="text-align: center;"></td>
            <td class="sign" style="width: 200px; text-align: left;">
                @if($stampUrl)
                    <img src="{{ $stampUrl }}" style="width: 130px;" alt="">
                @else
                    <b>{{ $providerName }}</b><br>
                    <span class="bsub">{{ $providerTagline }}</span><br>
                    <span class="web">www.tamironline.com</span>
                @endif
            </td>
        </tr>
    </table>

</div>
</body>
</html>
