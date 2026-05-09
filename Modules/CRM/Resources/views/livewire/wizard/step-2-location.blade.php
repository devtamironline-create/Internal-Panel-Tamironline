<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل مراجعه</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرسی که تکنسین برای انجام تعمیر مراجعه می‌کند.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- هر دو select داخل wire:ignore هستند تا Tom Select بتواند DOM
             اطراف <select> را آزادانه دستکاری کند. مقداردهی Livewire با
             $wire.set('provinceId', ...) انجام می‌شود نه wire:model. --}}

        <div wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان *</label>
            <select id="orderwiz-province"
                    data-tom-select data-placeholder="جستجو در استان‌ها..."
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">— انتخاب کنید —</option>
                @foreach($this->provinces as $p)
                    <option value="{{ $p->id }}" @selected($provinceId == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر / منطقه *</label>
            <select id="orderwiz-city"
                    data-tom-select data-placeholder="جستجو در شهرها..."
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">{{ $provinceId ? '— انتخاب کنید —' : '— ابتدا استان را انتخاب کنید —' }}</option>
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

    {{-- یک‌بار JS اتچ می‌کنیم. wire:id فقط در runtime معلوم می‌شود، پس از
         document.querySelector به wire:id هر مرحله از wizard می‌رسیم. --}}
    <script>
    (function () {
        if (window.__orderWizLocationInit) return;
        window.__orderWizLocationInit = true;

        const CITIES_URL = "{{ url('/admin/crm/provinces') }}/__ID__/cities";

        const wireSet = function (key, value) {
            // پیدا کردن کامپوننت Livewire والد بر اساس wire:id
            const root = document.querySelector('[wire\\:id]');
            if (!root || !window.Livewire) return;
            const id = root.getAttribute('wire:id');
            const cmp = window.Livewire.find(id);
            if (cmp) cmp.set(key, value);
        };

        const setupCity = function (cityEl, cities, placeholder, hasProvince) {
            const ts = cityEl.tomselect;
            if (!ts) {
                cityEl.innerHTML = `<option value="">${placeholder}</option>`
                    + cities.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                return;
            }
            ts.clear(true);
            ts.clearOptions();
            ts.addOption([
                { value: '', text: placeholder },
                ...cities.map(c => ({ value: String(c.id), text: c.name })),
            ]);
            ts.refreshOptions(false);
            if (hasProvince) ts.enable(); else ts.disable();
        };

        const tryAttach = function () {
            const provinceEl = document.getElementById('orderwiz-province');
            const cityEl = document.getElementById('orderwiz-city');
            if (!provinceEl || !cityEl) return false;
            // صبر تا Tom Select روی هر دو init شود
            if (!provinceEl.tomselect || !cityEl.tomselect) return false;
            if (provinceEl.dataset.wizBound === '1') return true;
            provinceEl.dataset.wizBound = '1';

            // بسته شدن اولیه شهر اگر استانی انتخاب نشده
            if (!provinceEl.value) cityEl.tomselect.disable();

            // تغییر استان: مقدار را در Livewire ست و شهرها را fetch کن
            provinceEl.tomselect.on('change', async function (value) {
                wireSet('provinceId', value === '' ? null : Number(value));
                // پاک کردن شهر
                wireSet('cityId', null);
                cityEl.tomselect.clear(true);

                if (!value) {
                    setupCity(cityEl, [], '— ابتدا استان را انتخاب کنید —', false);
                    return;
                }
                cityEl.tomselect.clearOptions();
                cityEl.tomselect.addOption([{ value: '', text: 'در حال بارگذاری...' }]);
                cityEl.tomselect.refreshOptions(false);

                try {
                    const url = CITIES_URL.replace('__ID__', value);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    setupCity(cityEl, data, '— انتخاب کنید —', true);
                } catch (e) {
                    setupCity(cityEl, [], 'خطا در بارگذاری شهرها', false);
                }
            });

            // تغییر شهر: مقدار را در Livewire ست
            cityEl.tomselect.on('change', function (value) {
                wireSet('cityId', value === '' ? null : Number(value));
            });

            return true;
        };

        // polling تا Tom Select هر دو select را init کند، بعد bind کن.
        const poll = setInterval(function () {
            if (tryAttach()) clearInterval(poll);
        }, 80);
        // safety stop بعد از ۱۰ ثانیه
        setTimeout(function () { clearInterval(poll); }, 10000);
    })();
    </script>
</div>
