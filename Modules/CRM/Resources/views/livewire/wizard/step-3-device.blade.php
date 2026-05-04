<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">دستگاه و ایراد</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">نوع سفارش، دستگاه و مشکل را مشخص کنید.</p>

    {{-- نوع سفارش (تعمیر / سرویس) --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">نوع سفارش *</label>
        <div class="grid grid-cols-2 gap-3">
            @foreach([
                'repair'  => ['title' => 'تعمیر', 'desc' => 'دستگاه ایراد دارد', 'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
                'service' => ['title' => 'سرویس', 'desc' => 'سرویس دوره‌ای', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ] as $key => $opt)
                <label class="cursor-pointer">
                    <input type="radio" wire:model="orderType" value="{{ $key }}" class="peer sr-only">
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

    {{-- ایرادات (multi-select با سرچ) --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">ایراد دستگاه (می‌توانید چند مورد را انتخاب کنید)</label>
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

                {{-- نتایج فقط زمانی نمایش داده شوند که کاربر چیزی تایپ کرده --}}
                <div x-show="query.length > 0" x-cloak class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach($this->objectionsList as $i => $opt)
                        @php $checked = in_array($opt, $objections, true); @endphp
                        <button x-show="matches(@js($opt))" x-cloak
                                wire:key="obj-{{ $i }}" type="button" wire:click="toggleObjection(@js($opt))"
                                class="px-3 py-2 rounded-lg border text-sm text-right transition
                                       {{ $checked ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-700 dark:text-gray-300' }}">
                            @if($checked) ✓ @endif
                            {{ $opt }}
                        </button>
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

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شرح ایراد / توضیحات اضافی</label>
        <textarea wire:model="objectionDescription" rows="3"
                  placeholder="جزئیات بیشتر مشکل دستگاه..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>
</div>
