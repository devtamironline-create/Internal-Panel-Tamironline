@php($m = $m ?? null)
<div>
    <label class="block text-xs text-gray-500 mb-1">نامِ نمایشی <span class="text-rose-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $m?->name) }}" required maxlength="150"
           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" placeholder="مثلاً: GPT-5 Mini (آروان)">
</div>
<div>
    <label class="block text-xs text-gray-500 mb-1">نامِ مدل در API (model) <span class="text-rose-500">*</span></label>
    <input type="text" name="model_name" value="{{ old('model_name', $m?->model_name) }}" required maxlength="150" dir="ltr"
           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr" placeholder="GPT-5-Mini">
</div>
<div class="md:col-span-2">
    <label class="block text-xs text-gray-500 mb-1">Endpoint (تا <code dir="ltr">/v1</code>) <span class="text-rose-500">*</span></label>
    <input type="url" name="endpoint" value="{{ old('endpoint', $m?->endpoint) }}" required maxlength="500" dir="ltr"
           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr" placeholder="https://arvancloudai.ir/gateway/models/.../v1">
    <p class="text-[11px] text-gray-400 mt-1">مسیرِ <code dir="ltr">/chat/completions</code> خودکار اضافه می‌شود.</p>
</div>
<div>
    <label class="block text-xs text-gray-500 mb-1">کلیدِ API (apikey) @if(!$m)<span class="text-rose-500">*</span>@endif</label>
    <input type="password" name="api_key" autocomplete="new-password" maxlength="2000" dir="ltr"
           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr"
           placeholder="{{ $m ? 'برای تغییر وارد کنید (خالی = بدون تغییر)' : 'apikey' }}">
    <p class="text-[11px] text-gray-400 mt-1">رمزنگاری‌شده ذخیره می‌شود و دیگر نمایش داده نمی‌شود.</p>
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Max tokens</label>
        <input type="number" name="max_tokens" value="{{ old('max_tokens', $m?->max_tokens ?? 1000) }}" min="1" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Temperature</label>
        <input type="number" step="0.1" min="0" max="2" name="temperature" value="{{ old('temperature', $m?->temperature ?? 0.2) }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
    </div>
</div>
<div class="md:col-span-2 flex items-center gap-5">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $m?->is_active ?? true)) class="w-4 h-4 rounded"> فعال
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $m?->is_default ?? false)) class="w-4 h-4 rounded"> پیش‌فرض
    </label>
</div>
