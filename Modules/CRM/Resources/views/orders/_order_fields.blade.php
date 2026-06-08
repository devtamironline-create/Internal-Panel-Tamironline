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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ایراد دستگاه</label>
            @php
                // ایرادهای فعلی سفارش به‌صورت رشتهٔ «، »جداشده ذخیره شده‌اند
                // (هم‌ساختار با OrderWizard::submit). برای پیش‌انتخاب در
                // فرم آن را به آرایه می‌شکنیم.
                $currentObjections = old('objections',
                    array_filter(array_map('trim', preg_split('/،|,/u', $order->problem_title ?? '')))
                );
                if (! is_array($currentObjections)) {
                    $currentObjections = [];
                }
            @endphp
            @if(! empty($objectionsList))
                <div x-data="{
                    selected: @js(array_values($currentObjections)),
                    query: '',
                    norm(s) { return String(s ?? '').replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim(); },
                    matches(label) { return this.norm(label).includes(this.norm(this.query)); },
                    toggle(label) {
                        const i = this.selected.indexOf(label);
                        if (i === -1) this.selected.push(label); else this.selected.splice(i, 1);
                        this.query = '';
                    },
                }" class="space-y-2">
                    {{-- ایرادهای انتخاب‌شده — همیشه قابل دیدن --}}
                    <template x-if="selected.length">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="sel in selected" :key="sel">
                                <button type="button" @click="toggle(sel)"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-brand-100 text-brand-700 text-sm hover:bg-brand-200">
                                    <span x-text="sel"></span>
                                    <span class="text-xs">✕</span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- input سرچ --}}
                    <input type="text" x-model="query" placeholder="برای دیدن ایرادها، نام آن را جستجو کنید..."
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">

                    {{-- نتایج — فقط هنگام تایپ --}}
                    <div x-show="query.length > 0" x-cloak
                         class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                        @foreach($objectionsList as $opt)
                            <button x-show="!selected.includes(@js($opt)) && matches(@js($opt))" x-cloak
                                    type="button" @click="toggle(@js($opt))"
                                    class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 text-sm text-right">
                                {{ $opt }}
                            </button>
                        @endforeach
                    </div>

                    {{-- hidden inputs: یک آرایه که controller با implode به
                         رشتهٔ «، »جدا تبدیل می‌کند و در problem_title ذخیره می‌کند. --}}
                    <template x-for="sel in selected" :key="'h-'+sel">
                        <input type="hidden" name="objections[]" :value="sel">
                    </template>
                </div>
            @else
                {{-- fallback: اگر objectionsList تو تنظیمات WP نبود،
                     همان input متنی قبلی به‌عنوان gracefully degrade. --}}
                <input type="text" name="problem_title" value="{{ old('problem_title', $order->problem_title ?? '') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"
                       placeholder="مثلاً آب‌بندی نمی‌کند">
                <p class="text-xs text-amber-600 mt-1">⚠ لیست ایرادها از تنظیمات WP بارگیری نشد — می‌توانید متنی وارد کنید.</p>
            @endif
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
                    data-regions-url="{{ url('/admin/crm/cities') }}/__ID__/regions"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— ابتدا استان را انتخاب کنید —</option>
                @foreach($cities as $c)
                <option value="{{ $c->id }}" @selected(old('city_id', $order->city_id ?? null) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        {{-- منطقه — برای سفارش‌های واقعی (غیرلید) در شهرهایی که منطقه
             دارند الزامی است. شهرهای بدون منطقه (شهرستان‌های کوچک)
             dropdown با JS مخفی می‌شود. --}}
        @php
            $regionIsRequired = isset($order) && ! $order->is_lead;
        @endphp
        <div id="order-region-wrap"
             data-has-regions="{{ ($regions ?? collect())->count() ? '1' : '0' }}"
             data-region-required="{{ $regionIsRequired ? '1' : '0' }}">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                منطقه
                @if($regionIsRequired)
                    <span class="text-rose-600">*</span>
                @else
                    <span class="text-gray-400">(اختیاری)</span>
                @endif
            </label>
            <select name="region_id" id="order-region"
                    data-tom-select data-placeholder="منطقه..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">{{ $regionIsRequired ? '— انتخاب کنید —' : '— بدون منطقه —' }}</option>
                @foreach($regions ?? [] as $r)
                <option value="{{ $r->id }}" @selected(old('region_id', $order->region_id ?? null) == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
            @error('region_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
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

{{-- فیلدهای لید — فقط برای رکوردهای با is_lead=true. اپراتور می‌تواند
     دلیل و یادداشت لید را اینجا ببیند و ویرایش کند. اگر سفارش لید نیست
     این بخش رندر نمی‌شود. --}}
@if(isset($order) && $order->is_lead)
    <div class="mb-6 bg-rose-50 dark:bg-rose-900/10 border-2 border-rose-200 dark:border-rose-700 rounded-xl p-4">
        <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300 mb-3">⚠ اطلاعات لید (تماس غیرقابل سفارش)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دلیل عدم سفارش</label>
                <select name="lead_reason_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— انتخاب کنید —</option>
                    @foreach(\Modules\CRM\Models\LeadReason::active()->ordered()->get() as $lr)
                        <option value="{{ $lr->id }}" @selected(old('lead_reason_id', $order->lead_reason_id) == $lr->id)>{{ $lr->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت لید (متن وارد شده توسط اپراتور هنگام ثبت)</label>
            <textarea name="lead_notes" rows="4" placeholder="یادداشت اپراتور…"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('lead_notes', $order->lead_notes ?? '') }}</textarea>
        </div>
    </div>
@endif

{{-- یادداشت داخلی --}}
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت داخلی</label>
    <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('notes', $order->notes ?? '') }}</textarea>
</div>

<script>
(function () {
    const provinceEl = document.getElementById('order-province');
    const cityEl = document.getElementById('order-city');
    const regionEl = document.getElementById('order-region');
    const regionWrap = document.getElementById('order-region-wrap');
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

    // dropdown منطقه — اگر شهر منطقه ندارد، wrapper مخفی می‌شود.
    // متن placeholder بر اساس اجباری بودن منطقه عوض می‌شود تا اپراتور
    // متوجه شود انتخاب لازم است.
    const regionRequired = regionWrap && regionWrap.dataset.regionRequired === '1';
    const regionPlaceholder = regionRequired ? '— انتخاب کنید —' : '— بدون منطقه —';
    function setRegionOptions(items) {
        if (!regionEl) return;
        const ts = regionEl.tomselect;
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            if (!items.length) {
                if (regionWrap) regionWrap.style.display = 'none';
                return;
            }
            if (regionWrap) regionWrap.style.display = '';
            ts.addOption([
                { value: '', text: regionPlaceholder },
                ...items.map(r => ({ value: String(r.id), text: r.name })),
            ]);
            ts.refreshOptions(false);
            ts.enable();
        } else {
            if (!items.length) {
                if (regionWrap) regionWrap.style.display = 'none';
                return;
            }
            if (regionWrap) regionWrap.style.display = '';
            regionEl.innerHTML = `<option value="">${regionPlaceholder}</option>` +
                items.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
        }
    }

    async function fetchRegions(cityId) {
        if (!regionEl || !cityId) {
            setRegionOptions([]);
            return;
        }
        try {
            const url = cityEl.dataset.regionsUrl.replace('__ID__', cityId);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            setRegionOptions(Array.isArray(data) ? data : []);
        } catch (e) {
            setRegionOptions([]);
        }
    }

    // وضعیت اولیه: اگر این شهر منطقه ندارد، wrapper را مخفی کن
    if (regionWrap && regionWrap.dataset.hasRegions === '0') {
        regionWrap.style.display = 'none';
    }

    provinceEl.addEventListener('change', async function () {
        const id = this.value;
        resetCity('در حال بارگذاری...');
        setRegionOptions([]); // پاک کردن مناطق چون شهر هم پاک می‌شود
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

    cityEl.addEventListener('change', function () {
        const id = this.value;
        fetchRegions(id);
    });
})();
</script>
