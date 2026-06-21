@csrf
<div class="grid grid-cols-1 md:grid-cols-12 gap-4">
    <div class="md:col-span-7">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان ایراد <span class="text-rose-500">*</span></label>
        <input type="text" name="name" required maxlength="200" value="{{ old('name', $item->name ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-3">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">slug</label>
        <input type="text" name="slug" maxlength="100" value="{{ old('slug', $item->slug ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr"
               placeholder="auto از روی نام">
        @error('slug')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
    </div>

    <div class="md:col-span-4">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">آیکن</label>
        <input type="text" name="icon" maxlength="60" value="{{ old('icon', $item->icon ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr">
    </div>
    <div class="md:col-span-8">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
        <textarea name="description" rows="2" maxlength="1000"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ old('description', $item->description ?? '') }}</textarea>
    </div>

    <div class="md:col-span-12" x-data="objectionDevices()">
        @php $selected = old('device_ids', $selectedDeviceIds ?? []); @endphp
        <div class="flex items-center justify-between mb-1 gap-2 flex-wrap">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200">
                دستگاه‌های مرتبط <span class="text-gray-400" x-text="'(' + count + ' انتخاب‌شده)'"></span>
            </label>
            <div class="flex items-center gap-3 text-xs">
                <button type="button" @click="selectAll()" class="text-blue-600 hover:underline">انتخاب همه</button>
                <button type="button" @click="selectNone()" class="text-rose-600 hover:underline">حذف همه</button>
            </div>
        </div>
        <input type="text" x-model="q" placeholder="جستجوی دستگاه..."
               class="w-full px-3 py-2 mb-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-h-64 overflow-y-auto bg-gray-50 dark:bg-gray-900 grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($devices as $d)
                <label class="inline-flex items-center gap-2 text-sm" x-show="match(@js($d->name))">
                    <input type="checkbox" name="device_ids[]" value="{{ $d->id }}" @change="recount()" @checked(in_array($d->id, (array) $selected))>
                    <span>{{ $d->name }}@unless($d->is_active ?? true)<span class="text-[10px] text-gray-400"> (غیرفعال)</span>@endunless</span>
                </label>
            @endforeach
        </div>
        <p class="text-[11px] text-gray-500 mt-1">اگر هیچ دستگاهی تیک نخورد، این ایراد در picker اپ نمایش داده نمی‌شود.</p>
    </div>

    <div class="md:col-span-12">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            <span class="text-sm">فعال</span>
        </label>
    </div>
</div>

<div class="mt-4 flex items-center gap-2">
    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
    <a href="{{ route('crm.objections.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm">انصراف</a>
</div>

<script>
    function objectionDevices() {
        return {
            q: '',
            count: 0,
            init() { this.recount(); },
            match(name) { return ! this.q || (name || '').includes(this.q.trim()); },
            boxes() { return this.$root.querySelectorAll('input[name="device_ids[]"]'); },
            recount() { this.count = this.$root.querySelectorAll('input[name="device_ids[]"]:checked').length; },
            selectAll() { this.boxes().forEach((c) => { c.checked = true; }); this.recount(); },
            selectNone() { this.boxes().forEach((c) => { c.checked = false; }); this.recount(); },
        };
    }
</script>
