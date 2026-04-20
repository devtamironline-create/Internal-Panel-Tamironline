<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش {{ $order->order_number }}</title>
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; direction: rtl; padding: 10px; font-size: 11px; color: #333; }
        .invoice { max-width: 148mm; margin: 0 auto; border: 1px solid #ccc; }

        /* Header */
        .header { display: flex; align-items: center; justify-content: space-between; background: #fff; border-bottom: 1px solid #ddd; padding: 4px 0; }
        .header-right { display: flex; align-items: center; padding: 0 10px; }
        .header-logo { width: 100px; height: auto; max-height: 70px; object-fit: contain; }
        .header-left { }
        .info-table { border-collapse: collapse; font-size: 9px; color: #444; }
        .info-table td { padding: 0 6px; }
        .info-table tr:not(:last-child) td { border-bottom: 1px solid #ddd; }
        .info-table .info-label { font-weight: bold; color: #666; white-space: nowrap; width: 65px; border-left: 1px solid #ddd; }
        .info-table .info-val { color: #222; }
        .store-name { text-align: center; padding: 4px 10px; font-size: 11px; font-weight: 500; color: #555; border-bottom: 1px solid #ddd; }

        /* Parties (Sender/Receiver) */
        .parties { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #ddd; }
        .party { padding: 6px 10px; }
        .party:first-child { border-left: 1px solid #ddd; }
        .party-label { font-weight: bold; font-size: 11px; color: #222; margin-bottom: 4px; }
        .party-row { display: flex; gap: 4px; margin-bottom: 2px; font-size: 9px; line-height: 1.6; }
        .party-key { font-weight: bold; min-width: 35px; color: #666; }
        .party-val { color: #222; }

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #eee; padding: 4px 6px; text-align: right; font-size: 9px; }
        .items-table th { background: #fff; font-weight: bold; font-size: 8px; color: #555; border-bottom: 1px solid #ddd; }
        .items-table tr:nth-child(even) { background: #fff; }
        .items-table .total-row { background: #fff; font-weight: bold; border-top: 1px solid #ddd; }
        .items-table .total-row td { padding: 5px 6px; }

        /* Notes */
        .notes-section { padding: 6px 10px; border-top: 1px solid #eee; }
        .notes { background: #fff; padding: 4px 8px; border-radius: 3px; margin-bottom: 3px; font-size: 9px; border-right: 3px solid #ccc; }

        /* Barcode */
        .barcode-section { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; border-top: 1px solid #ddd; gap: 8px; overflow: hidden; }
        .barcode-list { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .barcode-item { display: flex; align-items: center; gap: 6px; }
        .barcode-item svg { flex-shrink: 0; max-width: 280px; height: auto; }
        .barcode-meta { display: flex; flex-direction: column; flex-shrink: 0; }
        .barcode-label { font-size: 8px; font-weight: bold; color: #444; }
        .barcode-code { font-size: 7px; color: #aaa; }
        .qr-side { flex-shrink: 0; text-align: center; }
        .qr-side .barcode-label { margin-top: 3px; }
        .qr-side .barcode-code { margin-top: 1px; }

        /* Top bar (screen only) */
        .top-bar { position: fixed; top: 0; left: 0; right: 0; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; display: flex; align-items: center; gap: 10px; z-index: 100; }
        .print-btn { padding: 8px 18px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: 'Vazirmatn', Tahoma; }
        .print-btn:hover { background: #2563eb; }
        .print-count-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-family: 'Vazirmatn', Tahoma; font-weight: bold; }
        .invoice { margin-top: 60px; }

        @media print {
            .top-bar, .no-print { display: none !important; }
            .invoice { margin-top: 0; border: none; }
            body { padding: 0; }

            @page {
                size: A5 portrait;
                margin: 5mm;
            }
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
        <button class="print-btn" onclick="handlePrint()" id="printBtn">چاپ فاکتور</button>

        @if($order->print_count > 0)
        <span class="print-count-badge">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            تعداد چاپ: {{ $order->print_count }}
        </span>
        @endif

        @if($reprintRequest && $reprintRequest->status === 'pending')
        <span class="print-count-badge" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            درخواست در انتظار تایید
        </span>
        @elseif($reprintRequest && $reprintRequest->status === 'approved')
        <span class="print-count-badge" style="background:#d1fae5;color:#065f46;border-color:#a7f3d0;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            تایید شده - می‌توانید چاپ کنید
        </span>
        @endif

        @php
            $providerLabel = match($shippingProvider ?? 'amadest') {
                'tapin'  => 'تاپین',
                'postex' => 'پستکس',
                'cod24'  => 'COD24',
                default  => 'آمادست',
            };
        @endphp
        <span style="font-size:11px;color:#888;font-family:'Vazirmatn',Tahoma;">سرویس: {{ $providerLabel }}</span>
        @if($order->shipping_type === 'post' && (empty($order->amadest_barcode) || !empty($registrationError) || ($shippingProvider ?? 'amadest') === 'postex'))
        <button onclick="retryRegister()" id="retryBtn" style="padding:6px 14px;background:#f59e0b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-family:'Vazirmatn',Tahoma;">ثبت مجدد در {{ $providerLabel }}</button>
        @endif
        @if($order->shipping_type === 'post' && !empty($order->amadest_barcode))
        <button onclick="clearAndReRegister()" id="clearBarcodeBtn" style="padding:6px 14px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-family:'Vazirmatn',Tahoma;">پاک کردن بارکد و ثبت مجدد در {{ $providerLabel }}</button>
        @endif
    </div>
    @if(!empty($registrationError))
    <div class="no-print" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:8px 12px;margin:60px 10px 0;border-radius:6px;font-size:11px;">
        <strong>خطا در ثبت سرویس ارسال:</strong> {{ $registrationError }}
    </div>
    @endif

    {{-- باکس اطلاعات COD24 --}}
    @if(($shippingProvider ?? 'amadest') === 'cod24' && $order->shipping_type === 'post')
    @php
        $cod24Data = $order->wc_order_data['cod24'] ?? [];
    @endphp
    <div class="no-print" style="background:#f0f9ff;border:1px solid #7dd3fc;padding:10px 12px;margin:8px 10px 0;border-radius:6px;font-size:11px;font-family:'Vazirmatn',Tahoma;">
        <div style="font-weight:bold;color:#0369a1;margin-bottom:6px;">وضعیت COD24</div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <span>ثبت شده: <strong style="color:{{ !empty($cod24Data['registered']) ? '#16a34a' : '#dc2626' }}">{{ !empty($cod24Data['registered']) ? 'بله' : 'خیر' }}</strong></span>
            <span>سریال: <strong>{{ $cod24Data['barcode'] ?? $order->amadest_barcode ?? '—' }}</strong></span>
            <span>معلق (suspend): <strong style="color:{{ !empty($cod24Data['suspended']) ? '#16a34a' : '#dc2626' }}">{{ !empty($cod24Data['suspended']) ? 'بله' : 'خیر' }}</strong></span>
            <span>بارکد پستی: <strong style="color:{{ !empty($cod24Data['post_barcode']) ? '#16a34a' : '#f59e0b' }}">{{ $cod24Data['post_barcode'] ?? $order->post_tracking_code ?? 'هنوز صادر نشده' }}</strong></span>
        </div>
        @if(!empty($cod24Data['suspend_error']))
        <div style="margin-top:6px;color:#dc2626;background:#fef2f2;padding:4px 8px;border-radius:4px;">
            <strong>خطای suspend:</strong> {{ $cod24Data['suspend_error'] }}
        </div>
        @endif
    </div>
    @endif

    {{-- انتخاب دستی شهر COD24 - وقتی سرویس cod24 هست --}}
    @if(($shippingProvider ?? 'amadest') === 'cod24' && $order->shipping_type === 'post')
    <div class="no-print" id="cod24-city-selector" style="background:#ecfdf5;border:1px solid #6ee7b7;padding:10px 12px;margin:8px 10px 0;border-radius:6px;font-size:11px;">
        @php
            $wcShipping = ($order->wc_order_data['shipping'] ?? []);
            $wcBilling = ($order->wc_order_data['billing'] ?? []);
            $orderCity = ($wcShipping['city'] ?? '') ?: ($wcBilling['city'] ?? '');
            $orderStateCode = ($wcShipping['state'] ?? '') ?: ($wcBilling['state'] ?? '');
            $wcMapLocal = \Modules\Warehouse\Services\TapinService::getWcStateMap();
            $orderStateName = $wcMapLocal[strtoupper($orderStateCode)] ?? $orderStateCode;
        @endphp
        <div style="font-weight:bold;color:#065f46;margin-bottom:6px;">
            انتخاب استان و شهر مقصد COD24
            @if($orderCity)
            <span style="font-weight:normal;color:#666;font-size:10px;margin-right:8px;">
                (اطلاعات سفارش: {{ $orderStateName }} — {{ $orderCity }})
            </span>
            @endif
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:120px;">
                <label style="font-size:10px;color:#666;display:block;margin-bottom:2px;">استان:</label>
                <select id="cod24-province" onchange="loadCod24Cities()" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:'Vazirmatn',Tahoma;">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <div style="flex:2;min-width:180px;">
                <label style="font-size:10px;color:#666;display:block;margin-bottom:2px;">شهر:</label>
                <input type="text" id="cod24-city-filter" oninput="filterCod24Cities()" placeholder="فیلتر نام شهر..." style="width:100%;padding:4px 8px;border:1px solid #d1d5db;border-radius:5px 5px 0 0;font-size:10px;font-family:'Vazirmatn',Tahoma;display:none;" dir="rtl">
                <select id="cod24-city" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:0 0 5px 5px;font-size:11px;font-family:'Vazirmatn',Tahoma;">
                    <option value="">ابتدا استان را انتخاب کنید...</option>
                </select>
            </div>
            <button onclick="retryWithCod24City()" style="padding:5px 14px;background:#059669;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:11px;font-family:'Vazirmatn',Tahoma;white-space:nowrap;">ثبت در COD24</button>
        </div>
        <div id="cod24-city-status" style="font-size:10px;color:#888;margin-top:4px;"></div>
    </div>
    @endif

    {{-- انتخاب استان و شهر پستکس - فقط وقتی سرویس پستکس هست --}}
    @if(($shippingProvider ?? 'amadest') === 'postex' && $order->shipping_type === 'post' && empty($order->amadest_barcode))
    <div class="no-print" id="postex-city-selector" style="background:#f5f3ff;border:1px solid #c4b5fd;padding:10px 12px;margin:8px 10px 0;border-radius:6px;font-size:11px;">
        <div style="font-weight:bold;color:#5b21b6;margin-bottom:6px;">انتخاب استان و شهر مقصد پستکس</div>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:120px;">
                <label style="font-size:10px;color:#666;display:block;margin-bottom:2px;">استان:</label>
                <select id="postex-province" onchange="loadProvinceCities()" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:'Vazirmatn',Tahoma;">
                    <option value="">انتخاب استان...</option>
                    @php
                        $provinces = [
                            1  => 'تهران', 2  => 'هرمزگان', 3  => 'مرکزی', 4  => 'مازندران',
                            5  => 'لرستان', 6  => 'گیلان', 7  => 'گلستان', 8  => 'کهگیلویه',
                            9  => 'کرمانشاه', 10 => 'کرمان', 11 => 'کردستان', 12 => 'قم',
                            13 => 'قزوین', 14 => 'فارس', 15 => 'همدان', 16 => 'سیستان',
                            17 => 'زنجان', 18 => 'خوزستان', 19 => 'خراسان شمالی', 20 => 'خراسان رضوی',
                            21 => 'خراسان جنوبی', 22 => 'چهارمحال', 23 => 'بوشهر', 24 => 'ایلام',
                            25 => 'البرز', 26 => 'اصفهان', 27 => 'اردبیل', 28 => 'آذربایجان غربی',
                            29 => 'آذربایجان شرقی', 30 => 'سمنان', 31 => 'یزد',
                        ];
                        // تبدیل WC state به postex_id برای pre-select
                        $wcToPostex = [
                            'KHZ'=>18,'THR'=>1,'ILM'=>24,'BHR'=>23,'ADL'=>27,'ESF'=>26,'YZD'=>31,
                            'KRH'=>9,'KRN'=>10,'HDN'=>15,'GZN'=>13,'ZJN'=>17,'LRS'=>5,'ABZ'=>25,
                            'EAZ'=>29,'WAZ'=>28,'CHB'=>22,'SKH'=>21,'RKH'=>20,'NKH'=>19,'SMN'=>30,
                            'FRS'=>14,'QHM'=>12,'KRD'=>11,'KBD'=>8,'GLS'=>7,'GIL'=>6,'MZN'=>4,
                            'MKZ'=>3,'HRZ'=>2,'SBN'=>16,
                        ];
                        $orderState = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                        $preSelectedProvince = $wcToPostex[strtoupper($orderState)] ?? null;
                    @endphp
                    @foreach($provinces as $id => $name)
                        <option value="{{ $id }}" {{ $preSelectedProvince == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:2;min-width:180px;">
                <label style="font-size:10px;color:#666;display:block;margin-bottom:2px;">شهر:</label>
                <input type="text" id="postex-city-filter" oninput="filterCityDropdown()" placeholder="فیلتر نام شهر..." style="width:100%;padding:4px 8px;border:1px solid #d1d5db;border-radius:5px 5px 0 0;font-size:10px;font-family:'Vazirmatn',Tahoma;display:none;" dir="rtl">
                <select id="postex-city" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:0 0 5px 5px;font-size:11px;font-family:'Vazirmatn',Tahoma;">
                    <option value="">ابتدا استان را انتخاب کنید...</option>
                </select>
            </div>
            <button onclick="retryWithCity()" id="retryWithCityBtn" style="padding:5px 14px;background:#7c3aed;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:11px;font-family:'Vazirmatn',Tahoma;white-space:nowrap;">ثبت در پستکس</button>
        </div>
        <div id="postex-city-status" style="font-size:10px;color:#888;margin-top:4px;"></div>
    </div>
    @endif

    <div class="invoice">
        @php
            $wcTotal = floatval($wcData['total'] ?? 0);
            $shippingTotal = floatval($wcData['shipping_total'] ?? 0);
            $paymentMethod = $wcData['payment_method_title'] ?? 'نامشخص';
            $customerNote = $wcData['customer_note'] ?? '';
            $invoiceBox = $order->boxSize;
            $boxWeight = $invoiceBox ? $invoiceBox->weight : 0;
            $displayWeight = $order->actual_weight ? $order->actual_weight_grams : ($order->total_weight_grams + $boxWeight);
            $amadestCode = $order->amadest_barcode ?: $order->tracking_code;
            $postCode = $order->post_tracking_code;
            $postBarcodeValue = $postCode ?: $amadestCode;
            $showBarcode = !empty($postBarcodeValue) && $order->shipping_type === 'post';
            $showQR = !empty($postCode) && $order->shipping_type === 'post';
            $showOrderBarcode = $order->shipping_type !== 'post' && !empty($order->barcode);
            $wcMap = \Modules\Warehouse\Services\TapinService::getWcStateMap();
            $statePersian = $wcMap[strtoupper($state)] ?? $state;
        @endphp

        {{-- ═══ ردیف ۱: هدر ═══ --}}
        <div style="text-align:center;padding:8px 12px;border-bottom:2px solid #333;font-size:16px;font-weight:bold;">
            {{ $invoiceSettings['store_name'] }}
        </div>

        {{-- ═══ ردیف ۲: شناسه‌ها (راست) + فرستنده (چپ) ═══ --}}
        <table style="width:100%;border-collapse:collapse;border-bottom:1px solid #ccc;">
            <tr>
                {{-- شناسه‌ها (راست) --}}
                <td style="width:30%;padding:8px 12px;border-left:1px solid #ccc;vertical-align:top;">
                    <table style="width:100%;border-collapse:collapse;font-size:10px;">
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;width:60px;">شناسه سفارش:</td>
                            <td style="font-weight:bold;color:#111;text-align:left;padding:3px 0;">{{ $order->order_number }}</td>
                        </tr>
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;">تاریخ:</td>
                            <td dir="ltr" style="text-align:left;padding:3px 0;">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('H:i Y/m/d') }}</td>
                        </tr>
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;">نوع ارسال:</td>
                            <td style="font-weight:bold;text-align:left;padding:3px 0;">{{ $order->shippingTypeRelation?->name ?? $order->shipping_type ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- فرستنده (چپ) --}}
                <td style="width:70%;padding:8px 12px;vertical-align:top;">
                    <table style="width:100%;border-collapse:collapse;font-size:10px;">
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;width:85px;text-align:right;">فرستنده (فروشگاه):</td>
                            <td colspan="3" style="font-weight:bold;padding:3px 0;">{{ $invoiceSettings['store_name'] }}</td>
                        </tr>
                        @if(!empty($invoiceSettings['sender_province']) || !empty($invoiceSettings['sender_city']))
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;text-align:right;">استان:</td>
                            <td style="padding:3px 0 3px 8px;">{{ $invoiceSettings['sender_province'] ?? '' }}</td>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;text-align:right;width:35px;">شهر:</td>
                            <td style="padding:3px 0;">{{ $invoiceSettings['sender_city'] ?? '' }}</td>
                        </tr>
                        @endif
                        @if(!empty($invoiceSettings['sender_address']))
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;text-align:right;vertical-align:top;">آدرس:</td>
                            <td colspan="3" style="padding:3px 0;line-height:1.6;">{{ $invoiceSettings['sender_address'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($invoiceSettings['sender_postcode']) || !empty($invoiceSettings['sender_phone']))
                        <tr>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;text-align:right;">کدپستی:</td>
                            <td dir="ltr" style="text-align:right;padding:3px 0;">{{ $invoiceSettings['sender_postcode'] ?? '' }}</td>
                            <td style="color:#888;padding:3px 0;white-space:nowrap;text-align:right;">تلفن:</td>
                            <td dir="ltr" style="text-align:right;padding:3px 0;">{{ $invoiceSettings['sender_phone'] ?? '' }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        {{-- ═══ ردیف ۳: جدول محصولات ═══ --}}
        @if($order->items && $order->items->count() > 0)
        <table style="width:100%;border-collapse:collapse;border-bottom:1px solid #ccc;">
            <thead>
                <tr style="background:#f5f5f5;">
                    <th style="border:1px solid #ddd;padding:4px 6px;font-size:8px;text-align:right;">#</th>
                    <th style="border:1px solid #ddd;padding:4px 6px;font-size:8px;text-align:right;">نام کالا</th>
                    <th style="border:1px solid #ddd;padding:4px 6px;font-size:8px;text-align:center;width:40px;">تعداد</th>
                    <th style="border:1px solid #ddd;padding:4px 6px;font-size:8px;text-align:center;width:85px;">قیمت واحد (ریال)</th>
                    <th style="border:1px solid #ddd;padding:4px 6px;font-size:8px;text-align:center;width:85px;">مجموع (ریال)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalPrice = 0; @endphp
                @foreach($order->items as $index => $item)
                @php $lineTotal = $item->price; $unitPrice = $item->quantity > 0 ? $item->price / $item->quantity : $item->price; $totalPrice += $lineTotal; @endphp
                <tr>
                    <td style="border:1px solid #eee;padding:3px 6px;font-size:9px;text-align:center;">{{ $index + 1 }}</td>
                    <td style="border:1px solid #eee;padding:3px 6px;font-size:9px;">{{ $item->product_name }}</td>
                    <td style="border:1px solid #eee;padding:3px 6px;font-size:9px;text-align:center;">{{ $item->quantity }}</td>
                    <td style="border:1px solid #eee;padding:3px 6px;font-size:9px;text-align:center;">{{ number_format($unitPrice) }}</td>
                    <td style="border:1px solid #eee;padding:3px 6px;font-size:9px;text-align:center;">{{ number_format($lineTotal) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="text-align:center;padding:5px 10px;font-size:10px;font-weight:bold;border-bottom:1px solid #ccc;background:#f9f9f9;">
            مجموع مبلغ قابل پرداخت توسط گیرنده (خریدار): {{ number_format($totalPrice + $shippingTotal) }} ریال
        </div>
        <div style="padding:3px 10px;font-size:9px;border-bottom:1px solid #ccc;">
            نوع پرداخت: <strong>{{ $paymentMethod }}</strong>
            @if($shippingTotal > 0) | خدمات پست: {{ number_format($shippingTotal) }} ریال @endif
        </div>
        @endif

        {{-- ═══ ردیف ۴: گیرنده ═══ --}}
        <div style="border-bottom:1px solid #ccc;padding:6px 10px;">
            <table style="width:100%;border-collapse:collapse;font-size:9px;">
                <tr><td style="font-weight:bold;font-size:10px;padding-bottom:4px;" colspan="4">گیرنده (خریدار): <span style="font-size:11px;">{{ $order->customer_name }}</span></td></tr>
                <tr>
                    <td style="font-weight:bold;color:#666;width:40px;padding:2px 0;">استان:</td>
                    <td style="width:35%;font-weight:bold;">{{ $statePersian }}</td>
                    <td style="font-weight:bold;color:#666;width:30px;">شهر:</td>
                    <td style="font-weight:bold;">{{ $city }}</td>
                </tr>
                <tr><td style="font-weight:bold;color:#666;padding:2px 0;">آدرس:</td><td colspan="3">{{ $receiverAddress }}</td></tr>
                <tr>
                    <td style="font-weight:bold;color:#666;padding:2px 0;">کدپستی:</td>
                    <td dir="ltr" style="text-align:right;">{{ $receiverPostcode }}</td>
                    <td style="font-weight:bold;color:#666;">شماره تماس:</td>
                    <td dir="ltr" style="text-align:right;font-weight:bold;">{{ $receiverPhone }}</td>
                </tr>
            </table>
        </div>

        {{-- ═══ ردیف ۵: توضیحات + علت برگشتی ═══ --}}
        <table style="width:100%;border-collapse:collapse;border-bottom:1px solid #ccc;">
            <tr>
                <td style="width:55%;padding:6px 10px;border-left:1px solid #ccc;vertical-align:top;font-size:8px;">
                    <div style="font-weight:bold;font-size:9px;margin-bottom:3px;">توضیحات فروشگاه:</div>
                    <div style="color:#555;">سفارش {{ $order->order_number }}</div>
                    @if(!empty($customerNote))<div style="color:#555;margin-top:2px;">{{ $customerNote }}</div>@endif
                </td>
                <td style="width:45%;padding:6px 10px;vertical-align:top;font-size:8px;">
                    <div style="font-weight:bold;font-size:9px;margin-bottom:3px;">علت برگشتی</div>
                    <div style="color:#888;line-height:1.8;">
                        ☐ عدم اطلاع از سفارش<br>☐ تلفن مقاصد<br>☐ عدم وجود وجه نقد/کارت بانکی<br>☐ مغایرت مبلغ فاکتور<br>☐ مغایرت بارکد/محصول<br>☐ مرجوعی خود مشتری<br>☐ سایر موارد
                    </div>
                </td>
            </tr>
        </table>

        {{-- ═══ ردیف ۶: QR + اطلاعات بسته + امضا ═══ --}}
        <table style="width:100%;border-collapse:collapse;border-bottom:1px solid #ccc;">
            <tr>
                <td style="width:20%;padding:6px 10px;border-left:1px solid #ccc;text-align:center;vertical-align:middle;">
                    @if($showQR || $showOrderBarcode)
                    <div id="qrcode" style="display:inline-block;"></div>
                    @endif
                </td>
                <td style="width:50%;padding:6px 10px;border-left:1px solid #ccc;vertical-align:top;">
                    <table style="font-size:8px;border-collapse:collapse;width:100%;">
                        <tr>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;font-weight:bold;">کارتن</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;font-weight:bold;">وزن</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;font-weight:bold;">تاریخ</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;font-weight:bold;">نوع ارسال</td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;">{{ $invoiceBox->name ?? '-' }}</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;">{{ number_format($displayWeight) }}g</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d') }}</td>
                            <td style="border:1px solid #ddd;padding:3px 5px;text-align:center;">{{ $order->shippingTypeRelation?->name ?? '-' }}</td>
                        </tr>
                    </table>
                    @if(!empty($receiverPhone))
                    <div style="font-size:8px;margin-top:4px;">تلفن مقاصد: <strong dir="ltr">{{ $receiverPhone }}</strong></div>
                    @endif
                </td>
                <td style="width:30%;padding:6px 10px;text-align:center;vertical-align:top;font-size:8px;">
                    <div style="font-weight:bold;margin-bottom:15px;">نام خانوادگی نامه‌رسان و امضاء:</div>
                    <div style="border-bottom:1px dotted #999;height:30px;"></div>
                </td>
            </tr>
        </table>

        {{-- ═══ ردیف ۷: بارکد بزرگ ═══ --}}
        @if($showBarcode || $showOrderBarcode)
        <div style="text-align:center;padding:6px 10px;border-bottom:1px solid #ccc;">
            @if($showBarcode)<svg id="amadest-barcode" style="max-width:100%;"></svg>@endif
            @if($showOrderBarcode)<svg id="order-barcode" style="max-width:100%;"></svg>@endif
            <div style="display:flex;justify-content:space-between;font-size:8px;color:#666;margin-top:3px;padding:0 20px;">
                <span>شناسه سفارش: <strong>{{ $order->order_number }}</strong></span>
                @if(!empty($amadestCode))<span>سریال: <strong>{{ $amadestCode }}</strong></span>@endif
                @if(!empty($postCode))<span>بارکد پستی: <strong>{{ $postCode }}</strong></span>@endif
            </div>
        </div>
        @endif

        {{-- ═══ ردیف ۸: مقصد ═══ --}}
        @if(!empty($statePersian) || !empty($city))
        <div style="text-align:center;padding:8px 12px;background:#111;color:#fff;font-size:14px;font-weight:bold;">
            مقصد: {{ $statePersian }}{{ !empty($city) ? '/' . $city : '' }}
        </div>
        @endif
    </div>

    <script src="{{ asset('js/vendor/JsBarcode.all.min.js') }}"></script>
    @if($showQR)
    <script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
    @endif
    <script>
        @if($showOrderBarcode)
        JsBarcode("#order-barcode", "{{ $order->barcode }}", {
            format: "CODE128",
            width: 2,
            height: 45,
            displayValue: true,
            margin: 2,
            fontSize: 10,
        });
        @endif

        @if($showBarcode)
        JsBarcode("#amadest-barcode", "{{ $postBarcodeValue }}", {
            format: "CODE128",
            width: 2,
            height: 45,
            displayValue: false,
            margin: 2,
        });
        @endif

        @if($showQR)
        new QRCode(document.getElementById("qrcode"), {
            text: "{{ $postCode }}",
            width: 90,
            height: 90,
            correctLevel: QRCode.CorrectLevel.M,
        });
        @endif

        function handlePrint() {
            @if($order->print_count > 0)
            // چاپ مجدد - نیاز به تاییدیه
            var confirmMsg = 'این فاکتور {{ $order->print_count }} بار چاپ شده است.\n\n';

            @if($reprintRequest && $reprintRequest->status === 'approved')
                confirmMsg += '✅ درخواست شما تایید شده. آیا مطمئنید که میخواهید چاپ کنید؟';
            @elseif($reprintRequest && $reprintRequest->status === 'pending')
                alert('⏳ درخواست چاپ مجدد شما در انتظار تایید مدیر است.\n\nلطفاً صبر کنید تا مدیر درخواست شما را بررسی کند.');
                return;
            @else
                confirmMsg += 'برای چاپ مجدد، درخواست شما باید توسط مدیر تایید شود.\n\nآیا میخواهید درخواست چاپ مجدد ثبت کنید؟';
            @endif

            if (!confirm(confirmMsg)) {
                return;
            }
            @endif

            var btn = document.getElementById('printBtn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'در حال پردازش...';
            }

            // ثبت چاپ در سیستم
            fetch('/warehouse/{{ $order->id }}/print/mark-printed', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.type === 'request_submitted') {
                        // درخواست ثبت شد
                        alert('✅ ' + data.message);
                        location.reload();
                    } else if (data.can_print) {
                        // اجازه چاپ داره
                        window.print();
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }
                } else {
                    alert('❌ ' + (data.message || 'خطا در پردازش'));
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'چاپ فاکتور';
                    }
                }
            })
            .catch(function(err) {
                alert('❌ خطا در ارتباط با سرور');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'چاپ فاکتور';
                }
            });
        }

        function retryRegister() {
            // اگه selector پستکس وجود داره و شهر انتخاب شده، از اون استفاده کن
            var citySelect = document.getElementById('postex-city');
            var cityCode = citySelect ? citySelect.value : '';

            // اگه selector COD24 وجود داره و شهر انتخاب شده، از اون استفاده کن
            var cod24CitySelect = document.getElementById('cod24-city');
            var cod24CityCode = cod24CitySelect ? cod24CitySelect.value : '';

            var btn = document.getElementById('retryBtn');
            if (!btn) return;
            btn.disabled = true;
            btn.textContent = 'در حال ثبت...';

            var bodyData = {};
            if (cityCode) {
                bodyData.postex_city_code = parseInt(cityCode);
            }
            if (cod24CityCode) {
                bodyData.cod24_city_code = parseInt(cod24CityCode);
            }

            fetch('/warehouse/{{ $order->id }}/retry-register', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(bodyData),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('موفق! ' + data.message);
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'ثبت مجدد';
                }
            })
            .catch(function() {
                alert('خطا در ارتباط');
                btn.disabled = false;
                btn.textContent = 'ثبت مجدد';
            });
        }

        function clearAndReRegister() {
            if (!confirm('بارکد فعلی پاک شده و سفارش مجدد در {{ $providerLabel }} ثبت می‌شود. ادامه می‌دهید؟')) return;
            var btn = document.getElementById('clearBarcodeBtn');
            if (!btn) return;
            btn.disabled = true;
            btn.textContent = 'در حال پاک‌سازی و ثبت مجدد...';

            fetch('/warehouse/{{ $order->id }}/retry-register', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('موفق! ' + data.message);
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'پاک کردن بارکد و ثبت مجدد';
                }
            })
            .catch(function() {
                alert('خطا در ارتباط');
                btn.disabled = false;
                btn.textContent = 'پاک کردن بارکد و ثبت مجدد';
            });
        }

        // ذخیره همه شهرها برای فیلتر client-side
        var allLoadedCities = [];

        // بارگذاری شهرهای استان انتخاب شده
        function loadProvinceCities() {
            var provinceId = document.getElementById('postex-province').value;
            var citySelect = document.getElementById('postex-city');
            var statusEl = document.getElementById('postex-city-status');
            var filterInput = document.getElementById('postex-city-filter');

            if (!provinceId) {
                citySelect.innerHTML = '<option value="">ابتدا استان را انتخاب کنید...</option>';
                if (filterInput) filterInput.style.display = 'none';
                return;
            }

            citySelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            if (statusEl) statusEl.textContent = 'در حال دریافت شهرها...';

            fetch('/warehouse/postex/cities?province_id=' + provinceId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var cities = data.data || [];
                if (cities.length === 0) {
                    citySelect.innerHTML = '<option value="">شهری یافت نشد</option>';
                    if (statusEl) statusEl.textContent = 'شهری یافت نشد';
                    if (filterInput) filterInput.style.display = 'none';
                    return;
                }

                allLoadedCities = cities;
                var isFallback = data.fallback || false;

                // نمایش فیلتر اگه تعداد زیاده
                if (filterInput) {
                    filterInput.style.display = cities.length > 50 ? 'block' : 'none';
                    filterInput.value = '';
                }

                populateCityDropdown(cities, isFallback);
            })
            .catch(function() {
                citySelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
                if (statusEl) statusEl.textContent = 'خطا در دریافت شهرها';
            });
        }

        function populateCityDropdown(cities, isFallback) {
            var citySelect = document.getElementById('postex-city');
            var statusEl = document.getElementById('postex-city-status');
            var orderCity = @json(($shipping['city'] ?? '') ?: ($billing['city'] ?? ''));

            var html = '<option value="">انتخاب شهر (' + cities.length + ' شهر)...</option>';
            var autoSelected = false;

            cities.forEach(function(c) {
                var code = c.code || c.id || '';
                var name = c.name || c.title || '';
                var selected = '';
                if (!autoSelected && orderCity && (name === orderCity || name.indexOf(orderCity) !== -1 || orderCity.indexOf(name) !== -1)) {
                    selected = ' selected';
                    autoSelected = true;
                }
                html += '<option value="' + code + '"' + selected + '>' + name + ' (کد: ' + code + ')</option>';
            });

            citySelect.innerHTML = html;
            if (statusEl) {
                var msg = cities.length + ' شهر';
                if (isFallback) msg += ' (همه شهرها — فیلتر استان در API کار نکرد)';
                if (autoSelected) msg += ' — شهر «' + orderCity + '» خودکار انتخاب شد';
                statusEl.textContent = msg;
                statusEl.style.color = autoSelected ? '#059669' : (isFallback ? '#d97706' : '#888');
            }
        }

        // فیلتر client-side برای dropdown شهر
        function filterCityDropdown() {
            var q = (document.getElementById('postex-city-filter').value || '').trim();
            if (!q) {
                populateCityDropdown(allLoadedCities, false);
                return;
            }
            var filtered = allLoadedCities.filter(function(c) {
                var name = c.name || c.title || '';
                return name.indexOf(q) !== -1;
            });
            populateCityDropdown(filtered, false);
        }

        // ثبت با شهر انتخاب شده
        function retryWithCity() {
            var cityCode = document.getElementById('postex-city').value;
            if (!cityCode) {
                alert('لطفاً ابتدا شهر مقصد را انتخاب کنید');
                return;
            }

            var btn = document.getElementById('retryWithCityBtn');
            btn.disabled = true;
            btn.textContent = 'در حال ثبت...';

            fetch('/warehouse/{{ $order->id }}/retry-register', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ postex_city_code: parseInt(cityCode) }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('موفق! ' + data.message);
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'ثبت در پستکس';
                }
            })
            .catch(function() {
                alert('خطا در ارتباط');
                btn.disabled = false;
                btn.textContent = 'ثبت در پستکس';
            });
        }

        // اگه استان از قبل انتخاب شده (pre-selected)، شهرها رو بارگذاری کن
        @if(($shippingProvider ?? 'amadest') === 'postex' && $order->shipping_type === 'post' && empty($order->amadest_barcode))
        if (document.getElementById('postex-province') && document.getElementById('postex-province').value) {
            loadProvinceCities();
        }
        @endif

        // ===== COD24 City Selector =====
        @if(($shippingProvider ?? 'amadest') === 'cod24' && $order->shipping_type === 'post')
        var cod24StatesData = @json($cod24States ?? []);
        var cod24CitiesData = @json($cod24AllCities ?? []);
        var cod24FilteredCities = [];
        var cod24OrderCity = @json(($order->wc_order_data['shipping']['city'] ?? '') ?: ($order->wc_order_data['billing']['city'] ?? ''));
        var cod24OrderState = @json(($order->wc_order_data['shipping']['state'] ?? '') ?: ($order->wc_order_data['billing']['state'] ?? ''));
        var wcMap = @json(\Modules\Warehouse\Services\TapinService::getWcStateMap());

        // لود استان‌ها از داده‌های سرور (بدون fetch)
        (function loadCod24States() {
            var sel = document.getElementById('cod24-province');
            if (!sel) return;

            if (cod24StatesData.length === 0) {
                sel.innerHTML = '<option value="">خطا: لیست استان خالی</option>';
                return;
            }

            sel.innerHTML = '<option value="">انتخاب استان...</option>';
            var autoSelect = null;
            var persianName = wcMap[(cod24OrderState || '').toUpperCase()] || '';

            cod24StatesData.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.code;
                opt.textContent = s.name;
                sel.appendChild(opt);

                // auto-select بر اساس نام فارسی استان
                if (persianName && s.name && s.name.indexOf(persianName) !== -1) {
                    autoSelect = s.code;
                }
            });

            if (autoSelect) {
                sel.value = autoSelect;
                loadCod24Cities();
            }
        })();

        function loadCod24Cities() {
            var stateCode = parseInt(document.getElementById('cod24-province').value) || 0;

            // فیلتر شهرها بر اساس استان انتخابی
            if (stateCode > 0) {
                cod24FilteredCities = cod24CitiesData.filter(function(c) { return c.sc === stateCode; });
            } else {
                cod24FilteredCities = cod24CitiesData;
            }

            populateCod24Dropdown(cod24FilteredCities);
        }

        function populateCod24Dropdown(cities) {
            var citySelect = document.getElementById('cod24-city');
            var statusEl = document.getElementById('cod24-city-status');
            var filterInput = document.getElementById('cod24-city-filter');

            citySelect.innerHTML = '<option value="">انتخاب شهر (' + cities.length + ' شهر)...</option>';
            var autoSelected = false;
            var normalizeFA = function(s) { return (s||'').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/ة/g,'ه').trim(); };
            var nOrderCity = normalizeFA(cod24OrderCity);

            cities.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = c.code;
                opt.textContent = c.name + ' (' + c.code + ')';
                citySelect.appendChild(opt);
                if (!autoSelected && nOrderCity && normalizeFA(c.name) === nOrderCity) {
                    opt.selected = true;
                    autoSelected = true;
                }
            });

            if (filterInput) filterInput.style.display = cities.length > 15 ? 'block' : 'none';

            if (statusEl) {
                var msg = autoSelected ? '✓ شهر «' + cod24OrderCity + '» خودکار انتخاب شد — برای ثبت دکمه را بزنید' : (cities.length > 0 ? 'لطفاً شهر را انتخاب کنید' : 'شهری یافت نشد');
                statusEl.textContent = msg;
                statusEl.style.color = autoSelected ? '#0369a1' : '#888';
            }

            // اگه شهر خودکار پیدا شد، فقط نمایش بده — ثبت فقط با دکمه دستی
            // (حذف auto-retry برای جلوگیری از ثبت مکرر در COD24)
        }

        function filterCod24Cities() {
            var q = (document.getElementById('cod24-city-filter').value || '').trim();
            if (!q) {
                populateCod24Dropdown(cod24FilteredCities);
                return;
            }
            var normalizeFA = function(s) { return (s||'').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/ة/g,'ه').trim(); };
            var nq = normalizeFA(q);
            var filtered = cod24FilteredCities.filter(function(c) {
                return normalizeFA(c.name).indexOf(nq) !== -1;
            });
            populateCod24Dropdown(filtered);
        }

        function retryWithCod24City() {
            var cityCode = document.getElementById('cod24-city').value;
            if (!cityCode) {
                alert('لطفاً ابتدا شهر را انتخاب کنید');
                return;
            }
            var statusEl = document.getElementById('cod24-city-status');
            if (statusEl) { statusEl.textContent = 'در حال ثبت...'; statusEl.style.color = '#0369a1'; }

            fetch('{{ route("warehouse.retry-register", $order->id) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ cod24_city_code: parseInt(cityCode) }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (statusEl) {
                    statusEl.textContent = data.message || (data.success ? 'ثبت شد!' : 'خطا');
                    statusEl.style.color = data.success ? '#059669' : '#dc2626';
                }
                if (data.success) { setTimeout(function() { location.reload(); }, 1500); }
            })
            .catch(function() {
                if (statusEl) { statusEl.textContent = 'خطا در ارتباط'; statusEl.style.color = '#dc2626'; }
            });
        }
        @endif
    </script>

</body>
</html>
