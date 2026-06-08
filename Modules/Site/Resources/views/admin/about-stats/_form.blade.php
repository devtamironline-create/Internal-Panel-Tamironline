@php
    $s = $stat ?? null;
    $tones = \Modules\Site\Models\AboutStat::TONES;
@endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
    <ul class="list-disc pr-4">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm mb-1">کلید (انگلیسی) <span class="text-red-500">*</span></label>
        <input type="text" name="key" value="{{ old('key', $s?->key) }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" required maxlength="60"
               placeholder="experience_years">
        <p class="text-xs text-gray-500 mt-1">شناسه پایدار — حروف کوچک، عدد و _</p>
    </div>

    <div>
        <label class="block text-sm mb-1">مقدار آمار <span class="text-red-500">*</span></label>
        <input type="text" name="value" value="{{ old('value', $s?->value) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="60"
               placeholder="۱۵+">
        <p class="text-xs text-gray-500 mt-1">عدد را فارسی فرمت کرده وارد کنید (مثلاً «۵۰,۰۰۰+»).</p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm mb-1">برچسب فارسی <span class="text-red-500">*</span></label>
        <input type="text" name="label" value="{{ old('label', $s?->label) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="120"
               placeholder="سال تجربه">
    </div>

    <div>
        <label class="block text-sm mb-1">تم رنگ <span class="text-red-500">*</span></label>
        <select name="tone" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required>
            @foreach($tones as $tone)
            <option value="{{ $tone }}" @selected(old('tone', $s?->tone ?? 'blue') === $tone)>{{ $tone }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $s?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $s?->is_published ?? true))>
            <span class="text-sm">منتشر شود</span>
        </label>
    </div>
</div>
