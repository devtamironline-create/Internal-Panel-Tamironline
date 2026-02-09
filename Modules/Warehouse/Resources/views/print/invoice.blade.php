<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; font-size: 12px; }
        .invoice { max-width: 800px; margin: 0 auto; border: 2px solid #333; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 15px; }
        .header h1 { font-size: 20px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 11px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #999; }
        .info-item { display: flex; gap: 5px; }
        .info-label { font-weight: bold; min-width: 80px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        .items-table th { background: #f5f5f5; font-weight: bold; }
        .items-table tr:nth-child(even) { background: #fafafa; }
        .barcode-section { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #333; }
        .barcode-section svg { max-width: 250px; }
        .notes { background: #f9f9f9; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 11px; }
        .top-bar { position: fixed; top: 0; left: 0; right: 0; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; display: flex; align-items: center; gap: 10px; z-index: 100; }
        .print-btn { padding: 8px 18px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: Tahoma; }
        .print-btn:hover { background: #2563eb; }
        .mark-printed-btn { padding: 8px 18px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: Tahoma; }
        .mark-printed-btn:hover { background: #059669; }
        .print-count-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-family: Tahoma; font-weight: bold; }
        .invoice { margin-top: 60px; }
        @media print { .top-bar, .no-print { display: none !important; } .invoice { margin-top: 0; } }
    </style>
</head>
<body>
    <div class="top-bar">
        <button class="print-btn" onclick="handlePrint()">چاپ فاکتور</button>
        <button class="mark-printed-btn" onclick="markPrinted()">تایید پرینت شد</button>
        @if($order->print_count > 1)
        <span class="print-count-badge">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ $order->print_count }} بار چاپ شده
        </span>
        @endif
    </div>

    <div class="invoice">
        <div class="header">
            <h1>تعمیرآنلاین</h1>
            <p>فاکتور سفارش انبار</p>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">شماره سفارش:</span>
                <span>{{ $order->order_number }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">تاریخ:</span>
                <span>{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">مشتری:</span>
                <span>{{ $order->customer_name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">موبایل:</span>
                <span dir="ltr">{{ $order->customer_mobile ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">نوع ارسال:</span>
                <span>{{ $order->shipping_type === 'courier' ? 'پیک' : 'پست' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">وزن کل:</span>
                <span>{{ $order->total_weight }} کیلوگرم</span>
            </div>
        </div>

        @if($order->items && $order->items->count() > 0)
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نام محصول</th>
                    <th>SKU</th>
                    <th>تعداد</th>
                    <th>وزن (kg)</th>
                    <th>مبلغ (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td dir="ltr">{{ $item->product_sku ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->weight }}</td>
                    <td>{{ number_format($item->price) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($order->description)
        <div class="notes">
            <strong>توضیحات:</strong> {{ $order->description }}
        </div>
        @endif

        @if($order->notes)
        <div class="notes">
            <strong>یادداشت:</strong> {{ $order->notes }}
        </div>
        @endif

        <div class="barcode-section">
            <svg id="barcode"></svg>
            <p style="margin-top: 5px; font-size: 11px; color: #666;">{{ $order->barcode }}</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        JsBarcode("#barcode", "{{ $order->barcode }}", {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: false,
        });

        function handlePrint() {
            @if($order->print_count > 1)
            if (!confirm('این فاکتور قبلا {{ $order->print_count - 1 }} بار چاپ شده. مطمئنی میخوای دوباره چاپ کنی؟')) {
                return;
            }
            @endif
            window.print();
        }

        function markPrinted() {
            fetch('{{ route("warehouse.print.mark-printed", $order) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('سفارش به مرحله آماده‌سازی منتقل شد.');
                    window.print();
                }
            });
        }
    </script>
</body>
</html>
