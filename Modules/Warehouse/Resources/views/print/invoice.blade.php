<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; font-size: 12px; color: #333; }
        .invoice { max-width: 800px; margin: 0 auto; border: 2px solid #333; padding: 20px; }

        /* Header */
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 15px; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .header-logo { width: 50px; height: 50px; object-fit: contain; }
        .header-title { font-size: 22px; font-weight: bold; }
        .header-subtitle { font-size: 11px; color: #666; margin-top: 2px; }
        .header-left { text-align: left; font-size: 11px; color: #555; }
        .header-left .order-num { font-size: 14px; font-weight: bold; color: #333; }

        /* Sender / Receiver */
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .party { padding: 10px 12px; }
        .party:first-child { border-left: 1px solid #ddd; }
        .party-label { font-weight: bold; font-size: 12px; margin-bottom: 6px; color: #111; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .party-row { display: flex; gap: 5px; margin-bottom: 3px; font-size: 11px; line-height: 1.6; }
        .party-key { font-weight: bold; min-width: 50px; color: #555; }
        .party-val { color: #222; }

        /* Order Info */
        .order-info { display: flex; justify-content: space-between; margin-bottom: 12px; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; font-size: 11px; }
        .order-info-item { display: flex; gap: 4px; }
        .order-info-label { font-weight: bold; color: #555; }

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 7px 10px; text-align: right; font-size: 11px; }
        .items-table th { background: #f0f0f0; font-weight: bold; font-size: 11px; }
        .items-table tr:nth-child(even) { background: #fafafa; }
        .items-table .total-row { background: #f0f0f0; font-weight: bold; }

        /* Notes */
        .notes { background: #f9f9f9; padding: 8px 10px; border-radius: 4px; margin-top: 8px; font-size: 11px; border-right: 3px solid #999; }

        /* Barcode */
        .barcode-section { text-align: center; margin-top: 15px; padding-top: 12px; border-top: 2px solid #333; }
        .barcode-section svg { max-width: 220px; }

        /* Top bar for screen */
        .top-bar { position: fixed; top: 0; left: 0; right: 0; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; display: flex; align-items: center; gap: 10px; z-index: 100; }
        .print-btn { padding: 8px 18px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: Tahoma; }
        .print-btn:hover { background: #2563eb; }
        .print-count-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-family: Tahoma; font-weight: bold; }
        .invoice { margin-top: 60px; }
        @media print {
            .top-bar, .no-print { display: none !important; }
            .invoice { margin-top: 0; border: none; padding: 0; }
            body { padding: 10px; }
        }
    </style>
</head>
<body>
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
        {{-- Header with logo and store name --}}
        <div class="header">
            <div class="header-right">
                @if(!empty($invoiceSettings['logo']))
                    <img src="{{ asset('storage/' . $invoiceSettings['logo']) }}" alt="Logo" class="header-logo">
                @endif
                <div>
                    <div class="header-title">{{ $invoiceSettings['store_name'] }}</div>
                    <div class="header-subtitle">{{ $invoiceSettings['subtitle'] }}</div>
                </div>
            </div>
            <div class="header-left">
                <div class="order-num">{{ $order->order_number }}</div>
                <div>{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</div>
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
                @if($order->customer_mobile)
                <div class="party-row">
                    <span class="party-key">تلفن:</span>
                    <span class="party-val" dir="ltr">{{ $order->customer_mobile }}</span>
                </div>
                @endif
                @php
                    $wcData = $order->wc_order_data;
                    $receiverAddress = '';
                    if (is_array($wcData)) {
                        $shipping = $wcData['shipping'] ?? $wcData['billing'] ?? [];
                        $parts = array_filter([
                            $shipping['state'] ?? '',
                            $shipping['city'] ?? '',
                            $shipping['address_1'] ?? '',
                            $shipping['address_2'] ?? '',
                        ]);
                        $receiverAddress = implode('، ', $parts);
                        if (empty($receiverAddress) && !empty($wcData['customer_note'])) {
                            $receiverAddress = $wcData['customer_note'];
                        }
                    }
                @endphp
                @if(!empty($receiverAddress))
                <div class="party-row">
                    <span class="party-key">آدرس:</span>
                    <span class="party-val">{{ $receiverAddress }}</span>
                </div>
                @endif
                @if(is_array($wcData) && !empty($wcData['shipping']['postcode'] ?? $wcData['billing']['postcode'] ?? ''))
                <div class="party-row">
                    <span class="party-key">کدپستی:</span>
                    <span class="party-val" dir="ltr">{{ $wcData['shipping']['postcode'] ?? $wcData['billing']['postcode'] ?? '' }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Order Info Bar --}}
        <div class="order-info">
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
                <span>{{ $order->total_weight }} کیلوگرم</span>
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
                    <th style="width:30px">#</th>
                    <th>نام محصول</th>
                    <th style="width:70px">SKU</th>
                    <th style="width:50px">تعداد</th>
                    <th style="width:65px">وزن (kg)</th>
                    <th style="width:100px">مبلغ (تومان)</th>
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

        @if($order->description)
        <div class="notes">
            <strong>توضیحات:</strong> {{ $order->description }}
        </div>
        @endif

        @if($order->notes)
        <div class="notes" style="margin-top: 5px;">
            <strong>یادداشت:</strong> {{ $order->notes }}
        </div>
        @endif

        <div class="barcode-section">
            <svg id="barcode"></svg>
            <p style="margin-top: 4px; font-size: 10px; color: #888;">{{ $order->barcode }}</p>
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
