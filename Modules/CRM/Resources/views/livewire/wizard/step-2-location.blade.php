<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">استان و شهر</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">آدرس دقیق در مرحلهٔ بعد همراه با اطلاعات مشتری وارد می‌شود.</p>

    {{-- flex با w-1/2 به‌جای grid برای جلوگیری از فشرده شدن .ts-wrapper
         که display:inline-block است و گاهی grid item را به یک ردیف
         پایین می‌اندازد. --}}
    <div class="flex flex-row gap-4" style="display:flex;flex-direction:row;">
        <div wire:ignore class="w-1/2" style="width:50%;flex:1 1 50%;min-width:0;">
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

        <div wire:ignore class="w-1/2" style="width:50%;flex:1 1 50%;min-width:0;">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر *</label>
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

    {{-- آدرس و منطقه به مرحلهٔ بعد (مشتری) منتقل شده‌اند: آدرس برای
         اینکه با انتخاب مشتری قدیمی از سفارش قبلی پر شود، و منطقه
         چون فقط برای سفارش‌های قابل ثبت معنا دارد (در حالت لید نیازی
         نیست) — اینجا فقط استان و شهر بررسی می‌شود. --}}

    {{-- بلاک script-endscript پایین یک دایرکتیو Livewire 3 است. تگ
         script ساده در ویوهای کامپوننت توسط Livewire حذف می‌شوند،
         اما این بلاک تضمین اجرا دارد. هیچ نشانهٔ @ در این کامنت
         نگذارید — Blade دایرکتیوها را حتی داخل کامنت هم پارس می‌کند
         که باعث «JSON.parse unexpected character» در update می‌شود. --}}
    @script
    <script>
    (function () {
        if (window.__orderWizLocationInit) return;
        window.__orderWizLocationInit = true;

        const CITIES_URL = "{{ url('/admin/crm/provinces') }}/__ID__/cities";

        const wireSet = function (key, value) {
            // داخل script directive، $wire به کامپوننت Livewire این view اشاره می‌کند.
            try { $wire.set(key, value); } catch (e) {}
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
                // پاک کردن شهر و منطقه (در مرحلهٔ بعد دوباره بارگذاری می‌شود)
                wireSet('cityId', null);
                wireSet('regionId', null);
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

            // تغییر شهر: مقدار را در Livewire ست + پاک‌سازی منطقه (انتخابش
            // در مرحلهٔ بعد، فقط برای سفارش‌های قابل ثبت، انجام می‌شود).
            cityEl.tomselect.on('change', function (value) {
                wireSet('cityId', value === '' ? null : Number(value));
                wireSet('regionId', null);
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
    @endscript
</div>
