@extends('layouts.admin')
@section('page-title', 'تنظیمات انبار')
@section('main')
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">تنظیمات انبار</h1>
            <p class="text-gray-600 mt-1">تنظیمات تایمر، وزن و نوع ارسال</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Invoice Settings -->
        <div class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2">
            <h2 class="text-lg font-bold text-gray-900 mb-4">تنظیمات فاکتور</h2>
            <form action="{{ route('warehouse.settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="weight_tolerance" value="{{ $weightTolerance }}">
                <input type="hidden" name="alert_mobile" value="{{ $alertMobile }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام فروشگاه (روی فاکتور)</label>
                        <input type="text" name="invoice_store_name" value="{{ $invoiceSettings['invoice_store_name'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                               placeholder="مثلا: گنجه">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">زیرعنوان فاکتور</label>
                        <input type="text" name="invoice_subtitle" value="{{ $invoiceSettings['invoice_subtitle'] ?? '' }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                               placeholder="مثلا: فاکتور سفارش انبار">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">لوگوی فاکتور</label>
                        @if(!empty($invoiceSettings['invoice_logo']))
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ asset('storage/' . $invoiceSettings['invoice_logo']) }}" alt="Logo" class="w-16 h-16 object-contain border rounded-lg">
                                <a href="{{ route('warehouse.settings.delete-invoice-logo') }}" class="text-sm text-red-600 hover:text-red-800">حذف لوگو</a>
                            </div>
                        @endif
                        <input type="file" name="invoice_logo" accept="image/*"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm file:ml-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تلفن فرستنده</label>
                        <input type="text" name="invoice_sender_phone" value="{{ $invoiceSettings['invoice_sender_phone'] ?? '' }}" dir="ltr"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                               placeholder="021-12345678">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">آدرس فرستنده</label>
                        <textarea name="invoice_sender_address" rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                  placeholder="تهران، خیابان ...">{{ $invoiceSettings['invoice_sender_address'] ?? '' }}</textarea>
                    </div>
                </div>
                <button type="submit" class="mt-4 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">ذخیره تنظیمات فاکتور</button>
            </form>
        </div>

        <!-- Weight Tolerance -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">کنترل وزن</h2>
            <form action="{{ route('warehouse.settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">درصد اختلاف مجاز وزن</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="weight_tolerance" value="{{ $weightTolerance }}" min="0" max="100" step="0.5"
                               class="w-32 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" dir="ltr">
                        <span class="text-gray-500">%</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">اگر اختلاف وزن واقعی با وزن مورد انتظار بیشتر از این مقدار باشد، هشدار نمایش داده میشود.</p>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">ذخیره</button>
            </form>
        </div>

        <!-- Shipping Types -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">انواع ارسال و تایمر</h2>
            <div class="space-y-3">
                @foreach($shippingTypes as $type)
                <div class="flex items-center justify-between p-3 border rounded-lg" x-data="{ editing: false }">
                    <div x-show="!editing" class="flex items-center gap-3">
                        <span class="font-medium">{{ $type->name }}</span>
                        <span class="text-sm text-gray-500">({{ $type->slug }})</span>
                        <span class="px-2 py-0.5 rounded text-xs {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $type->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                        @if($type->requires_dispatch)
                        <span class="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">ایستگاه پیک</span>
                        @endif
                        <span class="text-sm text-blue-600">{{ $type->timer_label }}</span>
                    </div>
                    <button x-show="!editing" @click="editing = true" class="text-sm text-blue-600 hover:text-blue-800">ویرایش</button>

                    <form x-show="editing" class="flex items-center gap-2 flex-1" onsubmit="return updateShipping(event, {{ $type->id }})">
                        <input type="text" name="name" value="{{ $type->name }}" class="flex-1 px-3 py-1.5 border rounded text-sm">
                        <input type="number" name="timer_minutes" value="{{ $type->timer_minutes }}" class="w-24 px-3 py-1.5 border rounded text-sm" dir="ltr" placeholder="دقیقه">
                        <label class="flex items-center gap-1 text-sm">
                            <input type="checkbox" name="is_active" {{ $type->is_active ? 'checked' : '' }}> فعال
                        </label>
                        <label class="flex items-center gap-1 text-sm text-purple-700">
                            <input type="checkbox" name="requires_dispatch" {{ $type->requires_dispatch ? 'checked' : '' }}> ایستگاه پیک
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm">ذخیره</button>
                        <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-sm">لغو</button>
                    </form>
                </div>
                @endforeach
            </div>

            <!-- Add New -->
            <div class="mt-4 pt-4 border-t">
                <h3 class="text-sm font-medium text-gray-700 mb-2">افزودن نوع ارسال جدید</h3>
                <form action="{{ route('warehouse.settings.shipping-type.store') }}" method="POST" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">نام</label>
                        <input type="text" name="name" required class="px-3 py-2 border rounded-lg text-sm" placeholder="مثلا: باربری">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">شناسه</label>
                        <input type="text" name="slug" required dir="ltr" class="px-3 py-2 border rounded-lg text-sm" placeholder="cargo">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">تایمر (دقیقه)</label>
                        <input type="number" name="timer_minutes" required dir="ltr" class="w-24 px-3 py-2 border rounded-lg text-sm" value="60">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">افزودن</button>
                </form>
            </div>
        </div>
    </div>

        <!-- Alert Mobile -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">هشدار پیامکی</h2>
            <form action="{{ route('warehouse.settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="weight_tolerance" value="{{ $weightTolerance }}">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">موبایل مدیر برای هشدارها</label>
                    <input type="text" name="alert_mobile" value="{{ $alertMobile }}" dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                           placeholder="09123456789">
                    <p class="text-xs text-gray-500 mt-1">وقتی فاکتوری بیش از یکبار پرینت بشه، پیامک هشدار به این شماره ارسال میشه.</p>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">ذخیره</button>
            </form>
        </div>
    </div>

    <!-- Box Sizes Management -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">سایز کارتن‌ها</h2>
                <p class="text-sm text-gray-500 mt-1">تعریف سایزهای مختلف کارتن برای بسته‌بندی سفارشات. سیستم بر اساس ابعاد محصولات، کارتن مناسب را پیشنهاد می‌دهد.</p>
            </div>
        </div>

        <!-- Box Sizes Table -->
        <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">سایز</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">ابعاد (cm)</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">حجم (cm³)</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">وزن (g)</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">وضعیت</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($boxSizes as $box)
                    <tr x-data="{ editing: false }">
                        <template x-if="!editing">
                            <td class="px-4 py-3 font-bold text-gray-900">{{ $box->name }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $box->dimensions_label }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ number_format($box->volume) }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600">{{ $box->weight_label }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs {{ $box->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $box->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="editing = true" class="text-sm text-blue-600 hover:text-blue-800">ویرایش</button>
                                    <form action="{{ route('warehouse.settings.box-size.delete', $box) }}" method="POST" class="inline" onsubmit="return confirm('حذف سایز {{ $box->name }}؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </template>

                        <!-- Edit Mode -->
                        <template x-if="editing">
                            <td colspan="6" class="px-4 py-3">
                                <form onsubmit="return updateBoxSize(event, {{ $box->id }})" class="flex items-center gap-2 flex-wrap">
                                    <input type="text" name="name" value="{{ $box->name }}" class="w-16 px-2 py-1.5 border rounded text-sm text-center" placeholder="سایز">
                                    <input type="number" name="length" value="{{ $box->length }}" step="0.1" class="w-16 px-2 py-1.5 border rounded text-sm" dir="ltr" placeholder="طول">
                                    <span class="text-gray-400">×</span>
                                    <input type="number" name="width" value="{{ $box->width }}" step="0.1" class="w-16 px-2 py-1.5 border rounded text-sm" dir="ltr" placeholder="عرض">
                                    <span class="text-gray-400">×</span>
                                    <input type="number" name="height" value="{{ $box->height }}" step="0.1" class="w-16 px-2 py-1.5 border rounded text-sm" dir="ltr" placeholder="ارتفاع">
                                    <span class="text-xs text-gray-400">cm</span>
                                    <input type="number" name="weight" value="{{ $box->weight }}" class="w-20 px-2 py-1.5 border rounded text-sm" dir="ltr" placeholder="وزن">
                                    <span class="text-xs text-gray-400">g</span>
                                    <label class="flex items-center gap-1 text-sm">
                                        <input type="checkbox" name="is_active" {{ $box->is_active ? 'checked' : '' }}> فعال
                                    </label>
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm">ذخیره</button>
                                    <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-sm">لغو</button>
                                </form>
                            </td>
                        </template>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Add New Box Size -->
        <div class="pt-4 border-t">
            <h3 class="text-sm font-medium text-gray-700 mb-2">افزودن سایز جدید</h3>
            <form action="{{ route('warehouse.settings.box-size.store') }}" method="POST" class="flex items-end gap-2 flex-wrap">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">سایز</label>
                    <input type="text" name="name" required class="w-20 px-3 py-2 border rounded-lg text-sm text-center" placeholder="10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">طول (cm)</label>
                    <input type="number" name="length" required step="0.1" dir="ltr" class="w-20 px-3 py-2 border rounded-lg text-sm" placeholder="30">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">عرض (cm)</label>
                    <input type="number" name="width" required step="0.1" dir="ltr" class="w-20 px-3 py-2 border rounded-lg text-sm" placeholder="20">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">ارتفاع (cm)</label>
                    <input type="number" name="height" required step="0.1" dir="ltr" class="w-20 px-3 py-2 border rounded-lg text-sm" placeholder="15">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">وزن (g)</label>
                    <input type="number" name="weight" required dir="ltr" class="w-20 px-3 py-2 border rounded-lg text-sm" placeholder="200">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">افزودن</button>
            </form>
        </div>
    </div>

    <!-- Working Hours (ساعات کاری) -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">ساعات کاری</h2>
                <p class="text-sm text-gray-500 mt-1">تایمر سفارشات فقط در ساعات کاری محاسبه می‌شود. خارج از ساعت کاری تایمر متوقف می‌شود.</p>
            </div>
        </div>

        <form action="{{ route('warehouse.settings.working-hours.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="working_hours_enabled" value="0">
                    <input type="checkbox" name="working_hours_enabled" value="1" {{ $workingHoursEnabled ? 'checked' : '' }}
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-bold text-gray-800">فعال‌سازی ساعات کاری</span>
                        <span class="block text-xs text-gray-500">اگر غیرفعال باشد، تایمر ۲۴ ساعته محاسبه می‌شود</span>
                    </div>
                </label>
            </div>

            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 w-12">فعال</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">روز</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">شروع</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">پایان</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">ساعت کاری</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($dayLabels as $dayKey => $dayLabel)
                        @php
                            $dayData = $workingHours[$dayKey] ?? ['active' => false, 'start' => '09:00', 'end' => '17:00'];
                        @endphp
                        <tr class="{{ $dayData['active'] ? '' : 'bg-gray-50 opacity-60' }}" x-data="{ active: {{ $dayData['active'] ? 'true' : 'false' }}, start: '{{ $dayData['start'] }}', end: '{{ $dayData['end'] }}' }">
                            <td class="px-4 py-3 text-center">
                                <input type="hidden" name="days[{{ $dayKey }}][active]" :value="active ? '1' : ''">
                                <input type="checkbox" x-model="active"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-800">{{ $dayLabel }}</td>
                            <td class="px-4 py-3">
                                <input type="time" name="days[{{ $dayKey }}][start]" x-model="start" :disabled="!active"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-400" dir="ltr">
                            </td>
                            <td class="px-4 py-3">
                                <input type="time" name="days[{{ $dayKey }}][end]" x-model="end" :disabled="!active"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-400" dir="ltr">
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                <template x-if="active">
                                    <span x-text="(() => {
                                        let [sh, sm] = start.split(':').map(Number);
                                        let [eh, em] = end.split(':').map(Number);
                                        let diff = (eh * 60 + em) - (sh * 60 + sm);
                                        if (diff <= 0) return '—';
                                        let h = Math.floor(diff / 60);
                                        let m = diff % 60;
                                        return (h > 0 ? h + ' ساعت' : '') + (m > 0 ? ' و ' + m + ' دقیقه' : '');
                                    })()"></span>
                                </template>
                                <template x-if="!active">
                                    <span class="text-red-400">تعطیل</span>
                                </template>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">ذخیره ساعات کاری</button>
                <p class="text-xs text-gray-400">تغییرات فقط روی سفارشات جدید اعمال می‌شود.</p>
            </div>
        </form>
    </div>

    <!-- Shipping Rules (قوانین override ارسال) -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-900">قوانین تغییر نوع ارسال</h2>
            <p class="text-sm text-gray-500 mt-1">با این قوانین می‌توانید بر اساس استان، شهر و نوع ارسال اولیه، نوع ارسال را به صورت خودکار تغییر دهید. قوانین با اولویت بالاتر زودتر اجرا می‌شوند.</p>
        </div>

        <!-- Rules Table -->
        @if($shippingRules->count() > 0)
        <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">نام قانون</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">استان</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">شهر</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">از نوع ارسال</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">به نوع ارسال</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">اولویت</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">وضعیت</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($shippingRules as $rule)
                    <tr x-data="{ editing: false }">
                        <template x-if="!editing">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $rule->name }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600">{{ $rule->province === '*' ? 'همه' : $rule->province }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600">{{ $rule->city === '*' ? 'همه' : $rule->city }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600">{{ $rule->from_shipping_type === '*' ? 'همه' : $rule->from_shipping_type }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 font-medium text-blue-700">{{ $rule->to_shipping_type }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $rule->priority }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs {{ $rule->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $rule->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                        </template>
                        <template x-if="!editing">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="editing = true" class="text-sm text-blue-600 hover:text-blue-800">ویرایش</button>
                                    <form action="{{ route('warehouse.settings.shipping-rule.delete', $rule) }}" method="POST" class="inline" onsubmit="return confirm('حذف قانون {{ $rule->name }}؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </template>

                        <!-- Edit Mode -->
                        <template x-if="editing">
                            <td colspan="8" class="px-4 py-3">
                                <form onsubmit="return updateShippingRule(event, {{ $rule->id }})" class="flex items-center gap-2 flex-wrap">
                                    <input type="text" name="name" value="{{ $rule->name }}" class="w-36 px-2 py-1.5 border rounded text-sm" placeholder="نام">
                                    <input type="text" name="province" value="{{ $rule->province }}" class="w-20 px-2 py-1.5 border rounded text-sm" placeholder="استان">
                                    <input type="text" name="city" value="{{ $rule->city }}" class="w-20 px-2 py-1.5 border rounded text-sm" placeholder="شهر">
                                    <select name="from_shipping_type" class="w-28 px-2 py-1.5 border rounded text-sm">
                                        <option value="*" {{ $rule->from_shipping_type === '*' ? 'selected' : '' }}>همه</option>
                                        @foreach($shippingTypes as $type)
                                        <option value="{{ $type->slug }}" {{ $rule->from_shipping_type === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-gray-400">→</span>
                                    <select name="to_shipping_type" class="w-28 px-2 py-1.5 border rounded text-sm">
                                        @foreach($shippingTypes as $type)
                                        <option value="{{ $type->slug }}" {{ $rule->to_shipping_type === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="priority" value="{{ $rule->priority }}" class="w-16 px-2 py-1.5 border rounded text-sm" dir="ltr">
                                    <label class="flex items-center gap-1 text-sm">
                                        <input type="checkbox" name="is_active" {{ $rule->is_active ? 'checked' : '' }}> فعال
                                    </label>
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm">ذخیره</button>
                                    <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-sm">لغو</button>
                                </form>
                            </td>
                        </template>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-6 text-gray-400 text-sm mb-4">هنوز قانونی تعریف نشده است.</div>
        @endif

        <!-- Add New Rule -->
        <div class="pt-4 border-t">
            <h3 class="text-sm font-medium text-gray-700 mb-2">افزودن قانون جدید</h3>
            <form action="{{ route('warehouse.settings.shipping-rule.store') }}" method="POST" class="flex items-end gap-2 flex-wrap">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">نام قانون</label>
                    <input type="text" name="name" required class="w-40 px-3 py-2 border rounded-lg text-sm" placeholder="مثلا: تهران پست به پیک">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">استان (* = همه)</label>
                    <input type="text" name="province" value="*" required class="w-24 px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">شهر (* = همه)</label>
                    <input type="text" name="city" value="*" required class="w-24 px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">از نوع ارسال</label>
                    <select name="from_shipping_type" class="px-3 py-2 border rounded-lg text-sm">
                        <option value="*">همه</option>
                        @foreach($shippingTypes as $type)
                        <option value="{{ $type->slug }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">به نوع ارسال</label>
                    <select name="to_shipping_type" required class="px-3 py-2 border rounded-lg text-sm">
                        @foreach($shippingTypes as $type)
                        <option value="{{ $type->slug }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">اولویت</label>
                    <input type="number" name="priority" value="0" required dir="ltr" class="w-16 px-3 py-2 border rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">افزودن</button>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
function updateShipping(e, id) {
    e.preventDefault();
    const form = e.target;
    fetch('/warehouse/settings/shipping-type/' + id, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            name: form.name.value,
            timer_minutes: parseInt(form.timer_minutes.value),
            is_active: form.is_active.checked,
            requires_dispatch: form.requires_dispatch.checked,
        }),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
    return false;
}

function updateBoxSize(e, id) {
    e.preventDefault();
    const form = e.target;
    fetch('/warehouse/settings/box-size/' + id, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            name: form.name.value,
            length: parseFloat(form.length.value),
            width: parseFloat(form.width.value),
            height: parseFloat(form.height.value),
            weight: parseInt(form.weight.value),
            is_active: form.is_active.checked,
        }),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
    return false;
}

function updateShippingRule(e, id) {
    e.preventDefault();
    const form = e.target;
    fetch('/warehouse/settings/shipping-rule/' + id, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            name: form.name.value,
            province: form.province.value,
            city: form.city.value,
            from_shipping_type: form.from_shipping_type.value,
            to_shipping_type: form.to_shipping_type.value,
            priority: parseInt(form.priority.value),
            is_active: form.is_active.checked,
        }),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
    return false;
}

</script>
@endpush
@endsection
