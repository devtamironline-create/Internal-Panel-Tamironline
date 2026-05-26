<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>در حال انتقال به درگاه ملت...</title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#f3f4f6; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
        .box { text-align:center; background:#fff; padding:32px 40px; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
        .spinner { width:40px; height:40px; border:4px solid #e5e7eb; border-top-color:#2563eb; border-radius:50%; animation:spin 1s linear infinite; margin:0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color:#111827; font-size:16px; margin:8px 0; }
        p { color:#6b7280; font-size:13px; }
        button { margin-top:16px; padding:10px 24px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-size:14px; cursor:pointer; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h2>در حال انتقال به درگاه پرداخت ملت</h2>
        <p>لطفاً چند لحظه صبر کنید... اگر به‌صورت خودکار منتقل نشدید، دکمه زیر را بزنید.</p>
        <form id="mellat-form" method="POST" action="{{ $startPayUrl }}">
            <input type="hidden" name="RefId" value="{{ $refId }}">
            <button type="submit">انتقال به درگاه</button>
        </form>
    </div>
    <script>
        document.getElementById('mellat-form').submit();
    </script>
</body>
</html>
