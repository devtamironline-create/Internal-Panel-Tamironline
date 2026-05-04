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

    {{-- Cascade hooked directly into Tom Select's own event system so it
         fires regardless of native dispatch behavior. Retries until both
         instances are mounted, then attaches once. --}}
    <script>
    (function () {
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
                if (!res.ok) return;
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
                    if (prev && cities.some(function (c) { return String(c.id) === String(prev); })) {
                        city.tomselect.setValue(prev, true);
                    }
                }
            } catch (e) {
                console.warn('cities cascade failed', e);
            }
        }

        function attachCascade() {
            var province = document.getElementById('wizard-province-select');
            var city = document.getElementById('wizard-city-select');
            if (!province || !city) return false;

            // Wait until BOTH Tom Select instances are mounted
            if (!province.tomselect || !city.tomselect) return false;

            if (province.dataset.cascadeInited === '1') return true;
            province.dataset.cascadeInited = '1';

            // Initial state: disable city if no province
            if (!province.value) city.tomselect.disable();
            else city.tomselect.enable();

            // Hook into Tom Select's own change event
            province.tomselect.on('change', function () {
                loadCities(province, city);
            });

            return true;
        }

        // Poll every 80ms until both Tom Selects are ready, then attach once.
        var tries = 0;
        var poll = setInterval(function () {
            if (attachCascade() || ++tries > 60) { // ~5s max
                clearInterval(poll);
            }
        }, 80);
        document.addEventListener('livewire:navigated', function () {
            tries = 0;
            poll = setInterval(function () {
                if (attachCascade() || ++tries > 60) clearInterval(poll);
            }, 80);
        });
    })();
    </script>
</div>
