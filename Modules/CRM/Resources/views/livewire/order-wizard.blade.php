@php
    $steps = [
        1 => ['title' => 'مشتری',         'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        2 => ['title' => 'محل مراجعه',    'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
        3 => ['title' => 'دستگاه و ایراد', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
        4 => ['title' => 'تکنسین و زمان', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        5 => ['title' => 'مرور و ثبت',     'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
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
                @include('crm::livewire.wizard.step-1-customer')
            @elseif($currentStep === 2)
                @include('crm::livewire.wizard.step-2-location')
            @elseif($currentStep === 3)
                @include('crm::livewire.wizard.step-3-device')
            @elseif($currentStep === 4)
                @include('crm::livewire.wizard.step-4-technician')
            @elseif($currentStep === 5)
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

        @if($currentStep < 5)
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
