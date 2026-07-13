@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');
    $logoPath        = CrmSetting::get('invoice_provider_logo_path', '');
    $logoUrl         = $logoPath ? route('crm.invoice.asset', 'logo') : null;
    // مهر و امضای مجموعه — همان دارایی فاکتور (invoice_print_stamp_path).
    $stampPath       = CrmSetting::get('invoice_print_stamp_path', '');
    $stampUrl        = $stampPath ? route('crm.invoice.asset', 'stamp') : null;

    $customer = $order?->customer;
    $custName   = $order?->customer_name   ?: ($customer->display_name ?? '—');
    $custMobile = $order?->customer_mobile ?: ($customer->mobile ?? '');

    $deviceName = optional($order?->device)->name ?? '—';
    $brandName  = optional($order?->brand)->name ?? '';
    $provinceName = optional($order?->province)->name ?? '';
    $cityName     = optional($order?->city)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    $createdJalali = \Morilog\Jalali\Jalalian::fromDateTime($receipt->created_at)->format('Y/m/d H:i');
    $desc = trim((string) ($receipt->description ?? ''));
@endphp
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>رسید انتقال {{ $receipt->code }}</title>
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #1f2937; margin: 0; padding: 16px; background: #fff; }
        .sheet { max-width: 620px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; }
        .head { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 14px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { height: 46px; }
        .brand h1 { font-size: 18px; margin: 0; }
        .brand p { font-size: 11px; color: #6b7280; margin: 2px 0 0; }
        .title { text-align: center; font-size: 16px; font-weight: bold; background: #f3f4f6; border-radius: 8px; padding: 8px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td { padding: 7px 8px; border: 1px solid #e5e7eb; }
        td.label { background: #f9fafb; color: #6b7280; width: 34%; }
        .desc { margin-top: 14px; }
        .desc h3 { font-size: 13px; margin: 0 0 6px; }
        .desc .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; font-size: 13px; min-height: 54px; white-space: pre-wrap; line-height: 1.9; }
        .note { margin-top: 14px; font-size: 11px; color: #6b7280; line-height: 1.9; text-align: justify; }
        .note p { margin: 0 0 8px; }
        .note p:last-child { margin-bottom: 0; }
        .stamp-box { margin-top: 24px; text-align: left; }
        .stamp-box .label { font-size: 12px; color: #374151; margin-bottom: 6px; }
        .stamp-box img { height: 110px; max-width: 200px; object-fit: contain; opacity: 0.95; }
        .stamp-box .fallback { display: inline-block; border: 1px dashed #9ca3af; border-radius: 8px; padding: 22px 30px; font-size: 12px; color: #6b7280; }
        @media print { body { padding: 0; } .sheet { border: none; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <div class="brand">
                @if($logoUrl)<img src="{{ $logoUrl }}" alt="logo">@endif
                <div>
                    <h1>{{ $providerName }}</h1>
                    <p>{{ $providerTagline }}</p>
                </div>
            </div>
            <div style="text-align:left; font-size:12px; color:#6b7280;">
                <div>کد رسید: <strong dir="ltr">{{ $receipt->code }}</strong></div>
                <div>تاریخ: {{ $createdJalali }}</div>
            </div>
        </div>

        <div class="title">رسید تحویل دستگاه / قطعه</div>

        <table>
            <tr><td class="label">کد سفارش</td><td dir="ltr">{{ $order?->order_code ?? '—' }}</td></tr>
            <tr><td class="label">نام مشتری</td><td>{{ $custName }}</td></tr>
            <tr><td class="label">موبایل</td><td dir="ltr">{{ $custMobile }}</td></tr>
            <tr><td class="label">دستگاه</td><td>{{ $deviceName }}@if($brandName) — {{ $brandName }}@endif</td></tr>
            @if($custAddr)<tr><td class="label">آدرس</td><td>{{ $custAddr }}</td></tr>@endif
        </table>

        <div class="desc">
            <h3>توضیحات انتقال</h3>
            <div class="box">{{ $desc !== '' ? $desc : 'دستگاه جهت تعمیر از محل مشتری خارج شد.' }}</div>
        </div>

        <div class="note">
            <p>بدین‌وسیله تأیید می‌شود دستگاه یا قطعه مشخص‌شده در این رسید، جهت انجام بررسی، عیب‌یابی، تعمیر یا ارائه خدمات، توسط کارشناس {{ $providerName }} از محل مشتری تحویل گرفته شده است.</p>
            <p>این رسید صرفاً به‌منظور ثبت و تأیید تحویل دستگاه یا قطعه از محل مشتری صادر شده و به‌تنهایی بیانگر پذیرش گارانتی، تأیید نهایی وضعیت فنی، برآورد هزینه، انجام تعمیر، یا ایجاد هرگونه تعهد حقوقی و مالی برای طرفین نیست.</p>
            <p>جزئیات دستگاه یا قطعه، مشخصات سفارش و اطلاعات ثبت‌شده در سامانه {{ $providerName }}، ملاک ارائه خدمات و پیگیری‌های بعدی خواهد بود.</p>
            @if($providerPhone || $providerAddress)
                <p>@if($providerPhone)تماس: <span dir="ltr">{{ $providerPhone }}</span>@endif @if($providerAddress)— {{ $providerAddress }}@endif</p>
            @endif
        </div>

        {{-- مهر و امضای مجموعه — این رسید رسمی است و نیازی به امضای تکنسین ندارد. --}}
        <div class="stamp-box">
            <div class="label">مهر و امضای {{ $providerName }}</div>
            @if($stampUrl)
                <img src="{{ $stampUrl }}" alt="مهر و امضا">
            @else
                <div class="fallback">محل مهر و امضای مجموعه</div>
            @endif
        </div>
    </div>

    <div class="no-print" style="text-align:center; margin-top:14px;">
        <button onclick="window.print()" style="padding:8px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; cursor:pointer;">چاپ</button>
    </div>
</body>
</html>
