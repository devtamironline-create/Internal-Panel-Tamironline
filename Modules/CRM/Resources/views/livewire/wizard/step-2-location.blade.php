<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <div wire:ignore>
                <select wire:model.live="provinceId" data-tom-select id="wizard-province-select"
                        data-cities-url="{{ url('/admin/crm/provinces') }}/__ID__/cities"
                        data-placeholder="— انتخاب کنید —">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($this->provinces as $p)
                        <option value="{{ $p->id }}" @selected($provinceId == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر / منطقه *</label>
            <div wire:ignore>
                <select wire:model="cityId" data-tom-select id="wizard-city-select"
                        data-placeholder="{{ $provinceId ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید' }}">
                    <option value="">— {{ $provinceId ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید' }} —</option>
                    @foreach($this->cities as $c)
                        <option value="{{ $c->id }}" @selected($cityId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">آدرس کامل *</label>
        <textarea wire:model="address" rows="3"
                  placeholder="خیابان، پلاک، واحد..."
                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
    </div>

    {{-- Cascade for the Tom-Select-wrapped pair: when province changes,
         fetch its cities via AJAX and rebuild city's options on the
         Tom Select instance directly. wire:ignore on the parents stops
         Livewire from morphing the selects (which would break Tom Select
         wrappers); wire:model.live still fires on the underlying <select>
         so server-side provinceId stays in sync for validation. --}}
    <script>
    (function () {
        function attachCascade() {
            var province = document.getElementById('wizard-province-select');
            var city = document.getElementById('wizard-city-select');
            if (!province || !city) return;
            if (province.dataset.cascadeInited === '1') return;
            province.dataset.cascadeInited = '1';

            province.addEventListener('change', async function () {
                var id = province.value;
                if (!id) {
                    if (city.tomselect) {
                        city.tomselect.clear();
                        city.tomselect.clearOptions();
                        city.tomselect.disable();
                        city.tomselect.settings.placeholder = 'ابتدا استان را انتخاب کنید';
                    }
                    return;
                }
                try {
                    var url = province.dataset.citiesUrl.replace('__ID__', id);
                    var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    var cities = await res.json();
                    if (city.tomselect) {
                        var prev = city.value;
                        city.tomselect.clear(true);
                        city.tomselect.clearOptions();
                        cities.forEach(function (c) {
                            city.tomselect.addOption({ value: String(c.id), text: c.name });
                        });
                        city.tomselect.refreshOptions(false);
                        city.tomselect.enable();
                        city.tomselect.settings.placeholder = 'انتخاب کنید';
                        // اگر شهر قبلی هنوز در لیست جدید بود، حفظش کن
                        if (prev && cities.some(function (c) { return String(c.id) === String(prev); })) {
                            city.tomselect.setValue(prev, true);
                        }
                    } else {
                        city.innerHTML = '<option value="">— انتخاب کنید —</option>' +
                            cities.map(function (c) { return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
                        city.disabled = false;
                    }
                } catch (e) {}
            });
        }
        attachCascade();
        setTimeout(attachCascade, 100);
        setTimeout(attachCascade, 400);
        document.addEventListener('livewire:navigated', attachCascade);
    })();
    </script>
</div>
