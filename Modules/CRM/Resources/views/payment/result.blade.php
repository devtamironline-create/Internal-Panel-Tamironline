<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتیجه پرداخت</title>
    <script src="/vendor/js/tailwind.min.js"></script>
    <style>
        body { font-family: Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg max-w-md w-full p-8 text-center">
        @if($ok)
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">عملیات موفق</h1>
        @else
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">عملیات ناموفق</h1>
        @endif

        <p class="text-gray-600 mb-4">{{ $message }}</p>

        @if($invoice)
        <div class="bg-gray-50 rounded-lg p-4 text-sm mb-4">
            <div class="flex justify-between mb-2">
                <span class="text-gray-500">کد فاکتور:</span>
                <span class="font-medium" dir="ltr">{{ $invoice->invoice_code }}</span>
            </div>
            <div class="flex justify-between mb-2">
                <span class="text-gray-500">مبلغ:</span>
                <span class="font-bold">{{ number_format($invoice->total_amount) }} تومان</span>
            </div>
            @if($payment && $payment->ref_number)
            <div class="flex justify-between">
                <span class="text-gray-500">شماره پیگیری:</span>
                <span class="font-medium" dir="ltr">{{ $payment->ref_number }}</span>
            </div>
            @endif
        </div>
        @elseif($payment && $payment->purpose === 'wallet_charge')
        <div class="bg-gray-50 rounded-lg p-4 text-sm mb-4">
            <div class="flex justify-between mb-2">
                <span class="text-gray-500">مبلغ شارژ:</span>
                <span class="font-bold">{{ number_format((int) $payment->amount) }} تومان</span>
            </div>
            @if($payment->ref_number)
            <div class="flex justify-between">
                <span class="text-gray-500">شماره پیگیری:</span>
                <span class="font-medium" dir="ltr">{{ $payment->ref_number }}</span>
            </div>
            @endif
        </div>
        @if($payment->technician_id)
            <a href="{{ route('tech.wallet') }}"
               class="inline-block w-full py-2.5 rounded-lg bg-blue-600 text-white font-bold text-sm">
                بازگشت به کیف‌پول
            </a>
        @endif
        @endif

        @if($payment && $payment->return_url)
            <?php
                $backUrl = \Modules\CRM\Support\PaymentReturnUrl::withResult(
                    $payment->return_url,
                    $ok,
                    $payment->ref_number ?: $payment->track_id,
                );
            ?>
            <a href="{{ $backUrl }}"
               class="inline-block w-full py-3 mt-2 rounded-lg {{ $ok ? 'bg-emerald-600' : 'bg-gray-700' }} text-white font-bold text-sm">
                بازگشت به اپلیکیشن
            </a>
            <p class="text-xs text-gray-400 mt-2">تا <span id="return-count">3</span> ثانیهٔ دیگر خودکار به اپلیکیشن برمی‌گردید…</p>
            <script>
                (function () {
                    var left = 3, el = document.getElementById('return-count');
                    var t = setInterval(function () {
                        left--;
                        if (el) el.textContent = left;
                        if (left <= 0) { clearInterval(t); window.location.href = @json($backUrl); }
                    }, 1000);
                })();
            </script>
        @endif

        <p class="text-xs text-gray-400 mt-4">در صورت بروز مشکل با پشتیبانی تماس بگیرید.</p>
    </div>
</body>
</html>
