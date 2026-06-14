<div class="p-4 md:p-6 max-w-5xl mx-auto" wire:key="service-area-root">
    <div class="mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مدیریت مناطق و محدودهٔ سرویس‌دهی اپ</h1>
        <p class="text-xs text-gray-500 mt-1 leading-6">
            استان‌ها، شهرها و مناطق همگی در همین صفحه مدیریت می‌شوند. نمایش هر مورد در اپلیکیشن موبایل مشتری
            فقط با تاگل <span class="font-medium">«نمایش در اپ»</span> کنترل می‌شود؛ موردی که اینجا غیرفعال شود از اپ حذف می‌گردد
            (بدون حذف داده). برای دیدن شهرهای هر استان روی آن کلیک کنید و برای دیدن مناطق هر شهر روی آن.
        </p>
    </div>

    @if($flashMessage)
        <div class="mb-4 rounded-lg p-3 text-sm border
            {{ $flashType === 'error'
                ? 'bg-rose-50 border-rose-200 text-rose-800'
                : 'bg-emerald-50 border-emerald-200 text-emerald-800' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- نوار ابزار: جست‌وجو + افزودن استان --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="جست‌وجوی استان…"
                   class="w-full pr-9 pl-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <form wire:submit="addProvince" class="flex gap-2">
            <input type="text" wire:model="newProvinceName" placeholder="نام استان جدید"
                   class="w-44 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <button type="submit" class="px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">+ استان</button>
        </form>
    </div>
    @error('newProvinceName')<p class="text-xs text-rose-600 mb-3 -mt-2">{{ $message }}</p>@enderror

    {{-- درخت استان‌ها --}}
    <div class="space-y-2">
        @forelse($this->provinces as $province)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden" wire:key="prov-{{ $province->id }}">

                {{-- ردیف استان --}}
                <div class="flex items-center gap-3 px-4 py-3">
                    <button type="button" wire:click="toggleProvinceOpen({{ $province->id }})"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                        <svg class="w-5 h-5 transition-transform {{ $openProvince === $province->id ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    @if($editingType === 'province' && $editingId === $province->id)
                        @include('crm::livewire.partials.inline-edit')
                    @else
                        <button type="button" wire:click="toggleProvinceOpen({{ $province->id }})"
                                class="flex-1 text-right font-bold text-gray-900 dark:text-gray-100">
                            {{ $province->name }}
                        </button>
                        <span class="text-xs text-gray-400 shrink-0">{{ $province->cities_count }} شهر</span>
                        @include('crm::livewire.partials.toggle-pill', ['type' => 'province', 'row' => $province])
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" wire:click="startEdit('province', {{ $province->id }})" class="text-blue-600 hover:text-blue-800 text-xs">ویرایش</button>
                            <button type="button" wire:click="deleteProvince({{ $province->id }})" wire:confirm="حذف این استان؟" class="text-rose-600 hover:text-rose-800 text-xs">حذف</button>
                        </div>
                    @endif
                </div>

                {{-- شهرهای استان --}}
                @if($openProvince === $province->id)
                    <div class="border-t border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 px-3 py-3">
                        <form wire:submit="addCity" class="flex gap-2 mb-2">
                            <input type="text" wire:model="newCityName" placeholder="نام شهر جدید در {{ $province->name }}"
                                   class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                            <button type="submit" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold whitespace-nowrap">+ شهر</button>
                        </form>
                        @error('newCityName')<p class="text-xs text-rose-600 mb-2">{{ $message }}</p>@enderror

                        <div class="space-y-1.5">
                            @forelse($this->cities as $city)
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700" wire:key="city-{{ $city->id }}">
                                    <div class="flex items-center gap-3 px-3 py-2">
                                        <button type="button" wire:click="toggleCityOpen({{ $city->id }})"
                                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                                            <svg class="w-4 h-4 transition-transform {{ $openCity === $city->id ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>

                                        @if($editingType === 'city' && $editingId === $city->id)
                                            @include('crm::livewire.partials.inline-edit')
                                        @else
                                            <button type="button" wire:click="toggleCityOpen({{ $city->id }})"
                                                    class="flex-1 text-right text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {{ $city->name }}
                                            </button>
                                            <span class="text-[11px] text-gray-400 shrink-0">{{ $city->districts_count }} منطقه</span>
                                            @include('crm::livewire.partials.toggle-pill', ['type' => 'city', 'row' => $city])
                                            <div class="flex items-center gap-2 shrink-0">
                                                <button type="button" wire:click="startEdit('city', {{ $city->id }})" class="text-blue-600 hover:text-blue-800 text-xs">ویرایش</button>
                                                <button type="button" wire:click="deleteCity({{ $city->id }})" wire:confirm="حذف این شهر؟" class="text-rose-600 hover:text-rose-800 text-xs">حذف</button>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- مناطق شهر --}}
                                    @if($openCity === $city->id)
                                        <div class="border-t border-gray-100 dark:border-gray-700 px-3 py-2.5">
                                            <form wire:submit="addDistrict" class="flex gap-2 mb-2">
                                                <input type="text" wire:model="newDistrictName" placeholder="نام منطقهٔ جدید (مثلاً ۱، شمیران، …)"
                                                       class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold whitespace-nowrap">+ منطقه</button>
                                            </form>
                                            @error('newDistrictName')<p class="text-xs text-rose-600 mb-2">{{ $message }}</p>@enderror

                                            <div class="space-y-1">
                                                @forelse($this->districts as $district)
                                                    <div class="flex items-center gap-3 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-900/40" wire:key="dist-{{ $district->id }}">
                                                        @if($editingType === 'district' && $editingId === $district->id)
                                                            @include('crm::livewire.partials.inline-edit')
                                                        @else
                                                            <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $district->name }}</span>
                                                            @include('crm::livewire.partials.toggle-pill', ['type' => 'district', 'row' => $district])
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <button type="button" wire:click="startEdit('district', {{ $district->id }})" class="text-blue-600 hover:text-blue-800 text-xs">ویرایش</button>
                                                                <button type="button" wire:click="deleteDistrict({{ $district->id }})" wire:confirm="حذف این منطقه؟" class="text-rose-600 hover:text-rose-800 text-xs">حذف</button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 px-3 py-2">این شهر هنوز منطقه‌ای ندارد. می‌توانید با فرم بالا اضافه کنید.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 px-3 py-3">این استان هنوز شهری ندارد. با فرم بالا اضافه کنید.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-sm text-gray-500">
                {{ $search !== '' ? 'استانی با این نام پیدا نشد.' : 'هنوز استانی ثبت نشده است.' }}
            </div>
        @endforelse
    </div>
</div>
