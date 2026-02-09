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
                        <span class="text-sm text-blue-600">{{ $type->timer_label }}</span>
                    </div>
                    <button x-show="!editing" @click="editing = true" class="text-sm text-blue-600 hover:text-blue-800">ویرایش</button>

                    <form x-show="editing" class="flex items-center gap-2 flex-1" onsubmit="return updateShipping(event, {{ $type->id }})">
                        <input type="text" name="name" value="{{ $type->name }}" class="flex-1 px-3 py-1.5 border rounded text-sm">
                        <input type="number" name="timer_minutes" value="{{ $type->timer_minutes }}" class="w-24 px-3 py-1.5 border rounded text-sm" dir="ltr" placeholder="دقیقه">
                        <label class="flex items-center gap-1 text-sm">
                            <input type="checkbox" name="is_active" {{ $type->is_active ? 'checked' : '' }}> فعال
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

    <!-- WooCommerce Shipping Method Mapping -->
    <div class="bg-white rounded-xl shadow-sm p-6" x-data="wcShippingMapping()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">نقشه‌برداری ارسال ووکامرس</h2>
            <button @click="fetchMethods()" :disabled="fetching" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50">
                <span x-show="!fetching">دریافت از ووکامرس</span>
                <span x-show="fetching">در حال دریافت...</span>
            </button>
        </div>
        <p class="text-sm text-gray-500 mb-4">روش‌های ارسال ووکامرس را به انواع ارسال داخلی متصل کنید. هر روش ووکامرس به یک نوع ارسال داخلی (پست، پیک و ...) مپ می‌شود.</p>

        <!-- Fetch Status -->
        <div x-show="fetchMessage" x-cloak class="mb-4 p-3 rounded-lg text-sm"
             :class="fetchError ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
            <span x-text="fetchMessage"></span>
        </div>

        <!-- Mapping Table -->
        <div x-show="methods.length > 0" x-cloak class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-right font-medium text-gray-700">روش ووکامرس</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-700">منطقه</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-700">نوع ارسال داخلی</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(method, index) in methods" :key="index">
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span x-text="method.method_title" class="font-medium text-gray-900"></span>
                                    <span class="text-xs text-gray-400" x-text="method.method_id" dir="ltr"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500" x-text="method.zone_name"></td>
                            <td class="px-4 py-3">
                                <select x-model="mappings[method.method_id]" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                                    <option value="">-- انتخاب نوع ارسال --</option>
                                    @foreach($shippingTypes as $type)
                                    <option value="{{ $type->slug }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Saved Mappings Display -->
        @if(!empty($shippingMappings))
        <div x-show="methods.length === 0" class="mb-4">
            <h3 class="text-sm font-medium text-gray-700 mb-2">نقشه‌برداری فعلی:</h3>
            <div class="space-y-1">
                @foreach($shippingMappings as $wcMethod => $internalType)
                @if(!empty($internalType))
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                    <span class="text-gray-600" dir="ltr">{{ $wcMethod }}</span>
                    <span class="font-medium text-gray-900">→ {{ $internalType }}</span>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endif

        <button x-show="methods.length > 0" @click="saveMappings()" :disabled="saving" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm disabled:opacity-50">
            <span x-show="!saving">ذخیره نقشه‌برداری</span>
            <span x-show="saving">در حال ذخیره...</span>
        </button>
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
        }),
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
    return false;
}

function wcShippingMapping() {
    return {
        methods: [],
        mappings: @json($shippingMappings ?? []),
        fetching: false,
        saving: false,
        fetchMessage: '',
        fetchError: false,

        async fetchMethods() {
            this.fetching = true;
            this.fetchMessage = '';
            try {
                const res = await fetch('{{ route("warehouse.woocommerce.shipping-methods") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.success) {
                    this.methods = data.methods;
                    this.fetchMessage = data.methods.length + ' روش ارسال دریافت شد.';
                    this.fetchError = false;
                } else {
                    this.fetchMessage = data.message || 'خطا در دریافت';
                    this.fetchError = true;
                }
            } catch (e) {
                this.fetchMessage = 'خطا در ارتباط با سرور';
                this.fetchError = true;
            }
            this.fetching = false;
        },

        async saveMappings() {
            this.saving = true;
            try {
                const res = await fetch('{{ route("warehouse.woocommerce.shipping-mappings") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ mappings: this.mappings }),
                });
                const data = await res.json();
                this.fetchMessage = data.message;
                this.fetchError = !data.success;
            } catch (e) {
                this.fetchMessage = 'خطا در ذخیره';
                this.fetchError = true;
            }
            this.saving = false;
        }
    };
}
</script>
@endpush
@endsection
