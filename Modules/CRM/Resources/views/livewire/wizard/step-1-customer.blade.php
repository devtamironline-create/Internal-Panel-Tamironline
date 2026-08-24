<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">انتخاب مشتری</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">یک مشتری موجود را انتخاب کنید یا مشتری جدید ثبت کنید.</p>

    @if(! $showNewCustomerForm)
        {{-- جستجوی مشتری موجود --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                جستجو با موبایل یا نام
            </label>

            @if($this->selectedCustomer)
                {{-- مشتری انتخاب‌شده --}}
                <div class="flex items-center justify-between gap-3 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center font-bold">
                            {{ mb_substr($this->selectedCustomer->display_name ?: '?', 0, 1) }}
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $this->selectedCustomer->display_name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400" dir="ltr">
                                {{ $this->selectedCustomer->mobile }}
                                @if($this->selectedCustomer->phone) · {{ $this->selectedCustomer->phone }} @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">شماره اشتراک: {{ $this->selectedCustomer->subscription }}</div>
                        </div>
                    </div>
                    <button type="button" wire:click="clearCustomer" class="text-red-600 hover:text-red-800 text-sm">
                        تغییر
                    </button>
                </div>
            @else
                <input
                    type="text"
                    wire:model.live.debounce.250ms="customerSearch"
                    placeholder="موبایل (مثلاً 0912...) یا نام"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">

                @if($customerSearch && $this->customerSuggestions->count())
                    <div class="mt-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm divide-y divide-gray-100 dark:divide-gray-600 max-h-72 overflow-y-auto">
                        @foreach($this->customerSuggestions as $c)
                            <button
                                wire:key="suggest-{{ $c->id }}"
                                type="button"
                                wire:click.prevent="selectCustomer({{ $c->id }})"
                                class="w-full text-right px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center justify-between cursor-pointer">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $c->first_name ?: '—' }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ $c->mobile }}</div>
                                </div>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endforeach
                    </div>
                @elseif($customerSearch && trim($customerSearch) !== '')
                    <p class="mt-2 text-sm text-gray-500">مشتری‌ای پیدا نشد.</p>
                @endif
            @endif
        </div>

        <div class="text-center">
            <button type="button" wire:click="toggleNewCustomerForm" class="text-brand-600 hover:text-brand-700 text-sm font-medium">
                + افزودن مشتری جدید
            </button>
        </div>
    @else
        {{-- فرم سریع مشتری جدید --}}
        <div class="space-y-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <h3 class="font-medium text-gray-900 dark:text-gray-100">مشتری جدید</h3>
                <button type="button" wire:click="toggleNewCustomerForm" class="text-sm text-gray-500 hover:text-gray-700">
                    ← انتخاب از موجود
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام و نام خانوادگی *</label>
                    <input type="text" wire:model="newName"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره تماس (موبایل یا ثابت) *</label>
                    {{-- `blur` تا نرمال‌سازیِ سمتِ سرور موقعِ تایپ‌کردن شماره را
                         وسطِ کار عوض نکند. inputmode برای کیبوردِ عددی موبایل. --}}
                    <input type="tel" wire:model.blur="newMobile" maxlength="11" dir="ltr"
                           inputmode="numeric" placeholder="09123456789 یا 02177612345"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-100
                                  @error('newMobile') border-rose-500 dark:border-rose-500 @else border-gray-300 dark:border-gray-600 @enderror">
                    @error('newMobile')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تلفن ثابت</label>
                    <input type="tel" wire:model="newPhone" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شماره اشتراک</label>
                    <input type="text" wire:model="subscription" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
            </div>
        </div>
    @endif

    {{-- منطقه و آدرس — فقط برای سفارش‌های قابل ثبت. لیدها نه آدرس
         دقیق نیاز دارند و نه منطقه. اگر شهر منطقه نداشته باشد
         (کوچک‌تر است)، dropdown منطقه نمایش داده نمی‌شود. --}}
    @if($isOrderable && $this->regions->count())
        <div wire:key="region-select-{{ $cityId }}">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                منطقه {{ $this->selectedCity ? '— ' . $this->selectedCity->name : '' }}
                <span class="text-rose-600">*</span>
            </label>
            {{-- منطقه‌ای که برای دستگاهِ انتخابی تکنسین ندارد، قابلِ انتخاب
                 برای «سفارش» نیست (disabled + برچسب) — لید محدودیتی ندارد. --}}
            @php $regionCov = $this->regionCoverage; @endphp
            {{-- @region-detected: تشخیصِ خودکارِ منطقه از آدرس، مقدار را سمتِ
                 سرور ست می‌کند؛ اینجا value و یک attribute را هم دستی به‌روز
                 می‌کنیم تا dropdown قابل‌سرچ (MutationObserver روی attributes)
                 برچسبِ انتخاب را نشان بدهد. --}}
            <select wire:model="regionId" data-searchable
                    x-data
                    @region-detected.window="$el.value = String($event.detail.id);
                        $el.dispatchEvent(new Event('change', { bubbles: true }));
                        $el.setAttribute('data-detected', $event.detail.id)"
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->regions as $r)
                    @php $covered = $regionCov === null || ($regionCov[$r->id] ?? false); @endphp
                    <option value="{{ $r->id }}" @disabled(! $covered)>
                        {{ $r->name }}{{ $covered ? '' : ' — بدون تکنسین (فقط لید)' }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">برای این شهر انتخاب منطقه الزامی است. منطقه‌های بدون تکنسینِ فعال فقط برای ثبت لید ممکن‌اند.</p>
            @error('regionId')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if($isOrderable)
        <div wire:key="address-wrap-{{ $customerId ?? 'new' }}">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                آدرس کامل <span class="text-rose-600">*</span>
            </label>
            {{-- Alpine listener برای customer-prefilled — اگر Livewire
                 textarea را morph نکرد، اینجا دستی مقدار را می‌گذاریم. --}}
            <textarea wire:model="address" rows="3"
                      placeholder="خیابان، پلاک، واحد..."
                      x-data
                      @customer-prefilled.window="$el.value = $event.detail.address; $el.dispatchEvent(new Event('input'))"
                      class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
            @if($this->selectedCustomer && $address)
                <p class="text-xs text-emerald-600 mt-1">✓ آدرس آخرین سفارش این مشتری به‌صورت خودکار پر شد — در صورت نیاز ویرایش کنید.</p>
            @endif
            @error('address')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror

            {{-- تشخیص خودکار منطقه از آدرس — فقط برای شهرهای منطقه‌دار.
                 اول متنِ آدرس (رایگان)، بعد نقشهٔ نشان. نتیجه پیشنهاد است
                 و اپراتور می‌تواند در dropdown منطقه عوضش کند. --}}
            @if($this->regions->count())
                {{-- wire:key با cityId: با عوض‌شدن شهر، نقشه از نو با مرکزِ
                     شهرِ جدید ساخته می‌شود. نقشه Leaflet لوکال + OSM است و
                     به کلیدِ Web نشان نیازی ندارد (تشخیصِ منطقه سمتِ سرور
                     با REST نشان انجام می‌شود). --}}
                <div wire:key="region-detect-tools-{{ $cityId }}"
                     x-data="window.wizardRegionMap(null, @js($this->mapCenter))">
                    <div class="mt-2 flex items-center gap-3 flex-wrap">
                        <button type="button"
                                wire:click="detectRegionFromAddress"
                                wire:loading.attr="disabled"
                                wire:target="detectRegionFromAddress"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-brand-300 text-brand-700 hover:bg-brand-50 dark:border-brand-600 dark:text-brand-300 dark:hover:bg-brand-900/30 disabled:opacity-50">
                            📍 تشخیص خودکار منطقه از آدرس
                        </button>
                        <button type="button" @click="toggleMap()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            🗺 <span x-text="open ? 'بستن نقشه' : 'انتخاب روی نقشه'">انتخاب روی نقشه</span>
                        </button>
                        <span wire:loading wire:target="detectRegionFromAddress" class="text-xs text-gray-500">
                            در حال بررسی آدرس روی نقشه…
                        </span>
                        <span wire:loading wire:target="selectPointOnMap" class="text-xs text-gray-500">
                            در حال تشخیص منطقهٔ نقطهٔ انتخابی…
                        </span>
                    </div>

                    @if($regionDetectMessage)
                        <p class="text-xs mt-1.5 {{ match($regionDetectStatus) {
                            'ok' => 'text-emerald-600 dark:text-emerald-400',
                            'warn' => 'text-amber-600 dark:text-amber-400',
                            default => 'text-rose-600 dark:text-rose-400',
                        } }}">{{ $regionDetectMessage }}</p>
                    @endif

                    <div x-show="open" x-collapse x-cloak class="mt-2">
                        <p class="text-xs text-gray-500 mb-1.5">
                            روی محلِ آدرس مشتری کلیک کنید — منطقه به‌صورت خودکار انتخاب می‌شود و اگر فیلد آدرس خالی باشد، آدرسِ نقطه هم داخلش می‌نشیند. marker را می‌توانید جابه‌جا کنید.
                        </p>
                        <p x-show="loadingMap" class="text-xs text-gray-500 mb-1.5">در حال بارگذاری نقشه…</p>
                        <p x-show="failedMap" class="text-xs text-rose-600 mb-1.5">نقشه بارگذاری نشد — صفحه را رفرش کنید یا منطقه را دستی انتخاب کنید.</p>
                        {{-- wire:ignore: DOMِ داخلیِ Leaflet نباید توسط Livewire morph شود. --}}
                        <div wire:ignore x-ref="mapBox"
                             class="w-full rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden"
                             style="height: 320px; direction: ltr; z-index: 0;"></div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- معرف --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نحوه آشنایی (معرف) <span class="text-rose-600">*</span></label>
        @if(count($this->introductionList))
            <select wire:model="introduction" data-searchable class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->introductionList as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        @else
            <input type="text" wire:model="introduction" placeholder="مثلاً اینستاگرام"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            <p class="text-xs text-amber-600 mt-1">⚠ لیست معرف از تنظیمات WP بارگیری نشد. ابتدا «سینک تنظیمات» را در WP بزنید.</p>
        @endif
    </div>
</div>
