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
</script>
@endpush
@endsection
