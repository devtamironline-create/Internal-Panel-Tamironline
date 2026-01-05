@extends('layouts.admin')
@section('page-title', 'ایمپورت اکسل')
@section('main')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">ایمپورت اطلاعات از اکسل</h1>
    <p class="mt-1 text-sm text-gray-600">وارد کردن مشتریان و سرویس‌ها از فایل اکسل</p>
</div>

@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
    {{ session('error') }}
</div>
@endif

@if(session('import_errors'))
<div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
    <p class="font-semibold">خطاها:</p>
    <ul class="list-disc list-inside mt-2">
        @foreach(session('import_errors') as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Upload Form -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">آپلود فایل اکسل</h2>

        <form action="{{ route('admin.import.preview') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="mb-4">
                <label for="file" class="block text-sm font-medium text-gray-700 mb-2">فایل اکسل</label>
                <input type="file" name="file" id="file" accept=".xlsx,.xls,.csv" required
                    class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-400 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('file')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                پیش‌نمایش و بررسی داده‌ها
            </button>
        </form>
    </div>

    <!-- Instructions -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">راهنمای فرمت فایل</h2>

        <div class="text-sm text-gray-600 space-y-3">
            <p>فایل اکسل باید شامل ستون‌های زیر باشد:</p>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-right">#</th>
                            <th class="px-3 py-2 text-right">ستون</th>
                            <th class="px-3 py-2 text-right">توضیح</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="px-3 py-2">A</td><td class="px-3 py-2">#</td><td class="px-3 py-2 text-gray-500">شماره ردیف</td></tr>
                        <tr><td class="px-3 py-2">B</td><td class="px-3 py-2">سرور</td><td class="px-3 py-2 text-gray-500">شماره سرور (مثلاً 5)</td></tr>
                        <tr><td class="px-3 py-2">C</td><td class="px-3 py-2">سال</td><td class="px-3 py-2 text-gray-500">سال شمسی (مثلاً 1405)</td></tr>
                        <tr><td class="px-3 py-2">D</td><td class="px-3 py-2">ماه</td><td class="px-3 py-2 text-gray-500">نام ماه فارسی</td></tr>
                        <tr><td class="px-3 py-2">E</td><td class="px-3 py-2">روز</td><td class="px-3 py-2 text-gray-500">روز ماه</td></tr>
                        <tr><td class="px-3 py-2">F</td><td class="px-3 py-2">مبلغ</td><td class="px-3 py-2 text-gray-500">مبلغ به تومان</td></tr>
                        <tr><td class="px-3 py-2">G</td><td class="px-3 py-2">مدت تمدید</td><td class="px-3 py-2 text-gray-500">یکساله، ماهانه و...</td></tr>
                        <tr><td class="px-3 py-2">H</td><td class="px-3 py-2">نام دامنه</td><td class="px-3 py-2 text-gray-500">آدرس دامنه</td></tr>
                        <tr><td class="px-3 py-2">I</td><td class="px-3 py-2">مشتری</td><td class="px-3 py-2 text-gray-500">نام و نام خانوادگی</td></tr>
                        <tr class="bg-blue-50"><td class="px-3 py-2">J</td><td class="px-3 py-2 font-medium">موبایل</td><td class="px-3 py-2 text-gray-500">شماره موبایل (09xxxxxxxxx)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                <p class="font-medium text-yellow-800">نکات مهم:</p>
                <ul class="list-disc list-inside mt-1 text-yellow-700">
                    <li>تاریخ تمدید باید به شمسی باشد</li>
                    <li>مبلغ بدون واحد پول وارد شود</li>
                    <li>ردیف اول باید عنوان ستون‌ها باشد</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Current Servers & Products -->
<div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">سرورهای موجود</h3>
        @if($servers->count())
        <ul class="space-y-2">
            @foreach($servers as $server)
            <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <span>{{ $server->name }}</span>
                <span class="text-xs text-gray-500">{{ $server->type_label }}</span>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-gray-500 text-sm">هیچ سروری تعریف نشده.
            <a href="{{ route('admin.servers.create') }}" class="text-blue-600 hover:underline">ایجاد سرور</a>
        </p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">محصولات موجود</h3>
        @if($products->count())
        <ul class="space-y-2">
            @foreach($products as $product)
            <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <span>{{ $product->name }}</span>
                <span class="text-xs text-gray-500">{{ number_format($product->base_price) }} تومان</span>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-gray-500 text-sm">هیچ محصولی تعریف نشده.
            <a href="{{ route('admin.products.create') }}" class="text-blue-600 hover:underline">ایجاد محصول</a>
        </p>
        @endif
    </div>
</div>
@endsection
