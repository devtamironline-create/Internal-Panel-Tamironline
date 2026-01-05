<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>فاکتور {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: vazir, tahoma, sans-serif;
            direction: rtl;
            color: #1f2937;
            font-size: 10pt;
            line-height: 1.5;
            background: #fff;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 3px solid #0891b2;
            padding-bottom: 12px;
        }

        .logo-cell {
            width: 50%;
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            font-size: 28pt;
            font-weight: bold;
            color: #0891b2;
        }

        .logo-subtitle {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 3px;
        }

        .info-cell {
            width: 50%;
            text-align: left;
            vertical-align: top;
        }

        .info-box {
            float: left;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
            background: #f9fafb;
        }

        .info-row {
            padding: 3px 0;
        }

        .info-label {
            color: #6b7280;
            font-size: 9pt;
            display: inline-block;
            width: 80px;
            text-align: right;
        }

        .info-value {
            display: inline-block;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 3px 12px;
            font-size: 9pt;
            min-width: 110px;
            text-align: center;
            background: #fff;
            font-weight: bold;
        }

        .status-paid {
            background: #16a34a;
            color: #fff;
            border-color: #16a34a;
        }

        .status-draft {
            background: #f59e0b;
            color: #fff;
            border-color: #f59e0b;
        }

        .status-sent {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }

        .status-overdue {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }

        .status-cancelled {
            background: #6b7280;
            color: #fff;
            border-color: #6b7280;
        }

        /* Section Header */
        .section-header {
            background: #0891b2;
            color: #fff;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 11pt;
            margin: 12px 0 8px 0;
            border-radius: 4px;
        }

        /* Customer Info */
        .customer-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #f9fafb;
        }

        .customer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .customer-table td {
            padding: 5px 8px;
            font-size: 9pt;
            vertical-align: top;
        }

        .customer-label {
            color: #0891b2;
            font-weight: bold;
            white-space: nowrap;
        }

        .customer-value {
            color: #374151;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .items-table th {
            background: #0891b2;
            color: #fff;
            padding: 10px 6px;
            text-align: center;
            font-size: 9pt;
            font-weight: bold;
            border: 1px solid #0891b2;
        }

        .items-table td {
            padding: 10px 6px;
            text-align: center;
            font-size: 9pt;
            border: 1px solid #e5e7eb;
            background: #fff;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f3f4f6;
        }

        .description-cell {
            text-align: right !important;
        }

        /* Subtotal Row */
        .subtotal-row td {
            background: #e0f2fe !important;
            color: #0369a1 !important;
            font-weight: bold;
            font-size: 10pt;
            border: 1px solid #7dd3fc !important;
        }

        /* Total Row */
        .total-row td {
            background: #0c4a6e !important;
            color: #fff !important;
            font-weight: bold;
            font-size: 11pt;
            border: 1px solid #0c4a6e !important;
            padding: 12px 6px;
        }

        /* Notes */
        .notes-box {
            margin-top: 10px;
            padding: 8px 12px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 4px;
        }

        .notes-title {
            color: #92400e;
            font-weight: bold;
            font-size: 9pt;
        }

        .notes-text {
            color: #78350f;
            font-size: 9pt;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding: 10px;
            background: #f3f4f6;
            border-radius: 4px;
            text-align: center;
        }

        .footer-text {
            color: #4b5563;
            font-size: 9pt;
        }

        .footer-site {
            color: #0891b2;
            font-weight: bold;
            font-size: 10pt;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <div class="logo">هاستلینو</div>
                <div class="logo-subtitle">خدمات میزبانی وب و دامنه</div>
            </td>
            <td class="info-cell">
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">وضعیت:</span>
                        <span class="info-value status-{{ $invoice->status }}">{{ $invoice->status_label }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">شماره فاکتور:</span>
                        <span class="info-value">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تاریخ صدور:</span>
                        <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تاریخ اعتبار:</span>
                        <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Customer Section -->
    <div class="section-header">مشخصات خریدار</div>
    <div class="customer-box">
        <table class="customer-table">
            <tr>
                <td class="customer-label">نام و نام خانوادگی:</td>
                <td class="customer-value">{{ $invoice->customer->full_name }}</td>
                <td class="customer-label">شماره تلفن:</td>
                <td class="customer-value">{{ $invoice->customer->mobile }}</td>
                <td class="customer-label">ایمیل:</td>
                <td class="customer-value">{{ $invoice->customer->email ?? '-' }}</td>
            </tr>
            <tr>
                <td class="customer-label">آدرس:</td>
                <td class="customer-value" colspan="3">{{ $invoice->customer->address ?? '-' }}</td>
                <td class="customer-label">کد پستی:</td>
                <td class="customer-value">{{ $invoice->customer->postal_code ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Items Section -->
    <div class="section-header">مشخصات کالا یا خدمات</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40px;">ردیف</th>
                <th style="width: 70px;">کد کالا</th>
                <th>شرح کالا یا خدمات</th>
                <th style="width: 70px;">تعداد</th>
                <th style="width: 120px;">قیمت واحد (تومان)</th>
                <th style="width: 120px;">مبلغ کل (تومان)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product_id ?? '-' }}</td>
                <td class="description-cell">{{ $item->description }}</td>
                <td>{{ number_format($item->quantity) }}</td>
                <td>{{ number_format($item->unit_price) }}</td>
                <td>{{ number_format($item->total) }}</td>
            </tr>
            @endforeach
            @if($invoice->tax_amount > 0 || $invoice->discount_amount > 0)
            <tr class="subtotal-row">
                <td colspan="4"></td>
                <td>جمع اقلام</td>
                <td>{{ number_format($invoice->subtotal ?? $invoice->total_amount) }}</td>
            </tr>
            @endif
            @if($invoice->discount_amount > 0)
            <tr>
                <td colspan="4"></td>
                <td style="color: #16a34a; font-weight: bold;">تخفیف</td>
                <td style="color: #16a34a;">{{ number_format($invoice->discount_amount) }}-</td>
            </tr>
            @endif
            @if($invoice->tax_amount > 0)
            <tr>
                <td colspan="4"></td>
                <td style="font-weight: bold;">مالیات</td>
                <td>{{ number_format($invoice->tax_amount) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="4"></td>
                <td>مبلغ قابل پرداخت</td>
                <td>{{ number_format($invoice->total_amount) }} تومان</td>
            </tr>
        </tbody>
    </table>

    <!-- Notes -->
    @if($invoice->notes)
    <div class="notes-box">
        <span class="notes-title">توضیحات:</span>
        <span class="notes-text">{{ $invoice->notes }}</span>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div class="footer-text">با تشکر از اعتماد شما</div>
        <div class="footer-site">www.hostlino.com</div>
    </div>
</body>
</html>
