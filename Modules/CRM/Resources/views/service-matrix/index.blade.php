@extends('layouts.admin')

@section('page-title', 'مدیریت نوع خدمت')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl" x-data="{ tab: 'devices' }">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🧰 مدیریت نوع خدمت</h1>
        <p class="text-xs text-gray-500 mt-1">
            تعیین کنید هر <b>دستگاه</b> و هر <b>تکنسین</b> چه نوع خدماتی (تعمیر / سرویس / نصب) ارائه می‌دهد.
            نوعی که دستگاه نداشته باشد یا هیچ تکنسینِ آن استان ارائه ندهد، به مشتری نمایش داده نمی‌شود.
        </p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- تب‌ها --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button @click="tab = 'devices'" :class="tab === 'devices' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500'"
                class="px-4 py-2 text-sm font-bold border-b-2 -mb-px">دستگاه‌ها ({{ number_format($devices->count()) }})</button>
        <button @click="tab = 'technicians'" :class="tab === 'technicians' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500'"
                class="px-4 py-2 text-sm font-bold border-b-2 -mb-px">تکنسین‌ها ({{ number_format($technicians->count()) }})</button>
    </div>

    {{-- ─── دستگاه‌ها ─── --}}
    <div x-show="tab === 'devices'">
        <form method="POST" action="{{ route('crm.service-matrix.devices') }}">
            @csrf
            @method('PUT')
            @foreach($devices as $d)
                <input type="hidden" name="device_ids[]" value="{{ $d->id }}">
            @endforeach
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">دستگاه</th>
                            @foreach($types as $slug => $name)
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">{{ $name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($devices as $d)
                            @php $sel = is_array($d->order_types) ? $d->order_types : []; @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-2">
                                    <span class="text-gray-800 dark:text-gray-200">{{ $d->name }}</span>
                                    @unless($d->is_active_app)
                                        <span class="text-[10px] text-gray-400 mr-1">(غیرفعال در اپ)</span>
                                    @endunless
                                </td>
                                @foreach($types as $slug => $name)
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" name="devices[{{ $d->id }}][]" value="{{ $slug }}"
                                               @checked(in_array($slug, $sel, true))
                                               class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($types) + 1 }}" class="px-4 py-8 text-center text-gray-500">دستگاهی یافت نشد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیرهٔ دستگاه‌ها</button>
            </div>
        </form>
    </div>

    {{-- ─── تکنسین‌ها ─── --}}
    <div x-show="tab === 'technicians'" x-cloak>
        <form method="POST" action="{{ route('crm.service-matrix.technicians') }}">
            @csrf
            @method('PUT')
            @foreach($technicians as $t)
                <input type="hidden" name="tech_ids[]" value="{{ $t->id }}">
            @endforeach
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">تکنسین</th>
                            @foreach($types as $slug => $name)
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">{{ $name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($technicians as $t)
                            @php $sel = is_array($t->service_types) ? $t->service_types : []; @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-2">
                                    <span class="text-gray-800 dark:text-gray-200">{{ $t->full_name }}</span>
                                    <span class="text-[10px] text-gray-400 mr-1" dir="ltr">{{ $t->mobile }}</span>
                                    @if($t->status !== 'active')
                                        <span class="text-[10px] text-rose-400 mr-1">(غیرفعال)</span>
                                    @endif
                                </td>
                                @foreach($types as $slug => $name)
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" name="technicians[{{ $t->id }}][]" value="{{ $slug }}"
                                               @checked(in_array($slug, $sel, true))
                                               class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($types) + 1 }}" class="px-4 py-8 text-center text-gray-500">تکنسینی یافت نشد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیرهٔ تکنسین‌ها</button>
            </div>
        </form>
    </div>

</div>
@endsection
