<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * مدیریت دستگاه‌محورِ صفحات ترکیبی (device × brand).
 *
 * ادمین یک «دستگاه» را انتخاب می‌کند، تمام برندهای آن دستگاه را می‌بیند
 * و می‌تواند هر صفحهٔ ترکیبی (دستگاه × برند) را به‌سادگی فعال/غیرفعال کند.
 * این صفحه فقط برای مدیر کل (manage-permissions) قابل دسترسی است؛
 * گیتِ سطح روت این را تضمین می‌کند و در toggle نیز دوباره چک می‌شود.
 */
class ComboManagerController extends Controller
{
    public function index(Request $request)
    {
        // ─── همهٔ دستگاه‌های فعال برای نوار انتخاب دستگاه ─────────────
        $devices = Device::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'thumbnail']);

        // ─── دستگاه انتخاب‌شده از ?device={id} (پیش‌فرض: اولین دستگاه) ─
        $deviceId = (int) $request->query('device', 0);
        $device = $deviceId
            ? $devices->firstWhere('id', $deviceId)
            : $devices->first();

        $brandRows = collect();
        $summary = ['active' => 0, 'total' => 0];

        if ($device) {
            // ─── برندهای متصل به این دستگاه؛ اگر pivot خالی بود همهٔ
            //     برندهای فعال را نشان بده تا ادمین بتواند combo بسازد. ──
            $brands = $device->brands()
                ->where('is_active', true)
                ->get(['crm_brands.id', 'crm_brands.name', 'crm_brands.slug', 'crm_brands.logo']);

            if ($brands->isEmpty()) {
                $brands = Brand::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'logo']);
            }

            // ─── رکوردهای موجودِ صفحه‌ی ترکیبی را با یک کوئری whereIn
            //     بکش (بدون N+1) و map با کلید brand_id بساز. ──────────
            $brandIds = $brands->pluck('id')->all();
            $pages = DeviceBrandPage::query()
                ->where('device_id', $device->id)
                ->whereIn('brand_id', $brandIds)
                ->get(['id', 'brand_id', 'is_active'])
                ->keyBy('brand_id');

            // ─── هر ردیف برند می‌داند: combo ندارد | فعال | غیرفعال ───
            $brandRows = $brands->map(function ($brand) use ($pages) {
                $page = $pages->get($brand->id);

                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'logo' => $brand->logo,
                    'page_id' => $page?->id,
                    'has_page' => $page !== null,
                    'is_active' => (bool) ($page->is_active ?? false),
                ];
            });

            $summary = [
                'active' => $brandRows->where('is_active', true)->count(),
                'total' => $brandRows->count(),
            ];
        }

        return view('crm::combo-manager.index', compact('devices', 'device', 'brandRows', 'summary'));
    }

    /**
     * فعال/غیرفعال‌سازی یک صفحهٔ ترکیبی (دستگاه × برند).
     *
     * اگر رکورد وجود نداشته باشد ساخته و فعال می‌شود؛ اگر وجود داشته باشد
     * وضعیت is_active برعکس می‌شود (override‌های ادمین دست‌نخورده می‌ماند).
     */
    public function toggle(Request $request, Device $device, Brand $brand)
    {
        // ─── دفاع لایه‌ی دوم: فقط مدیر کل (علاوه بر گیتِ روت). ────────
        if (! $request->user()?->can('manage-permissions')) {
            abort(403, 'فعال/غیرفعال‌سازی صفحهٔ ترکیبی فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }

        $page = DeviceBrandPage::firstOrNew([
            'device_id' => $device->id,
            'brand_id' => $brand->id,
        ]);

        if (! $page->exists) {
            // رکورد تازه → ساختن و فعال‌کردن. بقیه ستون‌ها null می‌مانند
            // و API هنگام رندر مقادیر per-device/per-brand را merge می‌کند.
            $page->is_active = true;
        } else {
            $page->is_active = ! $page->is_active;
        }

        $page->save();

        $message = $page->is_active
            ? 'صفحهٔ ترکیبی فعال شد.'
            : 'صفحهٔ ترکیبی غیرفعال شد.';

        return redirect()
            ->route('crm.combo-manager.index', ['device' => $device->id])
            ->with('success', $message);
    }

    /**
     * فعال/غیرفعال‌سازی گروهیِ صفحات ترکیبیِ یک دستگاه — فقط مدیر کل.
     *
     * اگر brand_ids خالی باشد روی همهٔ برندهای این دستگاه اعمال می‌شود
     * (همان منبعِ index(): برندهای فعالِ متصل، در صورت خالی‌بودن همهٔ
     * برندهای فعال). برای هر برند رکورد صفحهٔ ترکیبی ساخته/به‌روز می‌شود.
     */
    public function bulkToggle(Request $request, Device $device)
    {
        // ─── دفاع لایه‌ی دوم: فقط مدیر کل (علاوه بر گیتِ روت). ────────
        if (! $request->user()?->can('manage-permissions')) {
            abort(403, 'عملیات گروهیِ فعال/غیرفعال‌سازی فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }

        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer|exists:crm_brands,id',
        ]);

        $activate = $validated['action'] === 'activate';

        // ─── تعیین مجموعهٔ برندهای هدف ──────────────────────────────
        $brandIds = array_values(array_unique(array_map('intval', $validated['brand_ids'] ?? [])));

        if (empty($brandIds)) {
            // همان منبع index(): برندهای فعالِ متصل، در صورت خالی‌بودن
            // همهٔ برندهای فعال.
            $brandIds = $device->brands()
                ->where('is_active', true)
                ->pluck('crm_brands.id')
                ->all();

            if (empty($brandIds)) {
                $brandIds = Brand::query()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();
            }
        }

        $count = 0;
        foreach ($brandIds as $bId) {
            $page = DeviceBrandPage::firstOrNew([
                'device_id' => $device->id,
                'brand_id' => (int) $bId,
            ]);
            $page->is_active = $activate;
            $page->save();
            $count++;
        }

        $verb = $activate ? 'فعال' : 'غیرفعال';
        $message = "{$count} صفحهٔ ترکیبی {$verb} شد.";

        return redirect()
            ->route('crm.combo-manager.index', ['device' => $device->id])
            ->with('success', $message);
    }
}
