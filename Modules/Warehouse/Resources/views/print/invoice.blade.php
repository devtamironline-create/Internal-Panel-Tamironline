<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش {{ $order->order_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; font-size: 12px; color: #333; }
        .invoice { max-width: 800px; margin: 0 auto; border: 1px solid #ccc; }

        /* Header */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 8px 20px; background: #f8f8f8; border-bottom: 2px solid #ddd; }
        .header-right { display: flex; align-items: center; }
        .header-logo { width: 80px; height: 80px; object-fit: contain; }
        .header-left { display: flex; align-items: center; }
        .header-title { font-size: 13px; font-weight: 500; color: #555; }
        .header-order-num { font-size: 12px; font-weight: bold; color: #444; }
        .header-date { font-size: 10px; color: #999; margin-top: 2px; }

        /* Parties (Sender/Receiver) */
        .parties { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #ddd; }
        .party { padding: 12px 20px; }
        .party:first-child { border-left: 1px solid #ddd; }
        .party-label { font-weight: bold; font-size: 13px; color: #222; margin-bottom: 8px; }
        .party-row { display: flex; gap: 5px; margin-bottom: 4px; font-size: 11px; line-height: 1.7; }
        .party-key { font-weight: bold; min-width: 45px; color: #666; }
        .party-val { color: #222; }

        /* Order Info Bar */
        .order-info { display: flex; justify-content: space-around; padding: 6px 20px; background: #fafafa; border-bottom: 1px solid #ddd; font-size: 11px; }
        .order-info-item { display: flex; gap: 4px; }
        .order-info-label { font-weight: bold; color: #666; }

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #eee; padding: 7px 12px; text-align: right; font-size: 11px; }
        .items-table th { background: #f5f5f5; font-weight: bold; font-size: 10px; color: #555; border-bottom: 1px solid #ddd; }
        .items-table tr:nth-child(even) { background: #fcfcfc; }
        .items-table .total-row { background: #f5f5f5; font-weight: bold; border-top: 1px solid #ddd; }
        .items-table .total-row td { padding: 8px 12px; }

        /* Notes */
        .notes-section { padding: 10px 20px; border-top: 1px solid #eee; }
        .notes { background: #fafafa; padding: 6px 10px; border-radius: 3px; margin-bottom: 5px; font-size: 11px; border-right: 3px solid #ccc; }

        /* Barcode */
        .barcode-section { text-align: center; padding: 12px 20px; border-top: 1px solid #ddd; }
        .barcode-section svg { max-width: 200px; }
        .barcode-code { font-size: 9px; color: #aaa; margin-top: 3px; }

        /* Top bar (screen only) */
        .top-bar { position: fixed; top: 0; left: 0; right: 0; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; display: flex; align-items: center; gap: 10px; z-index: 100; }
        .print-btn { padding: 8px 18px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: 'Vazirmatn', Tahoma; }
        .print-btn:hover { background: #2563eb; }
        .print-count-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-family: 'Vazirmatn', Tahoma; font-weight: bold; }
        .invoice { margin-top: 60px; }

        @media print {
            .top-bar, .no-print { display: none !important; }
            .invoice { margin-top: 0; }
            body { padding: 5px; }
        }
    </style>
</head>
<body>
    @php
        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        $shipping = $wcData['shipping'] ?? [];
        $billing = $wcData['billing'] ?? [];

        // Receiver address
        $addrParts = array_filter([
            $shipping['state'] ?? $billing['state'] ?? '',
            $shipping['city'] ?? $billing['city'] ?? '',
            $shipping['address_1'] ?? $billing['address_1'] ?? '',
            $shipping['address_2'] ?? $billing['address_2'] ?? '',
        ]);
        $receiverAddress = implode('، ', $addrParts);

        // Fallback to customer_note
        if (empty($receiverAddress) && !empty($wcData['customer_note'])) {
            $receiverAddress = $wcData['customer_note'];
        }

        $receiverPostcode = $shipping['postcode'] ?? $billing['postcode'] ?? '';
        $receiverPhone = $order->customer_mobile ?: ($billing['phone'] ?? '');
    @endphp

    <div class="top-bar">
        <button class="print-btn" onclick="handlePrint()">چاپ فاکتور</button>
        @if($order->print_count > 1)
        <span class="print-count-badge">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ $order->print_count }} بار چاپ شده
        </span>
        @endif
    </div>

    <div class="invoice">
        {{-- Header --}}
        <div class="header">
            <div class="header-right">
                @if(!empty($invoiceSettings['logo']))
                    <img src="{{ asset('storage/' . $invoiceSettings['logo']) }}" alt="Logo" class="header-logo">
                @endif
            </div>
            <div class="header-left">
                <div class="header-title">{{ $invoiceSettings['store_name'] }}</div>
            </div>
        </div>

        {{-- Sender / Receiver --}}
        <div class="parties">
            <div class="party">
                <div class="party-label">فرستنده</div>
                <div class="party-row">
                    <span class="party-key">نام:</span>
                    <span class="party-val">{{ $invoiceSettings['store_name'] }}</span>
                </div>
                @if(!empty($invoiceSettings['sender_phone']))
                <div class="party-row">
                    <span class="party-key">تلفن:</span>
                    <span class="party-val" dir="ltr">{{ $invoiceSettings['sender_phone'] }}</span>
                </div>
                @endif
                @if(!empty($invoiceSettings['sender_address']))
                <div class="party-row">
                    <span class="party-key">آدرس:</span>
                    <span class="party-val">{{ $invoiceSettings['sender_address'] }}</span>
                </div>
                @endif
            </div>
            <div class="party">
                <div class="party-label">گیرنده</div>
                <div class="party-row">
                    <span class="party-key">نام:</span>
                    <span class="party-val">{{ $order->customer_name }}</span>
                </div>
                @if(!empty($receiverPhone))
                <div class="party-row">
                    <span class="party-key">تلفن:</span>
                    <span class="party-val" dir="ltr">{{ $receiverPhone }}</span>
                </div>
                @endif
                @if(!empty($receiverAddress))
                <div class="party-row">
                    <span class="party-key">آدرس:</span>
                    <span class="party-val">{{ $receiverAddress }}</span>
                </div>
                @endif
                @if(!empty($receiverPostcode))
                <div class="party-row">
                    <span class="party-key">کدپستی:</span>
                    <span class="party-val" dir="ltr">{{ $receiverPostcode }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Order Info --}}
        <div class="order-info">
            <div class="order-info-item">
                <span class="order-info-label">شماره سفارش:</span>
                <span>{{ $order->order_number }}</span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">تاریخ:</span>
                <span>{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">نوع ارسال:</span>
                <span>
                    @switch($order->shipping_type)
                        @case('courier') پیک @break
                        @case('urgent') فوری @break
                        @case('emergency') اضطراری @break
                        @default پست
                    @endswitch
                </span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">وزن کل:</span>
                <span>{{ $order->total_weight }} kg</span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">تعداد اقلام:</span>
                <span>{{ $order->items ? $order->items->sum('quantity') : 0 }}</span>
            </div>
        </div>

        {{-- Items Table --}}
        @if($order->items && $order->items->count() > 0)
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th>نام محصول</th>
                    <th style="width:65px">SKU</th>
                    <th style="width:45px">تعداد</th>
                    <th style="width:55px">وزن</th>
                    <th style="width:95px">مبلغ (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalPrice = 0; @endphp
                @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td dir="ltr">{{ $item->product_sku ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->weight }}</td>
                    <td>{{ number_format($item->price) }}</td>
                </tr>
                @php $totalPrice += $item->price * $item->quantity; @endphp
                @endforeach
                <tr class="total-row">
                    <td colspan="5" style="text-align: left;">جمع کل:</td>
                    <td>{{ number_format($totalPrice) }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        {{-- Notes --}}
        @if($order->description || $order->notes)
        <div class="notes-section">
            @if($order->description)
            <div class="notes"><strong>توضیحات:</strong> {{ $order->description }}</div>
            @endif
            @if($order->notes)
            <div class="notes"><strong>یادداشت:</strong> {{ $order->notes }}</div>
            @endif
        </div>
        @endif

        {{-- Barcode --}}
        <div class="barcode-section">
            <svg id="barcode"></svg>
            <p class="barcode-code">{{ $order->barcode }}</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        JsBarcode("#barcode", "{{ $order->barcode }}", {
            format: "CODE128",
            width: 2,
            height: 50,
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
    </script>
</body>
</html>
