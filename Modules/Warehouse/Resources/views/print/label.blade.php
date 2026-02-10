<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>برچسب ارسال {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; }
        .label { width: 400px; margin: 0 auto; border: 2px solid #000; padding: 15px; }
        .label-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 10px; }
        .label-header h2 { font-size: 16px; }
        .label-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #ccc; font-size: 13px; }
        .label-row:last-of-type { border-bottom: none; }
        .label-barcode { text-align: center; margin-top: 10px; padding-top: 10px; border-top: 2px solid #000; }
        .shipping-badge { display: inline-block; padding: 3px 10px; border: 1px solid #000; font-weight: bold; font-size: 14px; margin-top: 5px; }
        .print-btn { position: fixed; top: 20px; left: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-family: Tahoma; }
        @media print { .print-btn { display: none !important; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">چاپ برچسب</button>

    <div class="label">
        <div class="label-header">
            <h2>تعمیرآنلاین</h2>
            <div class="shipping-badge">{{ $order->shipping_type === 'courier' ? 'پیک' : 'پست' }}</div>
        </div>

        <div class="label-row">
            <span>گیرنده:</span>
            <strong>{{ $order->customer_name }}</strong>
        </div>
        <div class="label-row">
            <span>تلفن:</span>
            <strong dir="ltr">{{ $order->customer_mobile ?? '-' }}</strong>
        </div>
        <div class="label-row">
            <span>شماره سفارش:</span>
            <strong>{{ $order->order_number }}</strong>
        </div>
        @php
            $labelBox = $order->boxSize ?? $order->recommended_box;
            $labelBoxWeight = $labelBox ? $labelBox->weight : 0;
            $labelWeight = ($order->actual_weight_grams ?: $order->total_weight_grams) + $labelBoxWeight;
        @endphp
        <div class="label-row">
            <span>وزن:</span>
            <strong>{{ number_format($labelWeight) }}g</strong>
        </div>
        @if($labelBox)
        <div class="label-row">
            <span>کارتن:</span>
            <strong>سایز {{ $labelBox->name }}</strong>
        </div>
        @endif
        @if($order->tracking_code)
        <div class="label-row">
            <span>کد رهگیری:</span>
            <strong dir="ltr">{{ $order->tracking_code }}</strong>
        </div>
        @endif

        <div class="label-barcode">
            <svg id="barcode"></svg>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        JsBarcode("#barcode", "{{ $order->barcode }}", { format: "CODE128", width: 2.5, height: 55, displayValue: true, fontSize: 12, margin: 5 });
    </script>
</body>
</html>
