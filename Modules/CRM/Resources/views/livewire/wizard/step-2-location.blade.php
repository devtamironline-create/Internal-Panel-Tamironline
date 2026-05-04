<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <x-searchable-select
                name="provinceId"
                :options="$this->provinces->pluck('name', 'id')->toArray()"
                placeholder="— انتخاب کنید —"
                live="true" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر / منطقه *</label>
            <x-searchable-select
                name="cityId"
                :options="$this->cities->pluck('name', 'id')->toArray()"
                :placeholder="$provinceId ? '— انتخاب کنید —' : '— ابتدا استان را انتخاب کنید —'"
                :disabled="! $provinceId" />
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل *</label>
        <textarea wire:model="address" rows="3"
                  placeholder="خیابان، پلاک، واحد..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>
</div>
