@extends('layouts.admin')

@section('page-title', 'ثبت سفارش')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ثبت سفارش تعمیر</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.orders.store') }}" method="POST">
            @csrf

            {{-- مشتری --}}
            <div class="mb-6">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">مشتری</h3>
                @if($customer)
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $customer->full_name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $customer->mobile }}</div>
                    </div>
                    <a href="{{ route('crm.orders.create') }}" class="text-xs text-red-600 hover:text-red-800">تغییر مشتری</a>
                </div>
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                @else
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">انتخاب مشتری (موبایل یا نام)</label>
                    <input type="text" id="customer-search" placeholder="جستجو..."
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <input type="hidden" name="customer_id" id="customer-id">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        مشتری در لیست نیست؟
                        <a href="{{ route('crm.customers.create') }}" target="_blank" class="text-brand-600 hover:underline">افزودن مشتری جدید</a>
                    </p>
                    <div id="customer-results" class="border border-gray-200 dark:border-gray-600 rounded-lg mt-1 max-h-48 overflow-y-auto hidden bg-white dark:bg-gray-800"></div>
                    @error('customer_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                @endif
            </div>

            @include('crm::orders._order_fields', [
                'order' => null,
                'brands' => $brands,
                'devices' => $devices,
                'provinces' => $provinces,
                'cities' => $cities,
                'showFinalPrice' => false,
            ])

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت اولیه</label>
                <select name="status" class="w-full md:w-1/2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', 'pending') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ثبت سفارش</button>
                <a href="{{ route('crm.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
            </div>
        </form>
    </div>
</div>

@if(!$customer)
<script>
(function () {
    // جستجوی ساده مشتری با تاخیر
    const input = document.getElementById('customer-search');
    const results = document.getElementById('customer-results');
    const hidden = document.getElementById('customer-id');
    let timer;

    input?.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }

        timer = setTimeout(async () => {
            try {
                const res = await fetch(`{{ route('crm.customers.index') }}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await res.text();
                // ساده: صفحه لیست رو پارس کن و ردیف‌های مشتری رو دربیار
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const rows = doc.querySelectorAll('tbody tr');
                const items = [];
                rows.forEach(tr => {
                    const link = tr.querySelector('a[href*="customers/"]');
                    if (!link) return;
                    const id = link.href.match(/customers\/(\d+)/)?.[1];
                    if (!id) return;
                    const name = link.textContent.trim();
                    const mobile = tr.querySelector('td[dir="ltr"]')?.textContent.trim() || '';
                    items.push({ id, name, mobile });
                });

                results.innerHTML = items.length
                    ? items.map(i => `<div class="customer-row px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm" data-id="${i.id}" data-name="${i.name}" data-mobile="${i.mobile}">
                        <div class="font-medium">${i.name}</div>
                        <div class="text-xs text-gray-500" dir="ltr">${i.mobile}</div>
                      </div>`).join('')
                    : '<div class="px-3 py-3 text-sm text-gray-500">موردی یافت نشد.</div>';
                results.classList.remove('hidden');
            } catch (e) {
                results.classList.add('hidden');
            }
        }, 300);
    });

    results?.addEventListener('click', (e) => {
        const row = e.target.closest('.customer-row');
        if (!row) return;
        hidden.value = row.dataset.id;
        input.value = `${row.dataset.name} (${row.dataset.mobile})`;
        results.classList.add('hidden');
    });
})();
</script>
@endif
@endsection
