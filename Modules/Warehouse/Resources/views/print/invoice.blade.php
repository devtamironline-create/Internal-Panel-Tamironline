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
        .header { display: flex; align-items: center; justify-content: space-between; background: #fff; border-bottom: 1px solid #ddd; padding: 5px 0; }
        .header-right { display: flex; align-items: center; padding: 0 15px; }
        .header-logo { width: 140px; height: auto; max-height: 120px; object-fit: contain; }
        .header-left { }
        .info-table { border-collapse: collapse; font-size: 11px; color: #444; }
        .info-table td { padding: 0 10px; }
        .info-table tr:not(:last-child) td { border-bottom: 1px solid #ddd; }
        .info-table .info-label { font-weight: bold; color: #666; white-space: nowrap; width: 80px; border-left: 1px solid #ddd; }
        .info-table .info-val { color: #222; }
        .store-name { text-align: center; padding: 6px 20px; font-size: 13px; font-weight: 500; color: #555; border-bottom: 1px solid #ddd; }

        /* Parties (Sender/Receiver) */
        .parties { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #ddd; }
        .party { padding: 12px 20px; }
        .party:first-child { border-left: 1px solid #ddd; }
        .party-label { font-weight: bold; font-size: 13px; color: #222; margin-bottom: 8px; }
        .party-row { display: flex; gap: 5px; margin-bottom: 4px; font-size: 11px; line-height: 1.7; }
        .party-key { font-weight: bold; min-width: 45px; color: #666; }
        .party-val { color: #222; }

        /* Order Info Bar - removed, moved to header */

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #eee; padding: 7px 12px; text-align: right; font-size: 11px; }
        .items-table th { background: #fff; font-weight: bold; font-size: 10px; color: #555; border-bottom: 1px solid #ddd; }
        .items-table tr:nth-child(even) { background: #fff; }
        .items-table .total-row { background: #fff; font-weight: bold; border-top: 1px solid #ddd; }
        .items-table .total-row td { padding: 8px 12px; }

        /* Notes */
        .notes-section { padding: 10px 20px; border-top: 1px solid #eee; }
        .notes { background: #fff; padding: 6px 10px; border-radius: 3px; margin-bottom: 5px; font-size: 11px; border-right: 3px solid #ccc; }

        /* Barcode */
        .barcode-section { display: flex; justify-content: center; gap: 40px; padding: 12px 20px; border-top: 1px solid #ddd; }
        .barcode-item { text-align: center; }
        .barcode-item svg, .barcode-item canvas { max-width: 200px; }
        .barcode-label { font-size: 11px; font-weight: bold; color: #444; margin-top: 5px; }
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

        // Receiver address - اول shipping بعد billing (با ?: به‌جای ?? چون رشته خالی فالبک نمیزنه)
        $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
        $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
        $address1 = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
        $address2 = ($shipping['address_2'] ?? '') ?: ($billing['address_2'] ?? '');

        // جستجو در meta_data اگه آدرس خالی بود
        $metaData = collect($wcData['meta_data'] ?? []);
        if (empty($address1)) {
            $meta = $metaData->firstWhere('key', '_shipping_address_1');
            if (!$meta) $meta = $metaData->firstWhere('key', '_billing_address_1');
            $address1 = $meta['value'] ?? '';
        }
        if (empty($city)) {
            $meta = $metaData->firstWhere('key', '_shipping_city');
            if (!$meta) $meta = $metaData->firstWhere('key', '_billing_city');
            $city = $meta['value'] ?? '';
        }
        if (empty($state)) {
            $meta = $metaData->firstWhere('key', '_shipping_state');
            if (!$meta) $meta = $metaData->firstWhere('key', '_billing_state');
            $state = $meta['value'] ?? '';
        }

        $addrParts = array_filter([$state, $city, $address1, $address2]);
        $receiverAddress = implode('، ', $addrParts);

        // Fallback to customer_note
        if (empty($receiverAddress) && !empty($wcData['customer_note'])) {
            $receiverAddress = $wcData['customer_note'];
        }

        $receiverPostcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');
        if (empty($receiverPostcode)) {
            // جستجوی گسترده در meta_data برای کدپستی
            $postcodeKeys = ['_shipping_postcode', '_billing_postcode', 'billing_postcode', 'shipping_postcode', '_postcode', 'postcode'];
            foreach ($postcodeKeys as $key) {
                $meta = $metaData->firstWhere('key', $key);
                if ($meta && !empty($meta['value'])) {
                    $receiverPostcode = $meta['value'];
                    break;
                }
            }
        }
        // آخرین تلاش: جستجو در هر meta که مقدارش شبیه کدپستی باشه (۱۰ رقم)
        if (empty($receiverPostcode)) {
            $postcodeMeta = $metaData->first(function ($m) {
                return str_contains($m['key'] ?? '', 'postcode') || str_contains($m['key'] ?? '', 'postal');
            });
            if ($postcodeMeta && !empty($postcodeMeta['value'])) {
                $receiverPostcode = $postcodeMeta['value'];
            }
        }

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
                <table class="info-table">
                    <tr><td class="info-label">شماره سفارش:</td><td class="info-val">{{ $order->order_number }}</td></tr>
                    <tr><td class="info-label">تاریخ:</td><td class="info-val">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</td></tr>
                    <tr><td class="info-label">نوع ارسال:</td><td class="info-val">@switch($order->shipping_type)@case('courier') پیک @break @case('urgent') فوری @break @case('emergency') اضطراری @break @default پست @endswitch</td></tr>
                    <tr><td class="info-label">وزن کل:</td><td class="info-val">{{ $order->total_weight }} kg</td></tr>
                </table>
            </div>
        </div>
        <div class="store-name">{{ $invoiceSettings['store_name'] }}</div>

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
                @php
                    $totalPrice = 0;
                    $shippingTotal = floatval($wcData['shipping_total'] ?? 0);
                    $shippingMethod = '';
                    if (!empty($wcData['shipping_lines'])) {
                        $shippingMethod = $wcData['shipping_lines'][0]['method_title'] ?? '';
                    }
                @endphp
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
                @if($shippingTotal > 0)
                <tr style="border-top: 1px solid #ddd;">
                    <td colspan="5" style="text-align: left;">هزینه ارسال{{ $shippingMethod ? ' (' . $shippingMethod . ')' : '' }}:</td>
                    <td>{{ number_format($shippingTotal) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td colspan="5" style="text-align: left;">جمع کل:</td>
                    <td>{{ number_format($totalPrice + $shippingTotal) }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        {{-- Barcodes Section --}}
        <div class="barcode-section">
            <div class="barcode-item">
                <svg id="barcode"></svg>
                <p class="barcode-label">بارکد سفارش</p>
                <p class="barcode-code">{{ $order->barcode }}</p>
            </div>

            @if($order->shipping_type === 'post' && !empty($order->tracking_code))
            <div class="barcode-item">
                <div id="qrcode"></div>
                <p class="barcode-label">کد رهگیری پست</p>
                <p class="barcode-code">{{ $order->tracking_code }}</p>
            </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    @if($order->shipping_type === 'post' && !empty($order->tracking_code))
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    @endif
    <script>
        JsBarcode("#barcode", "{{ $order->barcode }}", {
            format: "CODE128",
            width: 2,
            height: 50,
            displayValue: false,
        });

        @if($order->shipping_type === 'post' && !empty($order->tracking_code))
        new QRCode(document.getElementById("qrcode"), {
            text: "{{ $order->tracking_code }}",
            width: 120,
            height: 120,
            correctLevel: QRCode.CorrectLevel.M,
        });
        @endif

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
