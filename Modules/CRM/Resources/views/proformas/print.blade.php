@php
    use Modules\CRM\Models\CrmSetting;

    // همان تنظیماتِ برندِ فاکتور — تا پیش‌فاکتور دقیقاً شبیهِ فاکتور باشد.
    $providerName    = CrmSetting::get('invoice_provider_name', $provider['name'] ?? 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', $provider['tagline'] ?? 'مرکز تخصصی خدمات لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', $provider['phone'] ?? '');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', $provider['address'] ?? '');
    $notes           = CrmSetting::get('invoice_print_notes', '');
    $logoPath        = CrmSetting::get('invoice_provider_logo_path', '');
    $logoUrl         = $logoPath ? route('crm.invoice.asset', 'logo') : null;

    $order = $proforma->order;

    $custName   = $proforma->customer_name ?: ($order?->customer_name ?: '—');
    $custMobile = $proforma->customer_mobile ?: ($order?->customer_mobile ?: '');
    $custPhone  = $order?->customer_phone ?: '';

    $cityName     = optional($order?->city)->name ?? '';
    $provinceName = optional($order?->province ?? null)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    $device = trim(($proforma->device_name ?? '').' '.($proforma->brand_name ?? ''));

    $items = is_array($proforma->items) ? $proforma->items : [];

    $faNum = fn ($n) => str_replace(
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        number_format((int) $n)
    );
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌فاکتور {{ $proforma->proforma_code }}</title>
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            margin: 0; padding: 24px;
            background: #ffffff; color: #111;
            font-size: 13px;
        }
        .page {
            background: white; max-width: 1180px; margin: 0 auto;
            padding: 32px 36px; border-radius: 6px;
            position: relative; overflow: hidden;
        }

        /* ─── واترمارکِ «پیش‌فاکتور» — تفاوتِ اصلی با فاکتور ─── */
        .watermark {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; z-index: 0;
        }
        .watermark span {
            font-size: 120px; font-weight: 900;
            color: rgba(30, 64, 175, 0.07);
            transform: rotate(-32deg);
            white-space: nowrap; letter-spacing: 8px;
            user-select: none;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }

        .toolbar { display: flex; gap: 8px; justify-content: flex-start; margin-bottom: 16px; }
        .btn-print {
            background: #4f46e5; color: white; border: none;
            padding: 10px 22px; border-radius: 6px;
            font-family: inherit; font-size: 14px; font-weight: bold; cursor: pointer;
        }
        .btn-print:hover { background: #4338ca; }

        .header { border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; overflow: hidden; }
        .brand    { float: right; display: flex; align-items: center; gap: 10px; }
        .meta-box {
            float: left; border: 1px solid #d1d5db; border-radius: 4px;
            display: flex; flex-direction: column; min-width: 220px; font-size: 12px;
        }
        .brand-logo {
            width: 56px; height: 56px; border-radius: 12px;
            background: linear-gradient(135deg,#4f46e5 0%,#2874a6 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold; font-size: 22px;
        }
        .brand-logo-img { height: 64px; max-width: 200px; object-fit: contain; }
        .brand-title { font-size: 22px; font-weight: bold; color: #0f172a; }
        .brand-sub   { font-size: 11px; color: #64748b; margin-top: 2px; }

        .meta-row { display: flex; border-bottom: 1px solid #e5e7eb; }
        .meta-row:last-child { border-bottom: none; }
        .meta-row > div { padding: 8px 12px; display: flex; align-items: center; justify-content: center; text-align: center; }
        .meta-row .meta-label { background: #f9fafb; border-inline-end: 1px solid #d1d5db; font-weight: bold; flex: 0 0 120px; }
        .meta-row .meta-value { flex: 1; min-width: 130px; }

        h1.invoice-title { text-align: center; font-size: 18px; font-weight: bold; margin: 20px 0 6px; color: #1e3a8a; }
        .proforma-hint { text-align: center; font-size: 12px; color: #64748b; margin-bottom: 16px; }

        .party { display: flex; border: 1px solid #d1d5db; }
        .party + .party { border-top: none; }
        .party-label {
            writing-mode: vertical-rl; transform: rotate(180deg);
            background: #f9fafb; border-inline-start: 1px solid #d1d5db;
            padding: 22px 14px; font-weight: bold; font-size: 15px;
            min-width: 50px; text-align: center; letter-spacing: 2px; color: #1f2937;
        }
        .party-body { flex: 1; padding: 12px 14px; line-height: 2; font-size: 12.5px; }
        .party-body .row { display: flex; gap: 32px; flex-wrap: wrap; }
        .party-body strong { font-weight: bold; color: #1f2937; }

        .section-title { background: #f3f4f6; border: 1px solid #d1d5db; border-top: none; padding: 8px; font-weight: bold; text-align: center; font-size: 13px; }
        table.items { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; border-top: none; font-size: 12.5px; }
        table.items th, table.items td { border: 1px solid #d1d5db; padding: 10px 8px; text-align: center; }
        table.items th { background: #f9fafb; font-weight: bold; }
        table.items td.desc { text-align: start; padding-inline-start: 16px; white-space: pre-line; line-height: 2; }
        table.items tr.total td { background: #f9fafb; font-weight: bold; }
        table.items td.num { direction: ltr; }

        .desc-box { border: 1px solid #d1d5db; border-top: none; display: flex; }
        .desc-box .lbl { background: #f9fafb; border-inline-end: 1px solid #d1d5db; padding: 12px 18px; font-weight: bold; min-width: 130px; display: flex; align-items: center; justify-content: center; }
        .desc-box .bdy { flex: 1; padding: 14px 18px; line-height: 2; font-size: 12.5px; color: #374151; white-space: pre-line; }

        .foot-note { margin-top: 20px; font-size: 11.5px; color: #64748b; line-height: 2; white-space: pre-line; text-align: center; border-top: 1px dashed #e5e7eb; padding-top: 12px; }

        @media print {
            body { background: white; padding: 0; }
            .page { box-shadow: none; max-width: none; padding: 16px 20px; }
            .toolbar { display: none; }
            .watermark span { color: rgba(30, 64, 175, 0.10); }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">پرینت / دانلود</button>
    </div>

    <div class="page">
        {{-- واترمارک — نشان می‌دهد این سند «پیش‌فاکتور» است نه فاکتورِ نهایی --}}
        <div class="watermark"><span>پیش‌فاکتور</span></div>

        <div class="header">
            <div class="brand">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="brand-logo-img">
                @else
                    <div class="brand-logo">ت</div>
                    <div>
                        <div class="brand-title">{{ $providerName }}</div>
                        <div class="brand-sub">{{ $providerTagline }}</div>
                    </div>
                @endif
            </div>
            <div class="meta-box">
                <div class="meta-row">
                    <div class="meta-label">شماره پیش‌فاکتور</div>
                    <div class="meta-value" dir="ltr">{{ $proforma->proforma_code }}</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">تاریخ</div>
                    <div class="meta-value" dir="ltr">@jdate($proforma->created_at)</div>
                </div>
                @if($proforma->valid_until)
                    <div class="meta-row">
                        <div class="meta-label">معتبر تا</div>
                        <div class="meta-value" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($proforma->valid_until)->format('Y/m/d') }}</div>
                    </div>
                @endif
            </div>
        </div>

        <h1 class="invoice-title">پیش‌فاکتور خدمات</h1>
        <div class="proforma-hint">این سند «برآوردِ قیمت» است و فاکتورِ نهایی محسوب نمی‌شود.</div>

        {{-- ارائه‌دهنده --}}
        <div class="party">
            <div class="party-label">ارائه‌دهنده</div>
            <div class="party-body">
                <div class="row">
                    <div><strong>نام شخص:</strong> {{ $providerName }}</div>
                    <div><strong>شماره تلفن:</strong> <span dir="ltr">{{ $providerPhone }}</span></div>
                    @if($providerPostal)<div><strong>کد پستی:</strong> <span dir="ltr">{{ $providerPostal }}</span></div>@endif
                </div>
                @if($providerAddress)<div><strong>آدرس:</strong> {{ $providerAddress }}</div>@endif
            </div>
        </div>

        {{-- مشتری --}}
        <div class="party">
            <div class="party-label">مشتری</div>
            <div class="party-body">
                <div class="row">
                    <div><strong>نام شخص:</strong> {{ $custName }}</div>
                    @if($custMobile)<div><strong>شماره موبایل:</strong> <span dir="ltr">{{ $custMobile }}</span></div>@endif
                    @if($custPhone)<div><strong>شماره تلفن:</strong> <span dir="ltr">{{ $custPhone }}</span></div>@endif
                </div>
                @if($device !== '')<div><strong>دستگاه:</strong> {{ $device }}</div>@endif
                @if($custAddr !== '')<div><strong>نشانی:</strong> {{ $custAddr }}</div>@endif
            </div>
        </div>

        {{-- اقلام --}}
        <div class="section-title">مشخصات کالا یا خدمات مورد برآورد</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 48px;">ردیف</th>
                    <th>شرح کالا/خدمت</th>
                    <th style="width: 80px;">تعداد</th>
                    <th style="width: 150px;">قیمت واحد (تومان)</th>
                    <th style="width: 160px;">مبلغ کل (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr>
                        <td>{{ $faNum($i + 1) }}</td>
                        <td class="desc">{{ $item['title'] ?? '' }}</td>
                        <td class="num">{{ $faNum((int) ($item['quantity'] ?? 1)) }}</td>
                        <td class="num">{{ $faNum((int) ($item['unit_price'] ?? 0)) }}</td>
                        <td class="num">{{ $faNum((int) ($item['total'] ?? 0)) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4">جمع اقلام</td>
                    <td class="num">{{ $faNum($proforma->subtotal) }}</td>
                </tr>
                @if($proforma->discount > 0)
                    <tr>
                        <td colspan="4">تخفیف</td>
                        <td class="num">{{ $faNum($proforma->discount) }}−</td>
                    </tr>
                @endif
                <tr class="total">
                    <td colspan="4">مبلغ نهایی</td>
                    <td class="num">{{ $faNum($proforma->total) }} تومان</td>
                </tr>
            </tbody>
        </table>

        @if($proforma->description)
            <div class="desc-box">
                <div class="lbl">توضیحات</div>
                <div class="bdy">{{ $proforma->description }}</div>
            </div>
        @endif

        <div class="foot-note">
            وضعیت: {{ $proforma->statusLabel() }}
            @if($providerPhone) · تماس: <span dir="ltr">{{ $providerPhone }}</span>@endif
            @if($notes){{ "\n".$notes }}@endif
        </div>
    </div>
</body>
</html>
