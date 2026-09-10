<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * سطلِ بازیافتِ محتوای سایت — فهرستِ موجودیت‌های soft-delete‌شده
 * (برند/دستگاه/صفحهٔ ترکیبی/صفحهٔ شهر) و بازگردانیِ آن‌ها.
 *
 * حذفِ دائمی عمداً وجود ندارد؛ فقط بازگردانی. دسترسی: delete-seo-content.
 */
class SeoTrashController extends Controller
{
    /** نگاشتِ نوع → کلاسِ مدل. */
    private const TYPES = [
        'brand' => Brand::class,
        'device' => Device::class,
        'device-brand-page' => DeviceBrandPage::class,
        'city-page' => CityPage::class,
    ];

    private const LABELS = [
        'brand' => 'برندها',
        'device' => 'دستگاه‌ها',
        'device-brand-page' => 'صفحاتِ ترکیبی',
        'city-page' => 'صفحاتِ شهر',
    ];

    public function index()
    {
        $groups = [];
        foreach (self::TYPES as $type => $class) {
            /** @var class-string<Model> $class */
            $rows = $class::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->limit(500)
                ->get()
                ->map(function (Model $m) use ($type) {
                    $d = method_exists($m, 'seoDeletionDescriptor')
                        ? $m->seoDeletionDescriptor()
                        : ['type' => '', 'name' => (string) $m->getKey(), 'slug' => null];

                    return [
                        'id' => $m->getKey(),
                        'type' => $type,
                        'name' => $d['name'] ?? (string) $m->getKey(),
                        'slug' => $d['slug'] ?? null,
                        'deleted_at' => $m->deleted_at,
                    ];
                });

            $groups[$type] = [
                'label' => self::LABELS[$type],
                'rows' => $rows,
            ];
        }

        return view('crm::seo-trash.index', ['groups' => $groups]);
    }

    public function restore(string $type, int $id)
    {
        if (! isset(self::TYPES[$type])) {
            abort(404);
        }

        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];
        $model = $class::onlyTrashed()->find($id);

        if (! $model) {
            return redirect()->route('crm.seo-trash.index')
                ->with('error', 'موردی برای بازگردانی یافت نشد (شاید قبلاً بازگردانی شده).');
        }

        $model->restore();

        $name = method_exists($model, 'seoDeletionDescriptor')
            ? ($model->seoDeletionDescriptor()['name'] ?? $id)
            : $id;

        return redirect()->route('crm.seo-trash.index')
            ->with('success', 'بازگردانی شد: '.$name.' — توجه: اگر غیرفعال بود، باید جداگانه فعالش کنید.');
    }
}
