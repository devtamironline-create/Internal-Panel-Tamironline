<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4" wire:ignore>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <select wire:model="provinceId" data-searchable id="wizard-province-select"
                    data-cities-url="{{ url('/admin/crm/provinces') }}/__ID__/cities"
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->provinces as $p)
                    <option value="{{ $p->id }}" @selected($provinceId == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر / منطقه *</label>
            <select wire:model="cityId" data-searchable id="wizard-city-select" @disabled(! $provinceId)
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent disabled:opacity-50">
                <option value="">— {{ $provinceId ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید' }} —</option>
                @foreach($this->cities as $c)
                    <option value="{{ $c->id }}" @selected($cityId == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل *</label>
        <textarea wire:model="address" rows="3"
                  placeholder="خیابان، پلاک، واحد..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>

    {{-- Cascade: province → fetch cities → repaint city <select> options.
         Inline (not @push) so it runs every time this step view renders,
         even when Livewire only morphs the step body. Idempotent via the
         cascadeInited dataset flag. --}}
    <script>
    (function () {
        function attachWizardCascade() {
            var province = document.getElementById('wizard-province-select');
            var city = document.getElementById('wizard-city-select');
            if (!province || !city) return;
            if (province.dataset.cascadeInited === '1') return;
            province.dataset.cascadeInited = '1';

            province.addEventListener('change', async function () {
                var id = province.value;
                if (!id) {
                    city.innerHTML = '<option value="">— ابتدا استان را انتخاب کنید —</option>';
                    city.disabled = true;
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
                city.disabled = false;
                try {
                    var url = province.dataset.citiesUrl.replace('__ID__', id);
                    var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    var cities = await res.json();
                    city.innerHTML = '<option value="">— انتخاب کنید —</option>' +
                        cities.map(function (c) { return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
                    city.value = '';
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    city.innerHTML = '<option value="">خطا در بارگذاری شهرها</option>';
                }
            });
        }
        // Try a few times — the searchable-select enhancer in the layout
        // wraps the <select> after init, but the element + ID survive.
        attachWizardCascade();
        setTimeout(attachWizardCascade, 50);
        setTimeout(attachWizardCascade, 250);
        document.addEventListener('livewire:navigated', attachWizardCascade);
    })();
    </script>
</div>
