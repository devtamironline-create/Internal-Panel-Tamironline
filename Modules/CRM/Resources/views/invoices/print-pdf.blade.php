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

    // ارقام فارسی
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
        @page { margin: 8mm; }
        * { font-family: vazir, sans-serif; }
        body { color: #1f2937; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; }

        /* ── سربرگ ── */
        .head td { vertical-align: middle; padding: 0 0 6px; }
        .brand-name { font-size: 15pt; font-weight: bold; color: #15315a; }
        .brand-sub  { font-size: 8pt; color: #7b8794; }
        .title { font-size: 17pt; font-weight: bold; color: #15315a; text-align: center; letter-spacing: 2px; }
        .meta { border: 0.6pt solid #15315a; margin: 0 0 4px auto; width: auto; }
        .meta td { padding: 3px 8px; font-size: 8.5pt; white-space: nowrap; }
        .meta .k { background: #eef3f8; font-weight: bold; color: #15315a; border-left: 0.6pt solid #15315a; }
        .rule { border-bottom: 1.4pt solid #15315a; height: 0; margin-bottom: 8px; }

        /* ── بلوک اطلاعات با برچسب عمودی ── */
        .info { border: 0.6pt solid #15315a; }
        .info td { border: 0.6pt solid #15315a; }
        .vlabel { width: 24px; background: #15315a; text-align: center; vertical-align: middle; }
        .vlabel .vt { color: #fff; font-weight: bold; font-size: 9pt; white-space: nowrap; transform: rotate(90deg); }
        .fld { padding: 0; }
        .fld table { border: 0; }
        .fld td { border: 0; border-left: 0.5pt solid #dde5ee; padding: 7px 9px; font-size: 9pt; line-height: 1.7; }
        .fld td:last-child { border-left: 0; }
        .fld .k { color: #15315a; font-weight: bold; }

        /* ── جدول خدمات ── */
        .sec-head { background: #15315a; color: #fff; text-align: center; font-weight: bold; font-size: 10pt; padding: 6px; margin-top: 10px; }
        .items { border: 0.6pt solid #15315a; font-size: 9pt; }
        .items th, .items td { border: 0.6pt solid #15315a; padding: 7px 6px; text-align: center; }
        .items th { background: #eef3f8; color: #15315a; font-weight: bold; }
        .items td.desc { text-align: right; line-height: 1.8; }
        .items tr.sum td { background: #f6f8fb; font-weight: bold; color: #15315a; }

        /* ── پاورقی ── */
        .foot { margin-top: 12px; }
        .foot td { vertical-align: bottom; }
        .qrbox { border: 0.6pt solid #15315a; width: 118px; text-align: center; }
        .qrbox .ql { background: #eef3f8; color: #15315a; font-weight: bold; font-size: 7.5pt; padding: 3px; border-bottom: 0.6pt solid #15315a; }
        .qrbox .qi { padding: 6px 6px 2px; }
        .qrbox .qc { font-size: 7pt; color: #7b8794; padding-bottom: 5px; }
        .stamp { width: 120px; }
    </style>
</head>
<body>

    {{-- سربرگ --}}
    <table class="head">
        <tr>
            <td style="width: 34%; text-align: right;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="height: 46px;" alt="">
                @else
                    <span class="brand-name">{{ $providerName }}</span><br>
                    <span class="brand-sub">{{ $providerTagline }}</span>
                @endif
            </td>
            <td style="width: 34%;"><div class="title">صورتحساب خدمات</div></td>
            <td style="width: 32%;">
                <table class="meta"><tr><td class="k">شماره فاکتور</td><td dir="ltr">{{ $invoice->invoice_code }}</td></tr></table>
                <table class="meta"><tr><td class="k">تاریخ</td><td dir="ltr">{{ $dateStr }}</td></tr></table>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    {{-- اطلاعات: ارائه‌دهنده / مشتری / اطلاعات خدمت --}}
    <table class="info">
        <tr>
            <td class="vlabel"><span class="vt">ارائه‌دهنده</span></td>
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
            <td class="vlabel"><span class="vt">مشتری</span></td>
            <td class="fld">
                <table><tr>
                    <td style="width: 33%;"><span class="k">نام:</span> {{ $custName }}</td>
                    <td style="width: 27%;"><span class="k">تلفن:</span> <span dir="ltr">{{ $fa($custMobile ?: $custPhone) ?: '—' }}</span></td>
                    <td><span class="k">آدرس:</span> {{ $custAddr !== '' ? $custAddr : '—' }}</td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td class="vlabel"><span class="vt">اطلاعات خدمت</span></td>
            <td class="fld">
                <table><tr>
                    <td style="width: 33%;"><span class="k">نوع خدمت:</span> {{ $serviceType }}</td>
                    <td style="width: 33%;"><span class="k">دستگاه:</span> {{ $deviceName }}</td>
                    <td><span class="k">برند:</span> {{ $brandName }}</td>
                </tr></table>
            </td>
        </tr>
    </table>

    {{-- شرح خدمات --}}
    <div class="sec-head">شرح خدمات صورت گرفته</div>
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

    {{-- پاورقی: QR (راست) + مهر (چپ) --}}
    <table class="foot">
        <tr>
            <td style="width: 130px; text-align: right; vertical-align: top;">
                <table class="qrbox">
                    <tr><td class="ql">اعتبارسنجی QR-CODE</td></tr>
                    @if(! empty($qrDataUri))
                        <tr><td class="qi"><img src="{{ $qrDataUri }}" style="width: 92px; height: 92px;" alt="QR"></td></tr>
                    @endif
                    <tr><td class="qc" dir="ltr">{{ $invoice->invoice_code }}</td></tr>
                </table>
            </td>
            <td></td>
            <td style="width: 150px; text-align: left;">
                @if($stampUrl)
                    <img src="{{ $stampUrl }}" class="stamp" alt="">
                @else
                    <div class="brand-name" style="font-size: 11pt;">{{ $providerName }}</div>
                    <div class="brand-sub">{{ $providerTagline }}</div>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
