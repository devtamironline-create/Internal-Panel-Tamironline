@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- مشخصات --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">موبایل <span class="text-red-500">*</span></label>
        <input type="text" name="mobile" value="{{ old('mobile', $customer->mobile ?? '') }}" required dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500"
               placeholder="09xxxxxxxxx">
        @error('mobile')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تلفن ثابت</label>
        <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام</label>
        <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام خانوادگی</label>
        <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('last_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ایمیل</label>
        <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد ملی</label>
        <input type="text" name="national_code" value="{{ old('national_code', $customer->national_code ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('national_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- آدرس --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
        <select name="province_id" id="customer-province" data-cities-url="{{ url('/admin/crm/provinces') }}/__ID__/cities"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            <option value="">— انتخاب —</option>
            @foreach($provinces as $p)
            <option value="{{ $p->id }}" @selected(old('province_id', $customer->province_id ?? null) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        @error('province_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
        <select name="city_id" id="customer-city"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            <option value="">— ابتدا استان را انتخاب کنید —</option>
            @foreach($cities as $c)
            <option value="{{ $c->id }}" @selected(old('city_id', $customer->city_id ?? null) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('city_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد پستی</label>
        <input type="text" name="postal_code" value="{{ old('postal_code', $customer->postal_code ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('postal_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس</label>
        <textarea name="address" rows="2"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">{{ old('address', $customer->address ?? '') }}</textarea>
        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت‌ها</label>
        <textarea name="notes" rows="3"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">{{ old('notes', $customer->notes ?? '') }}</textarea>
        @error('notes')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.customers.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>

<script>
(function () {
    const provinceEl = document.getElementById('customer-province');
    const cityEl = document.getElementById('customer-city');
    if (!provinceEl || !cityEl) return;

    provinceEl.addEventListener('change', async function () {
        const id = this.value;
        cityEl.innerHTML = '<option value="">در حال بارگذاری...</option>';

        if (!id) {
            cityEl.innerHTML = '<option value="">— ابتدا استان را انتخاب کنید —</option>';
            return;
        }

        try {
            const url = provinceEl.dataset.citiesUrl.replace('__ID__', id);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            cityEl.innerHTML = '<option value="">— انتخاب شهر —</option>' +
                data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        } catch (e) {
            cityEl.innerHTML = '<option value="">خطا در بارگذاری شهرها</option>';
        }
    });
})();
</script>
