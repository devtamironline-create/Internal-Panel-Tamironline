<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پنل تکنسین</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4 font-sans">
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow p-6 space-y-3">
        <h1 class="text-lg font-bold">سلام، {{ trim($technician->firstname_tech ?: $technician->first_name) ?: $technician->mobile }}</h1>
        <p class="text-xs text-gray-500">فاز ۱ — تست auth با موفقیت انجام شد. ظاهر کامل و صفحات (سفارش، کیف‌پول، فاکتور، پروفایل) در فاز‌های بعدی اضافه می‌شوند.</p>

        <div class="text-sm text-gray-700 border-t pt-3 space-y-1">
            <div>موبایل: <span dir="ltr">{{ $technician->mobile }}</span></div>
            <div>درصد: {{ $technician->percent ?? 0 }}%</div>
            <div>وضعیت: {{ $technician->status }}</div>
            <div>آخرین ورود: {{ $technician->last_login_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>

        <form action="{{ route('tech.logout') }}" method="POST" class="pt-3">
            @csrf
            <button class="w-full py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">خروج</button>
        </form>
    </div>
</body>
</html>
