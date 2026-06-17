@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');
    $notes           = CrmSetting::get('invoice_print_notes', '');
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
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صورتحساب {{ $invoice->invoice_code }}</title>
    {{-- CSSِ ساده و سازگار با mPDF — بدون flex/float/transform/writing-mode/calc --}}
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 12px; color: #111; }
        table { border-collapse: collapse; }
        .head { width: 100%; border-bottom: 1px solid #e5e7eb; }
        .head td { vertical-align: middle; padding-bottom: 10px; }
        .brand-name { font-size: 20px; font-weight: bold; color: #0f172a; }
        .brand-sub { font-size: 11px; color: #64748b; }
        .meta { border: 1px solid #d1d5db; font-size: 12px; }
        .meta td { border: 1px solid #d1d5db; padding: 6px 10px; }
        .meta .lbl { background: #f9fafb; font-weight: bold; }
        h1.title { text-align: center; font-size: 17px; font-weight: bold; margin: 16px 0 12px; color: #0f172a; }
        .party { width: 100%; border: 1px solid #d1d5db; margin-bottom: 8px; }
        .party td { border: 1px solid #d1d5db; padding: 10px; font-size: 12.5px; line-height: 1.9; vertical-align: middle; }
        .party .plabel { width: 90px; background: #f9fafb; font-weight: bold; text-align: center; }
        .items { width: 100%; border: 1px solid #d1d5db; font-size: 12.5px; margin-top: 4px; }
        .items th, .items td { border: 1px solid #d1d5db; padding: 9px 8px; text-align: center; }
        .items th { background: #f9fafb; font-weight: bold; }
        .items td.desc { text-align: right; }
        .items tr.total td { background: #f9fafb; font-weight: bold; }
        .section-title { background: #f3f4f6; border: 1px solid #d1d5db; border-bottom: none; padding: 7px; font-weight: bold; text-align: center; font-size: 13px; }
        .footer { width: 100%; margin-top: 16px; }
        .footer td { vertical-align: top; font-size: 11.5px; color: #475569; line-height: 1.9; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="text-align: right;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="height: 60px;" alt="">
                @else
                    <span class="brand-name">{{ $providerName }}</span><br>
                    <span class="brand-sub">{{ $providerTagline }}</span>
                @endif
            </td>
            <td style="text-align: left;">
                <table class="meta">
                    <tr><td class="lbl">شماره فاکتور</td><td dir="ltr">{{ $invoice->invoice_code }}</td></tr>
                    <tr><td class="lbl">تاریخ</td><td dir="ltr">@jdate($invoice->issued_at ?? $invoice->created_at)</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h1 class="title">صورتحساب خدمات</h1>

    <table class="party">
        <tr>
            <td class="plabel">ارائه‌دهنده</td>
            <td>
                <strong>نام شخص:</strong> {{ $providerName }} &nbsp;&nbsp;
                <strong>تلفن:</strong> <span dir="ltr">{{ $providerPhone }}</span> &nbsp;&nbsp;
                <strong>کد پستی:</strong> <span dir="ltr">{{ $providerPostal }}</span>
                @if($providerAddress)<br><strong>آدرس:</strong> {{ $providerAddress }}@endif
            </td>
        </tr>
    </table>

    <table class="party">
        <tr>
            <td class="plabel">مشتری</td>
            <td>
                <strong>نام شخص:</strong> {{ $custName }}
                @if($custMobile)&nbsp;&nbsp;<strong>موبایل:</strong> <span dir="ltr">{{ $custMobile }}</span>@endif
                @if($custPhone)&nbsp;&nbsp;<strong>تلفن:</strong> <span dir="ltr">{{ $custPhone }}</span>@endif
                @if($custAddr !== '')<br><strong>نشانی:</strong> {{ $custAddr }}@endif
            </td>
        </tr>
    </table>

    <div class="section-title">مشخصات کالا یا خدمات مورد معامله</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 44px;">ردیف</th>
                <th>شرح کالا/خدمت</th>
                <th style="width: 170px;">مبلغ کل (تومان)</th>
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

    @if($notes || $stampUrl)
    <table class="footer">
        <tr>
            <td>{{ $notes }}</td>
            @if($stampUrl)
                <td style="width: 150px; text-align: left;"><img src="{{ $stampUrl }}" style="width: 120px;" alt=""></td>
            @endif
        </tr>
    </table>
    @endif
</body>
</html>
