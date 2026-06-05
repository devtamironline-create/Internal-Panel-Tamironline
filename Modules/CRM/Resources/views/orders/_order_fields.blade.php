{{-- اطلاعات سفارش — نحوه آشنایی، نوع، تکنسین، کد اشتراک --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">اطلاعات سفارش</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نحوه آشنایی (معرف)</label>
            @if(! empty($introductionList))
                @php
                    $introOpts = ['' => '— انتخاب —'];
                    foreach ($introductionList as $opt) { $introOpts[(string) $opt] = (string) $opt; }
                @endphp
                <x-searchable-select
                    name="introduction"
                    :options="$introOpts"
                    :value="old('introduction', $order->introduction ?? '')"
                    placeholder="— انتخاب —"
                    searchPlaceholder="جستجو..." />
            @else
                <input type="text" name="introduction" value="{{ old('introduction', $order->introduction ?? '') }}"
                       placeholder="مثلاً اینستاگرام، اپلیکیشن، سایت"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @endif
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع سفارش</label>
            <select name="order_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب —</option>
                <option value="repair"  @selected(old('order_type', $order->order_type ?? '') === 'repair')>تعمیر</option>
                <option value="service" @selected(old('order_type', $order->order_type ?? '') === 'service')>سرویس</option>
                <option value="install" @selected(old('order_type', $order->order_type ?? '') === 'install')>نصب</option>
            </select>
        </div>
        @if(isset($technicians))
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین</label>
            @php
                $techOpts = ['' => '— بدون تکنسین —'];
                foreach ($technicians as $t) {
                    $name = trim($t->firstname_tech ?: ($t->first_name . ' ' . ($t->last_name ?? '')));
                    $techOpts[(string) $t->id] = $name ?: ('#' . $t->id);
                }
            @endphp
            <x-searchable-select
                name="technician_id"
                :options="$techOpts"
                :value="old('technician_id', $order->technician_id ?? '')"
                placeholder="— بدون تکنسین —"
                searchPlaceholder="جستجوی تکنسین..." />
        </div>
        @endif
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد اشتراک</label>
            <input type="number" name="subscription" min="0" dir="ltr"
                   value="{{ old('subscription', $order->subscription ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- دستگاه --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">دستگاه و ایراد</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برند</label>
            <select name="brand_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب —</option>
                @foreach($brands as $b)
                <option value="{{ $b->id }}" @selected(old('brand_id', $order->brand_id ?? null) == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع دستگاه</label>
            <select name="device_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب —</option>
                @foreach($devices as $d)
                <option value="{{ $d->id }}" @selected(old('device_id', $order->device_id ?? null) == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان مشکل</label>
            <input type="text" name="problem_title" value="{{ old('problem_title', $order->problem_title ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="مثلاً آب‌بندی نمی‌کند">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زمان مراجعه</label>
            <input type="datetime-local" name="visit_scheduled_at"
                   value="{{ old('visit_scheduled_at', isset($order) && $order->visit_scheduled_at ? $order->visit_scheduled_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شرح مشکل</label>
            <textarea name="problem_description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('problem_description', $order->problem_description ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- محل مراجعه --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">محل مراجعه</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <select name="province_id" id="order-province"
                    data-tom-select data-placeholder="جستجو در استان‌ها..."
                    data-cities-url="{{ url('/admin/crm/provinces') }}/__ID__/cities"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب —</option>
                @foreach($provinces as $p)
                <option value="{{ $p->id }}" @selected(old('province_id', $order->province_id ?? null) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
            <select name="city_id" id="order-city"
                    data-tom-select data-placeholder="جستجو در شهرها..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— ابتدا استان را انتخاب کنید —</option>
                @foreach($cities as $c)
                <option value="{{ $c->id }}" @selected(old('city_id', $order->city_id ?? null) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل</label>
            <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('address', $order->address ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- مالی پایه --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">مالی اولیه (تومان)</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برآورد اولیه</label>
            <input type="number" name="estimated_price" min="0" value="{{ old('estimated_price', $order->estimated_price ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        @if($showFinalPrice ?? false)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ نهایی</label>
            <input type="number" name="final_price" min="0" value="{{ old('final_price', $order->final_price ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        @endif
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">بیعانه پرداخت‌شده</label>
            <input type="number" name="deposit" min="0" value="{{ old('deposit', $order->deposit ?? 0) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- یادداشت داخلی --}}
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت داخلی</label>
    <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('notes', $order->notes ?? '') }}</textarea>
</div>

<script>
(function () {
    const provinceEl = document.getElementById('order-province');
    const cityEl = document.getElementById('order-city');
    if (!provinceEl || !cityEl) return;

    // اگر Tom Select روی select فعال است، باید به‌جای innerHTML از API
    // tomselect استفاده کنیم تا dropdown قابل سرچ همگام بماند.
    function setCityOptions(items, placeholder) {
        const ts = cityEl.tomselect;
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            ts.addOption([{ value: '', text: placeholder }, ...items.map(c => ({ value: String(c.id), text: c.name }))]);
            ts.refreshOptions(false);
        } else {
            cityEl.innerHTML = `<option value="">${placeholder}</option>` +
                items.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        }
    }

    // پاک‌کردن صرف بدون لیست — برای حالت‌های loading/error
    function resetCity(placeholder) {
        const ts = cityEl.tomselect;
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            ts.addOption([{ value: '', text: placeholder }]);
            ts.refreshOptions(false);
        } else {
            cityEl.innerHTML = `<option value="">${placeholder}</option>`;
        }
    }

    provinceEl.addEventListener('change', async function () {
        const id = this.value;
        resetCity('در حال بارگذاری...');
        if (!id) {
            resetCity('— ابتدا استان را انتخاب کنید —');
            return;
        }
        try {
            const url = provinceEl.dataset.citiesUrl.replace('__ID__', id);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            setCityOptions(data, '— انتخاب شهر —');
        } catch (e) {
            resetCity('خطا در بارگذاری شهرها');
        }
    });
})();
</script>
