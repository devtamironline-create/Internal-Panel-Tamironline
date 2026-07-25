<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">دستگاه و ایراد</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">نوع سفارش، دستگاه و مشکل را مشخص کنید. اگر تماس منجر به سفارش نمی‌شود، تَوگل «قابل سفارش» را خاموش کنید.</p>

    {{-- تَوگل قابل سفارش (روی دستگاه اصلی) --}}
    <div class="flex items-center justify-between p-3 rounded-xl border-2 {{ $isOrderable ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/40' }}">
        <div>
            <div class="font-bold {{ $isOrderable ? 'text-emerald-800' : 'text-rose-800' }}">
                {{ $isOrderable ? '✓ قابل سفارش' : '✗ غیرقابل سفارش — به‌عنوان لید ثبت می‌شود' }}
            </div>
            <div class="text-xs text-gray-500 mt-0.5">
                {{ $isOrderable ? 'این تماس تبدیل به سفارش می‌شود.' : 'فقط اطلاعات تماس برای گزارش‌گیری ذخیره می‌شود.' }}
            </div>
        </div>
        <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" wire:model.live="isOrderable" class="sr-only peer">
            <div class="relative w-12 h-6 bg-gray-300 peer-checked:bg-emerald-500 rounded-full transition-colors">
                <div class="absolute top-0.5 start-0.5 bg-white w-5 h-5 rounded-full transition-transform peer-checked:translate-x-6 rtl:peer-checked:-translate-x-6"></div>
            </div>
        </label>
    </div>

    {{-- بخش لید — وقتی غیرقابل سفارش است --}}
    @if(! $isOrderable)
        <div class="space-y-3 p-4 rounded-xl bg-rose-50/30 border border-rose-200">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دلیل عدم امکان سفارش <span class="text-rose-600">*</span></label>
                <select wire:model="leadReasonId" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— یک گزینه را انتخاب کنید —</option>
                    @foreach($this->leadReasons as $lr)
                        <option value="{{ $lr->id }}">{{ $lr->name }}</option>
                    @endforeach
                </select>
                @error('leadReasonId')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت‌ها</label>
                <textarea wire:model="leadNotes" rows="2" placeholder="هرگونه توضیح اضافی…"
                          class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg"></textarea>
            </div>
        </div>
    @endif

    {{-- نوع سفارش (تعمیر / سرویس) — فقط در حالت قابل سفارش --}}
    <div @class(['hidden' => ! $isOrderable])>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">نوع سفارش *</label>
        <div class="grid grid-cols-2 gap-3">
            @foreach([
                'repair'  => ['title' => 'تعمیر', 'desc' => 'دستگاه ایراد دارد', 'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
                'service' => ['title' => 'نصب', 'desc' => 'نصب دستگاه', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ] as $key => $opt)
                <label class="cursor-pointer">
                    <input type="radio" name="orderType" wire:model="orderType" value="{{ $key }}" class="peer sr-only">
                    <div class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/30 transition flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 peer-checked:bg-brand-500 flex items-center justify-center text-gray-500 peer-checked:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $opt['icon'] }}"/></svg>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $opt['title'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $opt['desc'] }}</div>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع دستگاه *</label>
            <select wire:model="deviceId" data-searchable class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->devices as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برند *</label>
            <select wire:model="brandId" data-searchable class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->brands as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ایرادات (multi-select با سرچ) — فقط در حالت قابل سفارش --}}
    <div @class(['hidden' => ! $isOrderable])>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">ایراد دستگاه (می‌توانید چند مورد را انتخاب کنید) <span class="text-rose-600">*</span></label>
        @error('objections')<p class="text-xs text-rose-600 mb-2">{{ $message }}</p>@enderror
        @if(count($this->objectionsList))
            <div x-data="{
                query: '',
                norm(s) { return String(s ?? '').replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim(); },
                matches(label) { return this.norm(label).includes(this.norm(this.query)); }
            }">
                {{-- ایرادهای انتخاب‌شده — همیشه قابل دیدن --}}
                @if(count($objections))
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach($objections as $sel)
                            <button type="button" wire:click="toggleObjection(@js($sel))"
                                    wire:key="sel-{{ md5($sel) }}"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 text-sm hover:bg-brand-200 dark:hover:bg-brand-900/60 transition">
                                <span>{{ $sel }}</span>
                                <span class="text-xs">✕</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- input سرچ --}}
                <input type="text" x-model="query" placeholder="برای دیدن ایرادها، نام آن را جستجو کنید..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">

                {{-- نتایج فقط زمانی نمایش داده شوند که کاربر چیزی تایپ کرده.
                     آیتم‌های انتخاب‌شده از نتایج خارج می‌شوند (فقط به‌صورت
                     chip بالا دیده می‌شوند) — برای deselect باید روی chip
                     کلیک کرد. --}}
                <div x-show="query.length > 0" x-cloak class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach($this->objectionsList as $i => $opt)
                        @php $checked = in_array($opt, $objections, true); @endphp
                        @if(! $checked)
                        <button x-show="matches(@js($opt))" x-cloak
                                wire:key="obj-{{ $i }}" type="button" wire:click="toggleObjection(@js($opt))"
                                class="px-3 py-2 rounded-lg border text-sm text-right transition border-gray-200 dark:border-gray-600 hover:border-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 text-gray-700 dark:text-gray-300">
                            {{ $opt }}
                        </button>
                        @endif
                    @endforeach
                </div>

                @if(count($objections))
                    <div class="mt-2 text-xs text-gray-500">{{ count($objections) }} مورد انتخاب شده</div>
                @endif
            </div>
        @else
            <p class="text-sm text-amber-600">⚠ لیست ایرادها از تنظیمات WP بارگیری نشد. ابتدا «سینک تنظیمات» را در WP بزنید.</p>
        @endif
    </div>

    {{-- شرح ایراد — فقط در حالت قابل سفارش --}}
    <div @class(['hidden' => ! $isOrderable])>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شرح ایراد / توضیحات اضافی</label>
        <textarea wire:model="objectionDescription" rows="3"
                  placeholder="جزئیات بیشتر مشکل دستگاه..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>

    {{-- ─── دستگاه‌های اضافه ─── --}}
    @foreach($extraDevices as $i => $ed)
        <div wire:key="extra-device-{{ $i }}"
             class="bg-indigo-50/40 dark:bg-indigo-900/10 border-2 border-indigo-200 dark:border-indigo-700 rounded-xl p-4 space-y-4 relative">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-indigo-700 dark:text-indigo-300">📱 دستگاه اضافه #{{ $i + 1 }}</h3>
                <button type="button" wire:click="removeExtraDevice({{ $i }})"
                        class="text-xs text-rose-600 hover:text-rose-800 px-2 py-1 rounded hover:bg-rose-50">
                    × حذف این دستگاه
                </button>
            </div>

            @php $edOrderable = (bool) ($ed['is_orderable'] ?? true); @endphp

            {{-- تَوگل قابل سفارش — مستقل برای همین دستگاه (لید و سفارش همزمان ممکن است) --}}
            <div class="flex items-center justify-between p-3 rounded-xl border-2 {{ $edOrderable ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/40' }}">
                <div>
                    <div class="font-bold text-sm {{ $edOrderable ? 'text-emerald-800' : 'text-rose-800' }}">
                        {{ $edOrderable ? '✓ قابل سفارش' : '✗ غیرقابل سفارش — این دستگاه به‌عنوان لید ثبت می‌شود' }}
                    </div>
                    <div class="text-[11px] text-gray-500 mt-0.5">
                        {{ $edOrderable ? 'برای این دستگاه سفارش ساخته می‌شود.' : 'برای این دستگاه فقط رکوردِ لید (گزارش‌گیری) ذخیره می‌شود.' }}
                    </div>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="extraDevices.{{ $i }}.is_orderable" class="sr-only peer">
                    <div class="relative w-12 h-6 bg-gray-300 peer-checked:bg-emerald-500 rounded-full transition-colors">
                        <div class="absolute top-0.5 start-0.5 bg-white w-5 h-5 rounded-full transition-transform peer-checked:translate-x-6 rtl:peer-checked:-translate-x-6"></div>
                    </div>
                </label>
            </div>

            {{-- بخش لید این دستگاه — وقتی غیرقابل سفارش است --}}
            @if(! $edOrderable)
                <div class="space-y-3 p-3 rounded-xl bg-rose-50/30 border border-rose-200">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">دلیل عدم امکان سفارش <span class="text-rose-600">*</span></label>
                        <select wire:model="extraDevices.{{ $i }}.lead_reason_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                            <option value="">— یک گزینه را انتخاب کنید —</option>
                            @foreach($this->leadReasons as $lr)
                                <option value="{{ $lr->id }}">{{ $lr->name }}</option>
                            @endforeach
                        </select>
                        @error('extraDevices.'.$i.'.lead_reason_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">یادداشت‌ها</label>
                        <textarea wire:model="extraDevices.{{ $i }}.lead_notes" rows="2" placeholder="هرگونه توضیح اضافی…"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
                    </div>
                </div>
            @endif

            {{-- نوع سفارش (تعمیر / نصب) — فقط در حالت قابل سفارش --}}
            <div @class(['hidden' => ! $edOrderable])>
                <label class="block text-xs font-medium text-gray-700 mb-1">نوع سفارش *</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['repair' => 'تعمیر', 'service' => 'نصب'] as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="extraDevices.{{ $i }}.order_type" value="{{ $key }}" class="peer sr-only">
                            <div class="px-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-lg text-sm text-center peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/30 transition">
                                {{ $label }}
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دستگاه *</label>
                    <select wire:model="extraDevices.{{ $i }}.device_id" data-searchable
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">— انتخاب —</option>
                        @foreach($this->devices as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">برند *</label>
                    <select wire:model="extraDevices.{{ $i }}.brand_id" data-searchable
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">— انتخاب —</option>
                        @foreach($this->brands as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- ایرادات این دستگاه — فقط در حالت قابل سفارش --}}
            @if(count($this->objectionsList) && $edOrderable)
            <div x-data="{ q: '', norm(s) { return String(s ?? '').replace(/[يﻱ]/g,'ی').replace(/[كﻙ]/g,'ک').toLowerCase().trim(); }, m(l) { return this.norm(l).includes(this.norm(this.q)); } }">
                <label class="block text-xs font-medium text-gray-700 mb-1">ایراد دستگاه <span class="text-rose-600">*</span></label>
                @error('extraDevices.'.$i.'.objections')<p class="text-xs text-rose-600 mb-1">{{ $message }}</p>@enderror
                @php $selObjections = $ed['objections'] ?? []; @endphp
                @if(count($selObjections))
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @foreach($selObjections as $sel)
                            <button type="button" wire:click="toggleExtraObjection({{ $i }}, @js($sel))"
                                    wire:key="extra-{{ $i }}-sel-{{ md5($sel) }}"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs hover:bg-indigo-200">
                                {{ $sel }} <span class="text-[10px]">✕</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                <input type="text" x-model="q" placeholder="جستجوی ایراد..."
                       class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs">
                <div x-show="q.length > 0" x-cloak class="mt-1 grid grid-cols-2 md:grid-cols-3 gap-1">
                    @foreach($this->objectionsList as $j => $opt)
                        @if(! in_array($opt, $selObjections, true))
                        <button x-show="m(@js($opt))" x-cloak type="button"
                                wire:click="toggleExtraObjection({{ $i }}, @js($opt))"
                                wire:key="extra-{{ $i }}-opt-{{ $j }}"
                                class="px-2 py-1 rounded border text-xs text-right border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 text-gray-700">
                            {{ $opt }}
                        </button>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            <div @class(['hidden' => ! $edOrderable])>
                <label class="block text-xs font-medium text-gray-700 mb-1">شرح ایراد</label>
                <textarea wire:model="extraDevices.{{ $i }}.objection_description" rows="2"
                          placeholder="جزئیات بیشتر..."
                          class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm"></textarea>
            </div>
        </div>
    @endforeach

    {{-- دکمه افزودن دستگاه دیگر --}}
    <div class="pt-2">
        <button type="button" wire:click="addExtraDevice"
                class="w-full px-4 py-3 border-2 border-dashed border-indigo-300 dark:border-indigo-700 rounded-xl text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-sm font-bold transition-colors">
            + افزودن دستگاه دیگر (سفارش جداگانه ساخته می‌شود)
        </button>
        @if(! empty($extraDevices))
            @php
                $totalDevices = count($extraDevices) + 1;
                $leadCount = (int) (! $isOrderable);
                foreach ($extraDevices as $edRow) {
                    if (! (bool) ($edRow['is_orderable'] ?? true)) $leadCount++;
                }
                $orderCount = $totalDevices - $leadCount;
            @endphp
            <p class="text-[10px] text-gray-500 text-center mt-2">
                با ثبت نهایی، <b>{{ $totalDevices }} رکورد جداگانه</b> با اطلاعات مشترک مشتری/آدرس ساخته می‌شود
                @if($leadCount > 0 && $orderCount > 0)
                    (<b class="text-emerald-700">{{ $orderCount }} سفارش</b> + <b class="text-rose-700">{{ $leadCount }} لید</b>).
                @elseif($leadCount === $totalDevices)
                    (همه به‌صورت <b class="text-rose-700">لید</b>).
                @else
                    (همه به‌صورت <b class="text-emerald-700">سفارش</b>).
                @endif
            </p>
        @endif
    </div>
</div>
