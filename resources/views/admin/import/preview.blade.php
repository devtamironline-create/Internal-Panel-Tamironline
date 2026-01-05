@extends('layouts.admin')
@section('page-title', 'پیش‌نمایش ایمپورت')

@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">پیش‌نمایش داده‌ها</h1>
        <p class="mt-1 text-sm text-gray-600">{{ count($data) }} ردیف برای وارد کردن یافت شد</p>
    </div>
    <a href="{{ route('admin.import.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
        بازگشت
    </a>
</div>

@if(isset($debugInfo) && count($debugInfo))
<div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg">
    <p class="font-semibold mb-2">ستون‌های تشخیص داده شده:</p>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
        <div><span class="font-medium">مشتری:</span> {{ $debugInfo['customer'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">موبایل:</span> {{ $debugInfo['mobile'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">دامنه:</span> {{ $debugInfo['domain'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">مبلغ:</span> {{ $debugInfo['amount'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">سرور:</span> {{ $debugInfo['server'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">سال:</span> {{ $debugInfo['year'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">ماه:</span> {{ $debugInfo['month'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">روز:</span> {{ $debugInfo['day'] ?? 'یافت نشد' }}</div>
        <div><span class="font-medium">دوره:</span> {{ $debugInfo['billing_cycle'] ?? 'یافت نشد' }}</div>
    </div>
</div>
@endif

@if(count($errors ?? []))
<div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
    <p class="font-semibold">خطاها در پردازش فایل:</p>
    <ul class="list-disc list-inside mt-2 text-sm">
        @foreach($errors as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(count($data))
<form action="{{ route('admin.import.process') }}" method="POST">
    @csrf

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="select-all" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">انتخاب همه</span>
                </label>
            </div>
            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                وارد کردن انتخاب‌شده‌ها
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase w-10">انتخاب</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase w-8">#</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">موبایل</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">دامنه</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">محصول</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase w-20">ماه</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">سرور</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ</th>
                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ تمدید</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($data as $index => $row)
                    <tr class="hover:bg-gray-50 {{ $row['amount'] == 0 ? 'bg-red-50' : '' }}">
                        <td class="px-2 py-3">
                            <input type="checkbox" name="data[{{ $index }}][import]" value="1"
                                {{ $row['amount'] > 0 ? 'checked' : '' }}
                                class="row-checkbox w-4 h-4 text-blue-600 rounded">
                        </td>
                        <td class="px-2 py-3 text-gray-500 text-xs">{{ $row['row_number'] }}</td>
                        <td class="px-2 py-3">
                            <input type="text" name="data[{{ $index }}][customer_name]" value="{{ $row['customer_name'] }}"
                                class="w-full px-2 py-1 border border-gray-200 rounded text-sm" required>
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="data[{{ $index }}][mobile]" value="{{ $row['mobile'] ?? '' }}"
                                class="w-28 px-2 py-1 border border-gray-200 rounded text-sm ltr text-center" placeholder="09xxxxxxxxx">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="data[{{ $index }}][domain]" value="{{ $row['domain'] }}"
                                class="w-full px-2 py-1 border border-gray-200 rounded text-sm ltr">
                        </td>
                        <td class="px-2 py-3">
                            <select name="data[{{ $index }}][product_id]" class="w-full px-2 py-1 border border-gray-200 rounded text-sm product-select" data-index="{{ $index }}" required>
                                <option value="">انتخاب...</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    data-price="{{ $product->yearly_price }}"
                                    {{ $row['product_id'] == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ number_format($product->yearly_price) }} تومان/سال)
                                </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-3">
                            <select name="data[{{ $index }}][billing_months]" class="w-full px-2 py-1 border border-gray-200 rounded text-sm months-select" data-index="{{ $index }}">
                                <option value="12" selected>۱۲ ماه</option>
                                <option value="6">۶ ماه</option>
                                <option value="3">۳ ماه</option>
                                <option value="1">۱ ماه</option>
                                <option value="24">۲۴ ماه</option>
                            </select>
                        </td>
                        <td class="px-2 py-3">
                            <select name="data[{{ $index }}][server_id]" class="w-full px-2 py-1 border border-gray-200 rounded text-sm">
                                <option value="">بدون سرور</option>
                                @foreach($servers as $server)
                                <option value="{{ $server->id }}" {{ str_contains($row['server_name'] ?? '', $server->name) ? 'selected' : '' }}>
                                    {{ $server->name }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-3">
                            <input type="number" name="data[{{ $index }}][amount]" value="{{ $row['amount'] }}"
                                class="w-24 px-2 py-1 border border-gray-200 rounded text-sm ltr amount-input" data-index="{{ $index }}" required>
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="data[{{ $index }}][next_due_date_jalali]"
                                value="{{ $row['next_due_date'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['next_due_date'])->format('Y/m/d') : ($row['year'] && $row['month_number'] && $row['day'] ? $row['year'].'/'.$row['month_number'].'/'.$row['day'] : '') }}"
                                class="w-28 px-2 py-1 border border-gray-200 rounded text-sm ltr jalali-picker text-center"
                                placeholder="۱۴۰۵/۰۱/۰۱"
                                data-index="{{ $index }}">
                            <input type="hidden" name="data[{{ $index }}][next_due_date]" value="{{ $row['next_due_date'] }}" class="gregorian-date" data-index="{{ $index }}">
                            @if(!$row['next_due_date'] && ($row['year'] || $row['month'] || $row['day']))
                            <span class="text-xs text-orange-500 block mt-1">
                                {{ $row['year'] }}/{{ $row['month'] }}/{{ $row['day'] }}
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50">
            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                وارد کردن انتخاب‌شده‌ها
            </button>
        </div>
    </div>
</form>
@else
<div class="bg-white rounded-xl shadow-sm p-12 text-center">
    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <p class="text-lg font-medium text-gray-900">هیچ داده‌ای یافت نشد</p>
    <p class="mt-1 text-sm text-gray-500">فایل اکسل خالی است یا فرمت آن صحیح نیست</p>
    <a href="{{ route('admin.import.index') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        بازگشت و آپلود مجدد
    </a>
</div>
@endif

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Select all checkbox
    $('#select-all').on('change', function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Initialize Persian datepickers
    $('.jalali-picker').each(function() {
        var $input = $(this);
        var index = $input.data('index');
        var $hiddenInput = $('.gregorian-date[data-index="' + index + '"]');

        $input.persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            initialValue: false,
            onSelect: function(unix) {
                var pDate = new persianDate(unix);
                var gDate = pDate.toCalendar('gregorian');
                var gregorian = gDate.format('YYYY-MM-DD');
                $hiddenInput.val(gregorian);
            }
        });
    });

    // Product & Months change - Calculate price
    $('.product-select, .months-select').on('change', function() {
        var $row = $(this).closest('tr');
        var $productSelect = $row.find('.product-select');
        var $monthsSelect = $row.find('.months-select');
        var $amountInput = $row.find('.amount-input');

        var basePrice = parseFloat($productSelect.find(':selected').data('price')) || 0;
        var months = parseInt($monthsSelect.val()) || 12;

        // Calculate price: (base_price / 12) * months
        var calculatedPrice = Math.round((basePrice / 12) * months);
        $amountInput.val(calculatedPrice);

        console.log('Price calc:', basePrice, months, calculatedPrice);
    });
});
</script>
@endpush
