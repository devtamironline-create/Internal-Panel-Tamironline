@csrf
{{-- مشخصات فردی --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">مشخصات فردی</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" value="{{ old('first_name', $technician->first_name ?? '') }}" required
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام خانوادگی</label>
            <input type="text" name="last_name" value="{{ old('last_name', $technician->last_name ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            @error('last_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">موبایل <span class="text-red-500">*</span></label>
            <input type="text" name="mobile" value="{{ old('mobile', $technician->mobile ?? '') }}" required dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500"
                   placeholder="09xxxxxxxxx">
            @error('mobile')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تلفن ثابت</label>
            <input type="text" name="phone" value="{{ old('phone', $technician->phone ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تلفن اضطراری</label>
            <input type="text" name="phone_force" value="{{ old('phone_force', $technician->phone_force ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ایمیل</label>
            <input type="email" name="email" value="{{ old('email', $technician->email ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد ملی</label>
            <input type="text" name="national_code" value="{{ old('national_code', $technician->national_code ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جنسیت</label>
            <select name="gender" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— —</option>
                <option value="male" @selected(old('gender', $technician->gender ?? null) === 'male')>مرد</option>
                <option value="female" @selected(old('gender', $technician->gender ?? null) === 'female')>زن</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد تکنسین (اختیاری)</label>
            <input type="text" name="tech_code" value="{{ old('tech_code', $technician->tech_code ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500"
                   placeholder="مثلاً T405001">
            @error('tech_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

{{-- آدرس --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">آدرس</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <select name="province_id" id="tech-province" data-cities-url="{{ url('/admin/crm/provinces') }}/__ID__/cities"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب —</option>
                @foreach($provinces as $p)
                <option value="{{ $p->id }}" @selected(old('province_id', $technician->province_id ?? null) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
            <select name="city_id" id="tech-city" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— ابتدا استان را انتخاب کنید —</option>
                @foreach($cities as $c)
                <option value="{{ $c->id }}" @selected(old('city_id', $technician->city_id ?? null) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس</label>
            <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('address', $technician->address ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- تخصص و سطح --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">تخصص و سطح</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تخصص (متن آزاد)</label>
            <input type="text" name="specialty" value="{{ old('specialty', $technician->specialty ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="مثلاً ماشین لباسشویی، یخچال...">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سطح تکنسین <span class="text-red-500">*</span></label>
            <select name="tech_type" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="regular" @selected(old('tech_type', $technician->tech_type ?? 'regular') === 'regular')>عادی</option>
                <option value="senior" @selected(old('tech_type', $technician->tech_type ?? '') === 'senior')>ارشد</option>
                <option value="expert" @selected(old('tech_type', $technician->tech_type ?? '') === 'expert')>متخصص</option>
                <option value="freelance" @selected(old('tech_type', $technician->tech_type ?? '') === 'freelance')>فریلنسر</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('description', $technician->description ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس عکس شخصی (URL)</label>
            <input type="text" name="personal_image" value="{{ old('personal_image', $technician->personal_image ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس تصویر کارت ملی (URL)</label>
            <input type="text" name="card_image" value="{{ old('card_image', $technician->card_image ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- قوانین مالی --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">قوانین مالی و محدودیت‌ها</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">درصد کمیسیون (0-100)</label>
            <input type="number" name="commission_percent" min="0" max="100" value="{{ old('commission_percent', $technician->commission_percent ?? 0) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">روش محاسبه</label>
            <select name="calc_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="percent_of_customer" @selected(old('calc_type', $technician->calc_type ?? 'percent_of_customer') === 'percent_of_customer')>درصد از مبلغ دریافتی از مشتری</option>
                <option value="percent_of_total" @selected(old('calc_type', $technician->calc_type ?? '') === 'percent_of_total')>درصد از کل فاکتور</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سقف تعداد سفارش همزمان (خالی = نامحدود)</label>
            <input type="number" name="max_concurrent_orders" min="0" value="{{ old('max_concurrent_orders', $technician->max_concurrent_orders ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سقف مبلغ سفارش (تومان - خالی = نامحدود)</label>
            <input type="number" name="max_order_price" min="0" value="{{ old('max_order_price', $technician->max_order_price ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- اطلاعات بانکی --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">اطلاعات بانکی</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره شبا</label>
            <input type="text" name="bank_sheba" value="{{ old('bank_sheba', $technician->bank_sheba ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="IR...">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره کارت</label>
            <input type="text" name="bank_card" value="{{ old('bank_card', $technician->bank_card ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره حساب</label>
            <input type="text" name="bank_account" value="{{ old('bank_account', $technician->bank_account ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- وضعیت --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">وضعیت</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $technician->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="ready_for_delivery" value="1" @checked(old('ready_for_delivery', $technician->ready_for_delivery ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">آماده دریافت سفارش جدید</span>
        </label>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت‌ها (داخلی)</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('notes', $technician->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>

<script>
(function () {
    const provinceEl = document.getElementById('tech-province');
    const cityEl = document.getElementById('tech-city');
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
