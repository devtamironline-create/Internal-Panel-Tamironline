@php
    $steps = [
        1 => ['title' => 'محل سرویس و دستگاه‌ها', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
        2 => ['title' => 'مشتری و آدرس',          'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        3 => ['title' => 'بررسی و ثبت',          'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

<div class="max-w-4xl mx-auto p-4 md:p-6" wire:key="order-wizard-root">
    {{-- Step Indicator --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 md:p-6 mb-6">
        <div class="flex items-center justify-between">
            @foreach($steps as $num => $info)
                @php
                    $isDone = $num < $currentStep;
                    $isCurrent = $num === $currentStep;
                @endphp
                <button
                    type="button"
                    wire:click="goTo({{ $num }})"
                    @disabled($num > $currentStep)
                    class="flex flex-col items-center flex-1 min-w-0 group {{ $num > $currentStep ? 'opacity-60 cursor-not-allowed' : '' }}"
                >
                    <div class="relative w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center transition-all
                        {{ $isCurrent ? 'bg-brand-600 text-white shadow-lg ring-4 ring-brand-100 dark:ring-brand-900' : '' }}
                        {{ $isDone ? 'bg-green-500 text-white' : '' }}
                        {{ ! $isDone && ! $isCurrent ? 'bg-gray-200 dark:bg-gray-700 text-gray-500' : '' }}">
                        @if($isDone)
                            <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info['icon'] }}"/></svg>
                        @endif
                    </div>
                    <span class="mt-2 text-xs md:text-sm font-medium text-center
                        {{ $isCurrent ? 'text-brand-700 dark:text-brand-300' : '' }}
                        {{ $isDone ? 'text-green-700 dark:text-green-400' : '' }}
                        {{ ! $isDone && ! $isCurrent ? 'text-gray-500' : '' }}">
                        {{ $info['title'] }}
                    </span>
                </button>
                @if(!$loop->last)
                    <div class="flex-1 h-0.5 mx-1 md:mx-2 -mt-7 {{ $num < $currentStep ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Step Content --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 md:p-8 min-h-[24rem]">
        @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- wire:key روی هر مرحله حیاتی است — جلوگیری از نشتی DOM
             بین مراحل (مثل Tom Select wrapper استان/شهر که هنگام مرفینگ
             به مرحله بعد می‌ماند). --}}
        <div wire:key="wiz-step-{{ $currentStep }}">
            @if($currentStep === 1)
                @include('crm::livewire.wizard.step-1-location-devices')
            @elseif($currentStep === 2)
                @include('crm::livewire.wizard.step-1-customer')
            @elseif($currentStep === 3)
                @include('crm::livewire.wizard.step-5-review')
            @endif
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex items-center justify-between mt-6">
        <button
            type="button"
            wire:click="prev"
            @disabled($currentStep === 1)
            class="px-6 py-2.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
            ← مرحله قبل
        </button>

        @if($currentStep < 3)
            <button
                wire:key="wizard-nav-next"
                type="button"
                wire:click="next"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 rounded-lg bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-50 transition">
                مرحله بعد →
            </button>
        @else
            <button
                wire:key="wizard-nav-submit"
                type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                class="px-8 py-2.5 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 transition font-medium">
                <span wire:loading.remove wire:target="submit">ثبت سفارش ✓</span>
                <span wire:loading wire:target="submit">در حال ثبت…</span>
            </button>
        @endif
    </div>
</div>

{{-- هشدار قبل از خروج/رفرش — جلوگیری از از دست رفتن اطلاعات نیمه‌تکمیل
     سفارش. هنگام submit موفق، رویداد wizard-leaving-allowed ارسال می‌شود
     تا redirect نهایی بدون پرسش انجام شود. --}}
@script
<script>
(function () {
    if (window.__orderWizUnloadBound) return;
    window.__orderWizUnloadBound = true;

    let allowLeave = false;
    function handler(e) {
        if (allowLeave) return;
        e.preventDefault();
        // مرورگرهای مدرن متن سفارشی را نادیده می‌گیرند و پیام پیش‌فرض
        // خود را نشان می‌دهند، اما returnValue برای فعال شدن لازم است.
        e.returnValue = '';
        return '';
    }
    window.addEventListener('beforeunload', handler);

    Livewire.on('wizard-leaving-allowed', () => {
        allowLeave = true;
    });
})();

{{-- کارخانهٔ Alpine برای نقشهٔ «انتخاب منطقه روی نقشه» در مرحلهٔ مشتری.
     اینجا تعریف می‌شود (نه داخل partial مرحله) چون مرحلهٔ ۲ با morph
     می‌آید و <script> داخل HTMLِ morph شده اجرا نمی‌شود.
     SDK: همان Web SDK رسمیِ نشان (mapbox-gl) که اپِ مشتری هم استفاده
     می‌کند — docs/FRONTEND_LOCATIONS_NESHAN.md — و فقط بارِ اول که نقشه
     باز می‌شود لود می‌شود. ⚠ mapbox مختصات را [lng, lat] می‌گیرد. --}}
if (! window.wizardRegionMap) {
    window.wizardRegionMap = function (key, center) {
        return {
            open: false,
            map: null,
            marker: null,
            loadingMap: false,
            failedMap: false,

            toggleMap() {
                this.open = ! this.open;
                if (this.open && ! this.map && ! this.loadingMap) this.bootMap();
                // بعد از نمایشِ دوباره، mapbox باید اندازهٔ ظرف را از نو بخواند.
                if (this.open && this.map) this.$nextTick(() => this.map.resize());
            },

            bootMap() {
                this.loadingMap = true;
                this.failedMap = false;
                const ready = () => this.initMap();
                if (window.nmp_mapboxgl) return ready();
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.css';
                document.head.appendChild(css);
                const s = document.createElement('script');
                s.src = 'https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.js';
                s.onload = ready;
                s.onerror = () => { this.loadingMap = false; this.failedMap = true; };
                document.head.appendChild(s);
            },

            initMap() {
                // کمی صبر تا x-show ظرف را نمایان کرده باشد؛ وگرنه نقشه با
                // ارتفاع صفر ساخته می‌شود.
                setTimeout(() => {
                    this.loadingMap = false;
                    try {
                        this.map = new nmp_mapboxgl.Map({
                            mapType: nmp_mapboxgl.Map.mapTypes.neshanVector,
                            container: this.$refs.mapBox,
                            mapKey: key,
                            poi: true,
                            traffic: false,
                            center: center ? [center.lng, center.lat] : [53.7, 32.5],
                            zoom: center ? (center.zoom || 12) : 4.5,
                        });
                        this.map.on('click', (e) => this.pickPoint(e.lngLat.lat, e.lngLat.lng));
                        this.$nextTick(() => this.map.resize());
                    } catch (err) {
                        this.failedMap = true;
                    }
                }, 80);
            },

            pickPoint(lat, lng) {
                if (this.marker) {
                    this.marker.setLngLat([lng, lat]);
                } else {
                    this.marker = new nmp_mapboxgl.Marker({ draggable: true })
                        .setLngLat([lng, lat])
                        .addTo(this.map);
                    this.marker.on('dragend', () => {
                        const p = this.marker.getLngLat();
                        this.$wire.call('selectPointOnMap', p.lat, p.lng);
                    });
                }
                this.$wire.call('selectPointOnMap', lat, lng);
            },
        };
    };
}
</script>
@endscript
