<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتور سفارش #{{ $order->order_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }

        .invoice {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .company-info h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .order-info {
            text-align: left;
        }

        .order-number {
            font-size: 16px;
            font-weight: bold;
        }

        .order-date {
            color: #666;
        }

        .addresses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .address-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .address-box h3 {
            font-size: 14px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: right;
        }

        .items-table th {
            background: #f5f5f5;
            font-weight: 500;
        }

        .items-table .number {
            width: 50px;
            text-align: center;
        }

        .items-table .qty {
            width: 60px;
            text-align: center;
        }

        .items-table .price {
            width: 120px;
            text-align: left;
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 15px;
            border: 1px solid #ddd;
        }

        .totals-table .label {
            text-align: right;
            background: #f9f9f9;
        }

        .totals-table .value {
            text-align: left;
        }

        .totals-table .total-row {
            font-weight: bold;
            font-size: 14px;
            background: #333;
            color: white;
        }

        .totals-table .total-row .label,
        .totals-table .total-row .value {
            background: #333;
        }

        .notes {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .notes h3 {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .notes p {
            color: #666;
        }

        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #888;
            font-size: 11px;
        }

        .barcode {
            text-align: center;
            margin: 20px 0;
            font-family: 'Libre Barcode 39', monospace;
            font-size: 40px;
        }

        @media print {
            body {
                padding: 0;
            }

            .invoice {
                border: none;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
        }

        .print-button:hover {
            background: #555;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            background: #e3f2fd;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">چاپ فاکتور</button>

    @if(isset($printResult) && $printResult['is_duplicate'])
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; background: #fef3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 8px; max-width: 350px; z-index: 100;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <strong style="color: #856404;">پرینت تکراری</strong>
                <p style="margin: 5px 0 0; color: #856404; font-size: 12px;">
                    این سفارش {{ $printResult['current_count'] }} بار پرینت شده است.
                    @if($printResult['manager_notified'])
                    <br><span style="color: #dc3545;">مدیر مطلع شد.</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    <div class="invoice">
        <div class="header">
            <div class="company-info">
                <h1>{{ config('app.name', 'فروشگاه') }}</h1>
                <p>فاکتور فروش</p>
            </div>
            <div class="order-info">
                <div class="order-number">سفارش #{{ $order->order_number }}</div>
                <div class="order-date">{{ $order->date_created?->format('Y/m/d H:i') }}</div>
                <div class="status-badge">{{ $order->status_label }}</div>
            </div>
        </div>

        <div class="addresses">
            <div class="address-box">
                <h3>اطلاعات مشتری</h3>
                <p><strong>{{ $order->customer_full_name }}</strong></p>
                @if($order->billing_phone)
                <p>تلفن: {{ $order->billing_phone }}</p>
                @endif
                @if($order->customer_email)
                <p>ایمیل: {{ $order->customer_email }}</p>
                @endif
            </div>
            <div class="address-box">
                <h3>آدرس ارسال</h3>
                @if($order->shipping_address)
                <p>{{ $order->shipping_address }}</p>
                @else
                <p>{{ $order->billing_address }}</p>
                @endif
                @if($order->billing_postcode)
                <p>کدپستی: {{ $order->billing_postcode }}</p>
                @endif
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="number">ردیف</th>
                    <th>نام محصول</th>
                    <th>SKU</th>
                    <th class="qty">تعداد</th>
                    <th class="price">قیمت واحد</th>
                    <th class="price">جمع</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td>
                        {{ $item->name }}
                        @if($item->variation_text)
                        <br><small style="color: #666">{{ $item->variation_text }}</small>
                        @endif
                    </td>
                    <td>{{ $item->sku ?? '-' }}</td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="price">{{ number_format($item->price) }} تومان</td>
                    <td class="price">{{ number_format($item->total) }} تومان</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table class="totals-table">
                @if($order->subtotal)
                <tr>
                    <td class="label">جمع جزء:</td>
                    <td class="value">{{ number_format($order->subtotal) }} تومان</td>
                </tr>
                @endif
                @if($order->shipping_total > 0)
                <tr>
                    <td class="label">هزینه ارسال:</td>
                    <td class="value">{{ number_format($order->shipping_total) }} تومان</td>
                </tr>
                @endif
                @if($order->discount_total > 0)
                <tr>
                    <td class="label">تخفیف:</td>
                    <td class="value" style="color: red;">-{{ number_format($order->discount_total) }} تومان</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="label">جمع کل:</td>
                    <td class="value">{{ $order->formatted_total }}</td>
                </tr>
            </table>
        </div>

        @if($order->customer_note)
        <div class="notes">
            <h3>یادداشت مشتری</h3>
            <p>{{ $order->customer_note }}</p>
        </div>
        @endif

        @if($order->payment_method_title)
        <div class="notes">
            <h3>روش پرداخت</h3>
            <p>{{ $order->payment_method_title }}</p>
        </div>
        @endif

        <div class="barcode">
            *{{ $order->order_number }}*
        </div>

        <div class="footer">
            <p>با تشکر از خرید شما</p>
            <p>تاریخ چاپ: {{ now()->format('Y/m/d H:i') }}</p>
        </div>
    </div>

    <script>
        // Auto print on load
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
