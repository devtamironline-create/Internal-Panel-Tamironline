@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');
    $notes           = CrmSetting::get('invoice_print_notes', '');

    $customer = $invoice->customer;
    $order = $invoice->order;

    // اولویت با مقادیر روی خود سفارش — این‌ها snapshot زمان سفارش‌اند
    $custName   = $order?->customer_name   ?: ($customer->display_name ?? '—');
    $custMobile = $order?->customer_mobile ?: ($customer->mobile ?? '');
    $custPhone  = $order?->customer_phone  ?: ($customer->phone ?? '');

    $cityName     = optional($order?->city)->name ?? '';
    $provinceName = optional($order?->province ?? null)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    // اقلام — اول OrderItem، fallback به آرایه‌های موازی WP
    $rows = collect();
    if ($order && $order->items->isNotEmpty()) {
        foreach ($order->items as $item) {
            $rows->push([
                'title' => $item->title,
                'qty'   => (int) $item->quantity,
                'total' => (int) $item->total_price,
            ]);
        }
    } elseif ($order) {
        $titles = is_array($order->piece_list) ? $order->piece_list : [];
        $sells  = is_array($order->customer_price_list) ? $order->customer_price_list : [];
        foreach ($titles as $i => $title) {
            if ($title === '' || $title === null) continue;
            $unit = (int) ($sells[$i] ?? 0);
            $rows->push([
                'title' => is_string($title) ? $title : (string) ($title['title'] ?? ''),
                'qty'   => 1,
                'total' => $unit,
            ]);
        }
    }

    $grandTotal = (int) ($invoice->total_amount ?: $rows->sum('total'));

    // اگر هیچ ردیفی نداریم، یک ردیف کلی از توضیحات/مبلغ کل می‌سازیم
    if ($rows->isEmpty()) {
        $desc = $order->invoice_descripotion ?? $order->order_description ?? 'انجام خدمات';
        $rows->push([
            'title' => trim((string) $desc) ?: 'انجام خدمات',
            'qty'   => 1,
            'total' => $grandTotal,
        ]);
    }

    // اقلام WP قدیمی اغلب عنوان دارند ولی قیمت ندارند؛ در این حالت
    // مبلغ کل فاکتور را در یک ردیف ادغام‌شده نمایش می‌دهیم تا ستون
    // «مبلغ کل» با «جمع کل» جور باشد.
    if ($rows->sum('total') === 0 && $grandTotal > 0) {
        $combinedTitle = $rows->pluck('title')->filter()->implode('، ');
        $rows = collect([[
            'title' => $combinedTitle !== '' ? $combinedTitle : 'انجام خدمات',
            'qty'   => 1,
            'total' => $grandTotal,
        ]]);
    }

    // فرمت عدد فارسی
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
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            margin: 0; padding: 24px;
            background: #f3f4f6; color: #111;
            font-size: 13px;
        }
        .page {
            background: white; max-width: 950px; margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 32px 36px; border-radius: 6px;
            position: relative;
        }
        /* ─── Toolbar (hidden on print) ─── */
        .toolbar {
            display: flex; justify-content: flex-start; margin-bottom: 16px;
        }
        .btn-print {
            background: #16a34a; color: white; border: none;
            padding: 10px 22px; border-radius: 6px;
            font-family: inherit; font-size: 14px; font-weight: bold;
            cursor: pointer;
        }
        .btn-print:hover { background: #15803d; }

        /* ─── Header — float-based (bulletproof in RTL) ─── */
        .header {
            border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;
            overflow: hidden;
        }
        .brand    { float: right; display: flex; align-items: center; gap: 10px; }
        .meta-box {
            float: left;
            border: 1px solid #d1d5db; border-radius: 4px;
            display: flex; flex-direction: column; min-width: 220px; font-size: 12px;
        }
        .brand-logo {
            width: 56px; height: 56px; border-radius: 12px;
            background: linear-gradient(135deg,#16a34a 0%,#0d9488 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold; font-size: 22px;
        }
        .brand-title { font-size: 22px; font-weight: bold; color: #0f172a; }
        .brand-sub   { font-size: 11px; color: #64748b; margin-top: 2px; }

        .meta-row { display: flex; border-bottom: 1px solid #e5e7eb; }
        .meta-row:last-child { border-bottom: none; }
        .meta-row > div { padding: 8px 12px; }
        .meta-row .meta-label { background: #f9fafb; border-inline-end: 1px solid #d1d5db; font-weight: bold; }

        h1.invoice-title {
            text-align: center; font-size: 18px; font-weight: bold;
            margin: 20px 0 16px; color: #0f172a;
        }

        /* ─── Party info ─── */
        .party {
            display: flex; border: 1px solid #d1d5db;
        }
        .party + .party { border-top: none; }
        .party-label {
            writing-mode: vertical-rl; transform: rotate(180deg);
            background: #f9fafb; border-inline-start: 1px solid #d1d5db;
            padding: 12px 8px; font-weight: bold; font-size: 12px;
            min-width: 36px; text-align: center;
        }
        .party-body { flex: 1; padding: 12px 14px; line-height: 2; font-size: 12.5px; }
        .party-body .row { display: flex; gap: 32px; flex-wrap: wrap; }
        .party-body strong { font-weight: bold; color: #1f2937; }

        /* ─── Items table ─── */
        .section-title {
            background: #f3f4f6; border: 1px solid #d1d5db; border-top: none;
            padding: 8px; font-weight: bold; text-align: center; font-size: 13px;
        }
        table.items {
            width: 100%; border-collapse: collapse; border: 1px solid #d1d5db;
            border-top: none; font-size: 12.5px;
        }
        table.items th, table.items td {
            border: 1px solid #d1d5db; padding: 10px 8px; text-align: center;
        }
        table.items th { background: #f9fafb; font-weight: bold; }
        table.items td.desc { text-align: start; padding-inline-start: 16px; }
        table.items tr.total td {
            background: #f9fafb; font-weight: bold;
        }

        /* ─── Notes ─── */
        .notes {
            margin-top: 24px; font-size: 11.5px; color: #475569;
            line-height: 2; white-space: pre-line;
        }

        @media print {
            body { background: white; padding: 0; }
            .page { box-shadow: none; max-width: none; padding: 16px 20px; }
            .toolbar { display: none; }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">پرینت / دانلود</button>
    </div>

    <div class="page">
        {{-- Header — برند سمت راست، باکس شماره/تاریخ سمت چپ --}}
        <div class="header">
            <div class="brand">
                <div class="brand-logo">ت</div>
                <div>
                    <div class="brand-title">{{ $providerName }}</div>
                    <div class="brand-sub">مرکز تخصصی خدمات لوازم خانگی</div>
                </div>
            </div>
            <div class="meta-box">
                <div class="meta-row">
                    <div class="meta-label">شمارهٔ پیش‌فاکتور</div>
                    <div dir="ltr">{{ $faNum($invoice->id) }}</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">تاریخ</div>
                    <div dir="ltr">@jdate($invoice->issued_at ?? $invoice->created_at)</div>
                </div>
            </div>
        </div>

        <h1 class="invoice-title">صورتحساب خدمات</h1>

        {{-- ارائه‌دهنده --}}
        <div class="party">
            <div class="party-label">ارائه‌دهنده</div>
            <div class="party-body">
                <div class="row">
                    <div><strong>نام شخص:</strong> {{ $providerName }}</div>
                    <div><strong>شماره تلفن:</strong> <span dir="ltr">{{ $providerPhone }}</span></div>
                    <div><strong>کد پستی:</strong> <span dir="ltr">{{ $providerPostal }}</span></div>
                </div>
                <div><strong>آدرس:</strong> {{ $providerAddress }}</div>
            </div>
        </div>

        {{-- مشتری --}}
        <div class="party">
            <div class="party-label">مشتری</div>
            <div class="party-body">
                <div class="row">
                    <div><strong>نام شخص:</strong> {{ $custName }}</div>
                    @if($custMobile)
                        <div><strong>شماره موبایل:</strong> <span dir="ltr">{{ $custMobile }}</span></div>
                    @endif
                    @if($custPhone)
                        <div><strong>شماره تلفن:</strong> <span dir="ltr">{{ $custPhone }}</span></div>
                    @endif
                </div>
                @if($custAddr !== '')
                    <div><strong>نشانی:</strong> {{ $custAddr }}</div>
                @endif
            </div>
        </div>

        {{-- جدول اقلام --}}
        <div class="section-title">مشخصات کالا یا خدمات مورد معامله</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 48px;">ردیف</th>
                    <th>شرح کالا/خدمت</th>
                    <th style="width: 110px;">تعداد/مقدار</th>
                    <th style="width: 160px;">مبلغ کل (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td>{{ $faNum($i + 1) }}</td>
                        <td class="desc">{{ $row['title'] }}</td>
                        <td>{{ $faNum($row['qty']) }}</td>
                        <td>{{ $faNum($row['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">جمع کل</td>
                    <td>{{ $faNum($grandTotal) }} تومان</td>
                </tr>
            </tbody>
        </table>

        {{-- یادداشت‌ها --}}
        @if($notes)
            <div class="notes">{{ $notes }}</div>
        @endif
    </div>
</body>
</html>
