<?php

namespace Modules\CRM\Livewire;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;

/**
 * مدیریت یکپارچهٔ مناطق جغرافیایی و محدودهٔ سرویس‌دهی اپ.
 *
 * یک درختِ تعاملیِ سه‌سطحی استان → شهر → منطقه در یک صفحه؛ جایگزین
 * صفحات پراکندهٔ استان‌ها/شهرها/مناطق تا ادمین همه‌چیز را یک‌جا ببیند.
 *
 * منبع واحد: نمایش هر گره در اپ موبایل فقط با تاگل is_active
 * («نمایش در اپ») کنترل می‌شود و LocationController اپ نیز همین را
 * می‌خواند. این کامپوننت روی crm_cities کار می‌کند — شهرها = ردیف‌های
 * ریشه (parent_city_id = null) و مناطق = ردیف‌های فرزند — یعنی دقیقاً
 * همان داده‌ای که اپ و آدرس مشتری استفاده می‌کنند.
 */
class ServiceAreaManager extends Component
{
    public string $search = '';

    /** استان/شهرِ بازِ فعلی در درخت. */
    public ?int $openProvince = null;

    public ?int $openCity = null;

    /** فرم‌های افزودن inline. */
    public string $newProvinceName = '';

    public string $newCityName = '';

    public string $newDistrictName = '';

    /** ویرایش inline یک گره. */
    public ?int $editingId = null;

    public string $editingType = ''; // province | city | district

    public string $editName = '';

    public int $editSort = 0;

    /** پیام نتیجهٔ عملیات. */
    public ?string $flashMessage = null;

    public string $flashType = 'success';

    // ─── خواندن داده (Computed، کش یک render) ─────────────────────

    #[Computed]
    public function provinces()
    {
        return Province::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->withCount(['cities as cities_count' => fn ($q) => $q->whereNull('parent_city_id')])
            ->ordered()
            ->get();
    }

    #[Computed]
    public function cities()
    {
        if (! $this->openProvince) {
            return collect();
        }

        return City::query()
            ->where('province_id', $this->openProvince)
            ->mainCities()
            ->withCount('districts')
            ->ordered()
            ->get();
    }

    #[Computed]
    public function districts()
    {
        if (! $this->openCity) {
            return collect();
        }

        return City::query()
            ->where('parent_city_id', $this->openCity)
            ->ordered()
            ->get();
    }

    // ─── باز/بسته کردن گره‌ها ─────────────────────────────────────

    public function toggleProvinceOpen(int $id): void
    {
        $this->reset('flashMessage');
        $this->cancelEdit();
        $this->openProvince = $this->openProvince === $id ? null : $id;
        $this->openCity = null;
        $this->newCityName = '';
    }

    public function toggleCityOpen(int $id): void
    {
        $this->reset('flashMessage');
        $this->cancelEdit();
        $this->openCity = $this->openCity === $id ? null : $id;
        $this->newDistrictName = '';
    }

    // ─── فعال/غیرفعال کردن (نمایش در اپ) ──────────────────────────

    public function toggleActive(string $type, int $id): void
    {
        $this->guard();

        $row = $type === 'province' ? Province::findOrFail($id) : City::findOrFail($id);
        $row->forceFill(['is_active' => ! $row->is_active])->save();

        $this->flash('success', $row->is_active ? 'فعال شد و در اپ نمایش داده می‌شود.' : 'غیرفعال شد و از اپ حذف شد.');
        $this->forgetData();
    }

    // ─── افزودن ───────────────────────────────────────────────────

    public function addProvince(): void
    {
        $this->guard();

        $data = $this->validate(
            ['newProvinceName' => 'required|string|max:255'],
            [],
            ['newProvinceName' => 'نام استان']
        );

        Province::create([
            'name' => $data['newProvinceName'],
            'slug' => $this->uniqueProvinceSlug($data['newProvinceName']),
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->newProvinceName = '';
        $this->flash('success', 'استان اضافه شد.');
        $this->forgetData();
    }

    public function addCity(): void
    {
        $this->guard();
        abort_unless((bool) $this->openProvince, 400);

        $data = $this->validate(
            ['newCityName' => 'required|string|max:255'],
            [],
            ['newCityName' => 'نام شهر']
        );

        $province = Province::findOrFail($this->openProvince);

        City::create([
            'province_id' => $province->id,
            'parent_city_id' => null,
            'name' => $data['newCityName'],
            'slug' => $this->uniqueSlug($data['newCityName'], (int) $province->id),
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->newCityName = '';
        $this->flash('success', 'شهر اضافه شد.');
        $this->forgetData();
    }

    public function addDistrict(): void
    {
        $this->guard();
        abort_unless((bool) $this->openCity, 400);

        $data = $this->validate(
            ['newDistrictName' => 'required|string|max:120'],
            [],
            ['newDistrictName' => 'نام منطقه']
        );

        $parent = City::mainCities()->findOrFail($this->openCity);

        City::create([
            'province_id' => $parent->province_id,
            'parent_city_id' => $parent->id,
            'name' => $data['newDistrictName'],
            'slug' => $this->uniqueSlug($data['newDistrictName'], (int) $parent->province_id),
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->newDistrictName = '';
        $this->flash('success', 'منطقه اضافه شد.');
        $this->forgetData();
    }

    // ─── ویرایش inline (نام و ترتیب) ──────────────────────────────

    public function startEdit(string $type, int $id): void
    {
        $this->guard();
        $this->reset('flashMessage');

        $row = $type === 'province' ? Province::findOrFail($id) : City::findOrFail($id);
        $this->editingType = $type;
        $this->editingId = $id;
        $this->editName = $row->name;
        $this->editSort = (int) $row->sort_order;
    }

    public function saveEdit(): void
    {
        $this->guard();
        abort_if($this->editingId === null, 400);

        $data = $this->validate([
            'editName' => 'required|string|max:255',
            'editSort' => 'nullable|integer|min:0|max:9999',
        ], [], ['editName' => 'نام', 'editSort' => 'ترتیب']);

        $row = $this->editingType === 'province'
            ? Province::findOrFail($this->editingId)
            : City::findOrFail($this->editingId);

        $row->update([
            'name' => $data['editName'],
            'sort_order' => $data['editSort'] ?? 0,
        ]);

        $this->cancelEdit();
        $this->flash('success', 'ذخیره شد.');
        $this->forgetData();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingType = '';
        $this->editName = '';
        $this->editSort = 0;
    }

    // ─── حذف (با محافظ داده) ──────────────────────────────────────

    public function deleteProvince(int $id): void
    {
        $this->guard();
        $province = Province::findOrFail($id);

        // حذف آبشاری: شهرها و مناطق این استان هم حذف می‌شوند. ارجاع‌ها در
        // سفارش/آدرس مشتری به‌صورت خودکار null می‌شوند (دادهٔ سفارش پاک نمی‌شود).
        try {
            City::where('province_id', $id)->delete();
            $province->delete();
        } catch (\Throwable $e) {
            $this->flash('error', 'حذف استان ممکن نشد: '.$e->getMessage());

            return;
        }

        if ($this->openProvince === $id) {
            $this->openProvince = null;
            $this->openCity = null;
        }
        $this->flash('success', 'استان و همهٔ شهرها و مناطق آن حذف شد.');
        $this->forgetData();
    }

    public function deleteCity(int $id): void
    {
        $this->guard();
        $city = City::mainCities()->findOrFail($id);

        // حذف آبشاری مناطق این شهر.
        try {
            City::where('parent_city_id', $id)->delete();
            $city->delete();
        } catch (\Throwable $e) {
            $this->flash('error', 'حذف شهر ممکن نشد: '.$e->getMessage());

            return;
        }

        if ($this->openCity === $id) {
            $this->openCity = null;
        }
        $this->flash('success', 'شهر و همهٔ مناطق آن حذف شد.');
        $this->forgetData();
    }

    public function deleteDistrict(int $id): void
    {
        $this->guard();
        $district = City::whereNotNull('parent_city_id')->findOrFail($id);

        try {
            $district->delete();
        } catch (\Throwable $e) {
            $this->flash('error', 'حذف منطقه ممکن نشد: '.$e->getMessage());

            return;
        }

        $this->flash('success', 'منطقه حذف شد.');
        $this->forgetData();
    }

    public function render()
    {
        return view('crm::livewire.service-area-manager');
    }

    // ─── کمکی ─────────────────────────────────────────────────────

    private function guard(): void
    {
        abort_unless(Gate::allows('manage-crm-settings'), 403);
    }

    private function flash(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
    }

    /** پاک‌سازی کش Computedها بعد از تغییر داده. */
    private function forgetData(): void
    {
        unset($this->provinces, $this->cities, $this->districts);
    }

    private function uniqueProvinceSlug(string $name): string
    {
        $base = $this->slugBase($name);
        $slug = $base;
        $i = 2;
        while (Province::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /** slug یکتا در محدودهٔ استان — قید جدول: (province_id, slug). */
    private function uniqueSlug(string $name, int $provinceId): string
    {
        $base = $this->slugBase($name);
        $slug = $base;
        $i = 2;
        while (City::where('province_id', $provinceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * ارقام فارسی/عربی را به انگلیسی تبدیل می‌کنیم تا «منطقه ۱» و «منطقه ۲»
     * slugهای متمایز بسازند (وگرنه Str::slug رقم را حذف می‌کند). برای نام
     * کاملاً فارسیِ بدون رقم، Str::slug خالی برمی‌گرداند پس به base تصادفی
     * fallback می‌کنیم.
     */
    private function slugBase(string $name): string
    {
        $name = strtr($name, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return Str::slug($name) ?: ('area-'.Str::random(6));
    }
}
