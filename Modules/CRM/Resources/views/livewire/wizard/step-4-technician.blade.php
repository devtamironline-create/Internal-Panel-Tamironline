<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">تخصیص تکنسین و زمان</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">این مرحله اختیاری است. می‌توانید بعداً تخصیص بدهید.</p>

    @if(! $this->canAssignTechnician)
        {{-- اپراتور بدون دسترسی assign-crm-technician — فقط پیام و
             تأکید بر اینکه سفارش با وضعیت «جدید» ثبت می‌شود. --}}
        <div class="rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-6 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-1">تخصیص تکنسین و زمان مجاز نیست</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                برای دسترسی به تخصیص تکنسین، روز و بازه ساعت نیاز به دسترسی
                «تخصیص تکنسین به سفارش CRM» دارید. سفارش با وضعیت «جدید» ثبت می‌شود
                و سرپرست پس از ثبت می‌تواند تخصیص دهد.
            </p>
        </div>
    @else
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">تکنسین</label>

        {{-- جستجو در نام/موبایل تکنسین --}}
        <div class="relative mb-3">
            <input type="text" wire:model.live.debounce.250ms="technicianSearch"
                   placeholder="جستجو با نام یا موبایل تکنسین..."
                   class="w-full px-4 py-2.5 pr-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <div class="space-y-2 max-h-[28rem] overflow-y-auto pr-1">
            {{-- بدون تکنسین --}}
            <label class="cursor-pointer block">
                <input type="radio" wire:model.live="technicianId" value="" class="peer sr-only">
                <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/30 transition flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">بعداً تخصیص می‌دهم</div>
                        <div class="text-xs text-gray-500">سفارش با وضعیت «جدید» ثبت می‌شود.</div>
                    </div>
                </div>
            </label>

            @forelse($this->technicianOptions as $t)
                <label wire:key="tech-{{ $t->id }}" class="cursor-pointer block">
                    <input type="radio" wire:model.live="technicianId" value="{{ $t->id }}" class="peer sr-only">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/30 transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-10 h-10 rounded-full {{ $t->over ? 'bg-red-100 text-red-700' : 'bg-brand-100 text-brand-700' }} flex items-center justify-center font-bold flex-shrink-0">
                                    {{ mb_substr($t->name ?: '?', 0, 1) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $t->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $t->mobile }}</div>
                                </div>
                            </div>
                            <div class="text-xs font-medium px-2 py-1 rounded-full {{ $t->over ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $t->percent }}%
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <span class="text-gray-500">سفارش‌های فعال</span>
                                <span class="font-medium {{ $t->over_orders ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ number_format($t->now_orders) }} / {{ $t->max_orders ?: '∞' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <span class="text-gray-500">بدهی</span>
                                <span class="font-medium {{ $t->over_debt ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ number_format($t->now_debt) }}
                                    @if($t->max_debt) / {{ number_format($t->max_debt) }} @endif
                                </span>
                            </div>
                        </div>

                        @if($t->over)
                            <div class="mt-2 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @if($t->over_orders && $t->over_debt)
                                    ظرفیت سفارش و بدهی پر شده — تخصیص توصیه نمی‌شود
                                @elseif($t->over_orders)
                                    ظرفیت سفارش‌های فعال پر است
                                @else
                                    سقف بدهی رد شده
                                @endif
                            </div>
                        @endif
                    </div>
                </label>
            @empty
                <div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
                    @if(trim($technicianSearch) !== '')
                        تکنسینی با این مشخصات پیدا نشد.
                    @else
                        تکنسین فعالی موجود نیست.
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">روز مراجعه پیشنهادی</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            @foreach($this->visitDays as $d)
                <label wire:key="vd-{{ $d['value'] }}" class="cursor-pointer">
                    <input type="radio" name="visitDate" wire:model.live="visitDate" value="{{ $d['value'] }}" class="peer sr-only">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/30 transition text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d['weekday'] }}</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 my-1">{{ $d['day'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d['month'] }}</div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">بازه ساعت</label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach(\Modules\CRM\Livewire\OrderWizard::VISIT_SLOTS as $key => $slot)
                <label wire:key="vs-{{ $key }}" class="cursor-pointer">
                    <input type="radio" name="visitSlot" wire:model.live="visitSlot" value="{{ $key }}" class="peer sr-only">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/30 transition text-center font-medium text-gray-900 dark:text-gray-100">
                        {{ $slot['label'] }}
                    </div>
                </label>
            @endforeach
        </div>

        @if($visitDate || $visitSlot)
            <button type="button" wire:click="clearVisitTime"
                    class="mt-3 text-xs text-gray-500 hover:text-red-600 transition">
                × پاک کردن انتخاب
            </button>
        @endif
        <p class="text-xs text-gray-500 mt-2">می‌توانید خالی بگذارید و بعداً تنظیم کنید.</p>
    </div>
    @endif
</div>
