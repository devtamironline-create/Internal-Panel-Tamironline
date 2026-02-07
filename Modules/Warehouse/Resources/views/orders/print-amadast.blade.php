<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتور آمادست - سفارش #{{ $order->order_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
            background: white;
            padding: 10px;
        }

        .shipping-label {
            max-width: 10cm;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 10px;
            page-break-after: always;
        }

        .shipping-label:last-child {
            page-break-after: avoid;
        }

        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .logo-section {
            text-align: center;
        }

        .logo-section h1 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .logo-section .amadast-badge {
            background: #000;
            color: #fff;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 3px;
        }

        .order-info-box {
            text-align: left;
            font-size: 10px;
        }

        .order-info-box .order-number {
            font-size: 14px;
            font-weight: bold;
        }

        /* Tracking Codes Section */
        .tracking-section {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .tracking-section h3 {
            font-size: 12px;
            margin-bottom: 8px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .tracking-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .tracking-item {
            text-align: center;
        }

        .tracking-item .label {
            font-size: 9px;
            color: #666;
            margin-bottom: 3px;
        }

        .tracking-item .code {
            font-size: 13px;
            font-weight: bold;
            background: #fff;
            padding: 5px;
            border: 1px solid #000;
            border-radius: 3px;
            font-family: monospace;
        }

        .tracking-item .courier {
            font-size: 10px;
            color: #333;
            margin-top: 3px;
        }

        /* Barcode Section */
        .barcode-section {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            border: 1px dashed #999;
            background: #fafafa;
        }

        .barcode-section .barcode-label {
            font-size: 9px;
            color: #666;
            margin-bottom: 5px;
        }

        .barcode-section svg {
            max-width: 100%;
            height: 50px;
        }

        .barcode-section .barcode-text {
            font-family: monospace;
            font-size: 12px;
            margin-top: 5px;
            letter-spacing: 2px;
        }

        /* Address Sections */
        .addresses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .address-box {
            border: 1px solid #000;
            padding: 8px;
            border-radius: 5px;
        }

        .address-box.sender {
            background: #f0f0f0;
        }

        .address-box.receiver {
            background: #fff3cd;
        }

        .address-box h4 {
            font-size: 10px;
            border-bottom: 1px solid #999;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .address-box p {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .address-box .name {
            font-weight: bold;
            font-size: 11px;
        }

        .address-box .phone {
            font-family: monospace;
            direction: ltr;
        }

        /* Items Table */
        .items-section {
            margin-bottom: 10px;
        }

        .items-section h4 {
            font-size: 11px;
            margin-bottom: 5px;
            padding: 3px;
            background: #eee;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #999;
            padding: 4px 6px;
            text-align: right;
        }

        .items-table th {
            background: #f5f5f5;
            font-weight: 500;
        }

        .items-table .number {
            width: 25px;
            text-align: center;
        }

        .items-table .sku {
            width: 80px;
            font-family: monospace;
            font-size: 8px;
        }

        .items-table .qty {
            width: 35px;
            text-align: center;
        }

        .items-table .barcode-cell {
            width: 80px;
            text-align: center;
        }

        .items-table .barcode-cell svg {
            height: 20px;
            width: 100%;
        }

        /* Package Info */
        .package-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            margin-bottom: 10px;
            padding: 8px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .package-item {
            text-align: center;
        }

        .package-item .label {
            font-size: 9px;
            color: #666;
        }

        .package-item .value {
            font-size: 12px;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 8px;
            border-top: 1px solid #999;
            font-size: 9px;
            color: #666;
        }

        /* Print Styles */
        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .shipping-label {
                border: 2px solid #000;
                margin: 0;
            }
        }

        /* Print Buttons */
        .print-buttons {
            position: fixed;
            top: 10px;
            left: 10px;
            display: flex;
            gap: 10px;
        }

        .print-btn {
            padding: 8px 15px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }

        .print-btn:hover {
            background: #555;
        }

        .print-btn.secondary {
            background: #666;
        }

        /* Full Page Invoice Styles */
        .full-invoice {
            max-width: 21cm;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            display: none;
        }

        .full-invoice.active {
            display: block;
        }

        .shipping-label.active {
            display: block;
        }

        /* Products with Barcode */
        .product-barcode-section {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .product-barcode-section h4 {
            font-size: 11px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .product-barcode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }

        .product-barcode-item {
            text-align: center;
            padding: 8px;
            border: 1px solid #eee;
            border-radius: 3px;
            background: #fafafa;
        }

        .product-barcode-item .product-name {
            font-size: 9px;
            margin-bottom: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-barcode-item svg {
            height: 30px;
            width: 100%;
        }

        .product-barcode-item .sku-text {
            font-family: monospace;
            font-size: 8px;
            margin-top: 3px;
        }

        /* No Tracking Code Message */
        .no-tracking {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="print-buttons no-print">
        <button onclick="window.print()" class="print-btn">چاپ فاکتور</button>
        <a href="{{ route('warehouse.orders.print', $order) }}" class="print-btn secondary">فاکتور فروش</a>
        <a href="{{ route('warehouse.orders.show', $order) }}" class="print-btn secondary">بازگشت</a>
    </div>

    <div class="shipping-label">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <h1>{{ config('app.name', 'فروشگاه') }}</h1>
                <span class="amadast-badge">آمادست</span>
            </div>
            <div class="order-info-box">
                <div class="order-number">#{{ $order->order_number }}</div>
                <div>{{ $order->date_created?->format('Y/m/d') }}</div>
                <div>{{ $order->date_created?->format('H:i') }}</div>
            </div>
        </div>

        <!-- Tracking Codes Section -->
        <div class="tracking-section">
            <h3>کدهای رهگیری</h3>
            @if($order->amadast_tracking_code || $order->courier_tracking_code || $order->tracking_code)
            <div class="tracking-grid">
                @if($order->amadast_tracking_code)
                <div class="tracking-item">
                    <div class="label">کد رهگیری آمادست</div>
                    <div class="code">{{ $order->amadast_tracking_code }}</div>
                </div>
                @endif
                @if($order->courier_tracking_code)
                <div class="tracking-item">
                    <div class="label">کد رهگیری پیک</div>
                    <div class="code">{{ $order->courier_tracking_code }}</div>
                    @if($order->courier_title)
                    <div class="courier">{{ $order->courier_title }}</div>
                    @endif
                </div>
                @endif
                @if($order->tracking_code && !$order->courier_tracking_code)
                <div class="tracking-item">
                    <div class="label">کد رهگیری</div>
                    <div class="code">{{ $order->tracking_code }}</div>
                    @if($order->shipping_carrier)
                    <div class="courier">{{ $order->shipping_carrier }}</div>
                    @endif
                </div>
                @endif
            </div>
            @else
            <div class="no-tracking">
                کد رهگیری هنوز صادر نشده است
            </div>
            @endif
        </div>

        <!-- Order Barcode -->
        <div class="barcode-section">
            <div class="barcode-label">بارکد سفارش</div>
            <svg id="order-barcode"></svg>
            <div class="barcode-text">{{ $order->order_number }}</div>
        </div>

        <!-- Addresses -->
        <div class="addresses">
            <div class="address-box sender">
                <h4>فرستنده</h4>
                <p class="name">{{ config('app.name', 'فروشگاه') }}</p>
                <p>{{ \App\Models\Setting::get('shop_address', 'تهران') }}</p>
                <p class="phone">{{ \App\Models\Setting::get('shop_phone', '') }}</p>
            </div>
            <div class="address-box receiver">
                <h4>گیرنده</h4>
                <p class="name">{{ $order->customer_full_name }}</p>
                <p>{{ $order->shipping_address ?: $order->billing_address }}</p>
                @if($order->shipping_postcode || $order->billing_postcode)
                <p>کدپستی: {{ $order->shipping_postcode ?? $order->billing_postcode }}</p>
                @endif
                <p class="phone">{{ $order->billing_phone ?? $order->customer_phone }}</p>
            </div>
        </div>

        <!-- Package Info -->
        <div class="package-info">
            <div class="package-item">
                <div class="label">تعداد اقلام</div>
                <div class="value">{{ $order->items_count }} عدد</div>
            </div>
            <div class="package-item">
                <div class="label">مبلغ کل</div>
                <div class="value">{{ $order->formatted_total }}</div>
            </div>
            <div class="package-item">
                <div class="label">نوع پرداخت</div>
                <div class="value">{{ $order->payment_method_title ?: 'نامشخص' }}</div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="items-section">
            <h4>اقلام سفارش</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="number">#</th>
                        <th>محصول</th>
                        <th class="sku">SKU</th>
                        <th class="qty">تعداد</th>
                        <th class="barcode-cell">بارکد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                    <tr>
                        <td class="number">{{ $index + 1 }}</td>
                        <td>
                            {{ Str::limit($item->name, 30) }}
                            @if($item->variation_text)
                            <br><small>{{ $item->variation_text }}</small>
                            @endif
                        </td>
                        <td class="sku">{{ $item->sku ?? '-' }}</td>
                        <td class="qty">{{ $item->quantity }}</td>
                        <td class="barcode-cell">
                            @if($item->sku)
                            <svg class="item-barcode" data-code="{{ $item->sku }}"></svg>
                            @else
                            -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Product Barcodes Section (Larger for scanning) -->
        <div class="product-barcode-section">
            <h4>بارکد محصولات (برای اسکن)</h4>
            <div class="product-barcode-grid">
                @foreach($order->items as $item)
                @if($item->sku)
                <div class="product-barcode-item">
                    <div class="product-name">{{ Str::limit($item->name, 20) }}</div>
                    <svg class="product-barcode" data-code="{{ $item->sku }}"></svg>
                    <div class="sku-text">{{ $item->sku }}</div>
                </div>
                @endif
                @endforeach
            </div>
        </div>

        @if($order->customer_note)
        <div style="margin-top: 10px; padding: 8px; background: #ffe6e6; border: 1px solid #ffcccc; border-radius: 5px;">
            <strong style="font-size: 10px;">یادداشت مشتری:</strong>
            <p style="font-size: 10px; margin-top: 3px;">{{ $order->customer_note }}</p>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>تاریخ چاپ: {{ now()->format('Y/m/d H:i') }}</p>
            @if($order->amadast_order_id)
            <p>شناسه آمادست: {{ $order->amadast_order_id }}</p>
            @endif
        </div>
    </div>

    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate order barcode
            const orderBarcode = document.getElementById('order-barcode');
            if (orderBarcode) {
                try {
                    JsBarcode(orderBarcode, "{{ $order->order_number }}", {
                        format: "CODE128",
                        width: 2,
                        height: 50,
                        displayValue: false,
                        margin: 5
                    });
                } catch (e) {
                    console.error('Order barcode error:', e);
                }
            }

            // Generate item barcodes in table
            document.querySelectorAll('.item-barcode').forEach(function(svg) {
                const code = svg.getAttribute('data-code');
                if (code) {
                    try {
                        JsBarcode(svg, code, {
                            format: "CODE128",
                            width: 1,
                            height: 20,
                            displayValue: false,
                            margin: 0
                        });
                    } catch (e) {
                        console.error('Item barcode error:', e);
                    }
                }
            });

            // Generate product barcodes (larger, for scanning)
            document.querySelectorAll('.product-barcode').forEach(function(svg) {
                const code = svg.getAttribute('data-code');
                if (code) {
                    try {
                        JsBarcode(svg, code, {
                            format: "CODE128",
                            width: 1.5,
                            height: 30,
                            displayValue: false,
                            margin: 2
                        });
                    } catch (e) {
                        console.error('Product barcode error:', e);
                    }
                }
            });
        });
    </script>
</body>
</html>
