@extends('layouts.admin')

@section('page-title', 'تنظیمات صورتحساب چاپی')

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تنظیمات صورتحساب چاپی</h1>
            <p class="text-sm text-gray-500 mt-1">اطلاعات ارائه‌دهنده که در بالای فاکتور چاپ می‌شود + متن یادداشت‌های پایین فاکتور.</p>
        </div>
        <a href="{{ route('crm.invoices.index') }}" class="text-sm text-brand-700 hover:underline">← فاکتورها</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <form action="{{ route('crm.invoices.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5 bg-white dark:bg-gray-800 rounded-xl shadow p-6">
        @csrf

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg p-3 text-sm">
                <ul class="list-disc ps-5 space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- ── لوگو و مهر/امضا ── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">لوگوی شرکت</label>
                @if($logoPath)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ asset('storage/' . $logoPath) }}" alt="logo" class="h-16 w-16 object-contain bg-gray-50 rounded border">
                        <label class="inline-flex items-center gap-2 text-xs text-rose-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded">
                            حذف لوگوی فعلی
                        </label>
                    </div>
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                       class="w-full text-xs text-gray-600 dark:text-gray-300">
                <p class="text-[11px] text-gray-500 mt-1">PNG/JPG/WEBP/SVG — حداکثر ۲ مگابایت. اگر آپلود نکنید آیکن پیش‌فرض «ت» نمایش داده می‌شود.</p>
            </div>

            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">مهر و امضا</label>
                @if($stampPath)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ asset('storage/' . $stampPath) }}" alt="stamp" class="h-20 w-20 object-contain bg-gray-50 rounded border">
                        <label class="inline-flex items-center gap-2 text-xs text-rose-600">
                            <input type="checkbox" name="remove_stamp" value="1" class="rounded">
                            حذف مهر فعلی
                        </label>
                    </div>
                @endif
                <input type="file" name="stamp" accept="image/png,image/jpeg,image/webp"
                       class="w-full text-xs text-gray-600 dark:text-gray-300">
                <p class="text-[11px] text-gray-500 mt-1">ترجیحاً PNG با پس‌زمینهٔ شفاف — پایین صفحهٔ فاکتور چاپ می‌شود.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام ارائه‌دهنده</label>
                <input type="text" name="invoice_provider_name" value="{{ old('invoice_provider_name', $providerName) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره تلفن</label>
                <input type="text" name="invoice_provider_phone" value="{{ old('invoice_provider_phone', $providerPhone) }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد پستی</label>
                <input type="text" name="invoice_provider_postal_code" value="{{ old('invoice_provider_postal_code', $providerPostal) }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل</label>
            <input type="text" name="invoice_provider_address" value="{{ old('invoice_provider_address', $providerAddress) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">متن یادداشت‌های پایین فاکتور</label>
            <textarea name="invoice_print_notes" rows="7"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs leading-7">{{ old('invoice_print_notes', $printNotes) }}</textarea>
            <p class="text-[11px] text-gray-500 mt-1">هر خط جدید در نمای چاپی به‌صورت پاراگراف جدا نمایش داده می‌شود.</p>
        </div>

        @php $lastInvoice = \Modules\CRM\Models\Invoice::orderByDesc('id')->first(); @endphp
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">
                ذخیره تنظیمات
            </button>
            @if($lastInvoice)
                <a href="{{ route('crm.invoices.print', $lastInvoice) }}" target="_blank"
                   class="text-sm text-brand-700 hover:underline">پیش‌نمایش آخرین فاکتور →</a>
            @endif
        </div>
    </form>
</div>
@endsection
