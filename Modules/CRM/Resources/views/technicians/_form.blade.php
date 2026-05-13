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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام نمایشی تکنسین</label>
            <input type="text" name="firstname_tech" value="{{ old('firstname_tech', $technician->firstname_tech ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500"
                   placeholder="نام کامل/برند تکنسین">
            @error('firstname_tech')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد ملی</label>
            <input type="text" name="national_code" value="{{ old('national_code', $technician->national_code ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کد تکنسین (اختیاری)</label>
            <input type="text" name="technician_id" value="{{ old('technician_id', $technician->technician_id ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500"
                   placeholder="مثلاً T405001">
            @error('technician_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

{{-- آدرس --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">آدرس</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <input type="text" name="province" value="{{ old('province', $technician->province ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="نام استان">
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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سطح/نوع تکنسین</label>
            <input type="text" name="type_tech" value="{{ old('type_tech', $technician->type_tech ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="مثلاً regular / senior / expert / freelance">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('description', $technician->description ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس عکس شخصی (URL)</label>
            <input type="text" name="img_personal" value="{{ old('img_personal', $technician->img_personal ?? '') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس تصویر کارت ملی (URL)</label>
            <input type="text" name="cart_img" value="{{ old('cart_img', $technician->cart_img ?? '') }}" dir="ltr"
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
            <input type="number" name="percent" min="0" max="100" value="{{ old('percent', $technician->percent ?? 0) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">درصد دوم (tech_per_of_all)</label>
            <input type="number" name="tech_per_of_all" min="0" max="100" value="{{ old('tech_per_of_all', $technician->tech_per_of_all ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">روش محاسبه</label>
            <input type="text" name="type_of_calc_tech" value="{{ old('type_of_calc_tech', $technician->type_of_calc_tech ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                   placeholder="percent_of_customer / percent_of_total">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سقف تعداد سفارش همزمان (خالی = نامحدود)</label>
            <input type="number" name="max_order" min="0" value="{{ old('max_order', $technician->max_order ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سقف مبلغ سفارش (تومان - خالی = نامحدود)</label>
            <input type="number" name="max_price" min="0" value="{{ old('max_price', $technician->max_price ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
    </div>
</div>

{{-- وضعیت --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">وضعیت</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="active" @selected(old('status', $technician->status ?? 'active') === 'active')>فعال</option>
                <option value="inactive" @selected(old('status', $technician->status ?? '') === 'inactive')>غیرفعال</option>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 mt-7">
            <input type="checkbox" name="ready_for_delivery" value="1" @checked(old('ready_for_delivery', $technician->ready_for_delivery ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">آماده دریافت سفارش جدید</span>
        </label>
    </div>
</div>

{{-- جهت سینک با WP CRM (per-technician) --}}
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
        جهت سینک با WordPress CRM
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-6">
        به‌صورت پیش‌فرض، WP منبع حقیقت است و تغییرات Laravel به WP push نمی‌شود.
        اگر می‌خواهی تغییرات این تکنسین از پنل به WP هم برگردد، گزینهٔ
        «دوطرفه» یا «فقط از پنل به WP» را انتخاب کن.
    </p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جهت سینک سفارش</label>
            <select name="order_sync_direction"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                @foreach (\Modules\CRM\Models\Technician::SYNC_DIRECTIONS as $value => $label)
                    <option value="{{ $value }}"
                            @selected(old('order_sync_direction', $technician->order_sync_direction ?? 'wp_to_laravel') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جهت سینک کیف‌پول</label>
            <select name="wallet_sync_direction"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                @foreach (\Modules\CRM\Models\Technician::SYNC_DIRECTIONS as $value => $label)
                    <option value="{{ $value }}"
                            @selected(old('wallet_sync_direction', $technician->wallet_sync_direction ?? 'wp_to_laravel') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- ─── تخصص برای سیستم پیشنهاد هوشمند ─── --}}
@if(isset($allCities) && isset($allBrands) && isset($allDevices))
<div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6 space-y-4">
    <div>
        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">تخصص برای پیشنهاد هوشمند</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            این مقادیر تعیین می‌کنند چه سفارش‌هایی به این تکنسین پیشنهاد شود.
            بدون تگ‌گذاری، تکنسین در پیشنهادها ظاهر نمی‌شود.
        </p>
    </div>

    {{-- اسکریپت کوچک کمکی برای انتخاب/لغو همه روی یک container.
         با data-toggle-all روی دکمه و data-group روی container کار می‌کند. --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">شهرهای فعال</label>
            <div class="flex gap-2">
                <button type="button" data-toggle-all="select" data-group="cities"
                        class="text-xs px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200">
                    انتخاب همه
                </button>
                <button type="button" data-toggle-all="clear" data-group="cities"
                        class="text-xs px-2 py-1 rounded bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200">
                    حذف همه
                </button>
            </div>
        </div>
        <div data-group="cities" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-64 overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
            @foreach($allCities as $c)
                <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                    <input type="checkbox" name="city_ids[]" value="{{ $c->id }}"
                           @checked(in_array($c->id, $selectedCityIds ?? []))
                           class="w-4 h-4 accent-brand-600">
                    <span>{{ $c->name }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">برندهای تخصصی</label>
                <div class="flex gap-2">
                    <button type="button" data-toggle-all="select" data-group="brands"
                            class="text-xs px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200">
                        انتخاب همه
                    </button>
                    <button type="button" data-toggle-all="clear" data-group="brands"
                            class="text-xs px-2 py-1 rounded bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200">
                        حذف همه
                    </button>
                </div>
            </div>
            <div data-group="brands" class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                @foreach($allBrands as $b)
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                        <input type="checkbox" name="brand_ids[]" value="{{ $b->id }}"
                               @checked(in_array($b->id, $selectedBrandIds ?? []))
                               class="w-4 h-4 accent-brand-600">
                        <span>{{ $b->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">دستگاه‌های قابل انجام</label>
                <div class="flex gap-2">
                    <button type="button" data-toggle-all="select" data-group="devices"
                            class="text-xs px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200">
                        انتخاب همه
                    </button>
                    <button type="button" data-toggle-all="clear" data-group="devices"
                            class="text-xs px-2 py-1 rounded bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200">
                        حذف همه
                    </button>
                </div>
            </div>
            <div data-group="devices" class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                @foreach($allDevices as $d)
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                        <input type="checkbox" name="device_ids[]" value="{{ $d->id }}"
                               @checked(in_array($d->id, $selectedDeviceIds ?? []))
                               class="w-4 h-4 accent-brand-600">
                        <span>{{ $d->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('button[data-toggle-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.getAttribute('data-group');
                var mode = btn.getAttribute('data-toggle-all'); // select | clear
                var box = document.querySelector('div[data-group="' + group + '"]');
                if (! box) return;
                box.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = (mode === 'select');
                });
            });
        });
    })();
    </script>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">امتیاز رضایت مشتری (۰ تا ۵)</label>
        <input type="number" name="satisfaction_score" min="0" max="5" step="0.1"
               value="{{ old('satisfaction_score', $technician->satisfaction_score) }}"
               class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        <p class="text-xs text-gray-400 mt-1">با یک رقم اعشار. خالی = استفاده از مقدار میانگین (۲.۵).</p>
    </div>
</div>
@endif

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
