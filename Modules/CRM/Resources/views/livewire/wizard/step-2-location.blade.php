<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <div wire:ignore>
                <select id="wizard-province-select" data-tom-select
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
                <select id="wizard-city-select" data-tom-select
                        data-placeholder="— انتخاب کنید —">
                    <option value="">— ابتدا استان را انتخاب کنید —</option>
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

    <script>
    (function () {
        // Resolve a $wire instance for the current Livewire component.
        function getWire() {
            var root = document.querySelector('[wire\\:id]');
            if (!root || !window.Livewire) return null;
            try { return Livewire.find(root.getAttribute('wire:id')); } catch (e) { return null; }
        }

        async function loadCities(province, city) {
            var id = province.value;
            if (!id) {
                if (city.tomselect) {
                    city.tomselect.clear(true);
                    city.tomselect.clearOptions();
                    city.tomselect.disable();
                }
                return;
            }
            try {
                var url = province.dataset.citiesUrl.replace('__ID__', id);
                var res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) { console.warn('[cascade] fetch not ok', res.status); return; }
                var cities = await res.json();
                console.log('[cascade] loaded', cities.length, 'cities for province', id);
                if (city.tomselect) {
                    city.tomselect.clear(true);
                    city.tomselect.clearOptions();
                    cities.forEach(function (c) {
                        city.tomselect.addOption({ value: String(c.id), text: c.name });
                    });
                    city.tomselect.refreshOptions(false);
                    city.tomselect.enable();
                }
            } catch (e) {
                console.warn('[cascade] load failed', e);
            }
        }

        function attachCascade() {
            var province = document.getElementById('wizard-province-select');
            var city = document.getElementById('wizard-city-select');
            if (!province || !city) return false;
            if (!province.tomselect || !city.tomselect) return false;
            if (province.dataset.cascadeInited === '1') return true;
            province.dataset.cascadeInited = '1';
            console.log('[cascade] attached');

            // Initial state
            if (!province.value) city.tomselect.disable(); else city.tomselect.enable();

            // Province change: sync Livewire + fetch cities
            province.tomselect.on('change', function (val) {
                console.log('[cascade] province →', val);
                var w = getWire();
                if (w) try { w.set('provinceId', val, false); } catch (e) {}
                loadCities(province, city);
            });

            // City change: sync Livewire
            city.tomselect.on('change', function (val) {
                var w = getWire();
                if (w) try { w.set('cityId', val, false); } catch (e) {}
            });

            return true;
        }

        var tries = 0;
        var poll = setInterval(function () {
            if (attachCascade() || ++tries > 80) clearInterval(poll); // ~6.4s max
        }, 80);
        document.addEventListener('livewire:navigated', function () {
            tries = 0;
            poll = setInterval(function () {
                if (attachCascade() || ++tries > 80) clearInterval(poll);
            }, 80);
        });
    })();
    </script>
</div>
