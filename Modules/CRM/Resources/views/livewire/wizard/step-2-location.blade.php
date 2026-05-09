<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- هر دو select داخل wire:ignore هستند تا Livewire DOM آنها را
             بعد از morph دستکاری نکند (با Tom Select تداخل می‌کرد).
             province ثابت است و فقط مقدارش را Livewire می‌خواند. cities
             از طریق event به JS push می‌شود (نگاه کنید به updatedProvinceId
             در OrderWizard.php). --}}

        <div wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <select wire:model.live="provinceId"
                    id="orderwiz-province"
                    data-tom-select data-placeholder="جستجو در استان‌ها..."
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->provinces as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر / منطقه *</label>
            <select wire:model.live="cityId"
                    id="orderwiz-city"
                    data-tom-select data-placeholder="جستجو در شهرها..."
                    @disabled(! $provinceId)
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="">
                    {{ $provinceId ? '— انتخاب کنید —' : '— ابتدا استان را انتخاب کنید —' }}
                </option>
                @foreach($this->cities as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <script>
    // فقط یک‌بار listener را روی Livewire ثبت می‌کنیم — guard جلوی
    // ثبت تکراری در morphهای بعدی wizard را می‌گیرد. این inline است
    // (نه @push) چون Livewire components نمی‌توانند به stack layout بعد
    // از render اولیه push کنند.
    (function () {
        if (window.__orderWizCityHookInstalled) return;
        window.__orderWizCityHookInstalled = true;

        const attach = function () {
            if (typeof Livewire === 'undefined') return false;
            Livewire.on('order-wizard:cities-loaded', function (payload) {
                const data = (payload && payload[0] && payload[0].cities) || [];
                const sel = document.getElementById('orderwiz-city');
                if (! sel) return;

                const placeholder = data.length ? '— انتخاب کنید —' : '— ابتدا استان را انتخاب کنید —';
                if (sel.tomselect) {
                    sel.tomselect.clear(true);
                    sel.tomselect.clearOptions();
                    sel.tomselect.addOption([
                        { value: '', text: placeholder },
                        ...data.map(c => ({ value: String(c.id), text: c.name })),
                    ]);
                    sel.tomselect.refreshOptions(false);
                    if (data.length === 0) {
                        sel.tomselect.disable();
                    } else {
                        sel.tomselect.enable();
                    }
                } else {
                    // fallback اگر Tom Select هنوز init نشده
                    sel.innerHTML = `<option value="">${placeholder}</option>`
                        + data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                    sel.disabled = data.length === 0;
                }
            });
            return true;
        };

        if (! attach()) {
            document.addEventListener('livewire:init', attach);
        }
    })();
    </script>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل *</label>
        <textarea wire:model="address" rows="3"
                  placeholder="خیابان، پلاک، واحد..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>
</div>
