<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Endpointهای سینک تاکسونومی‌های CRM وردپرسی (terms).
 *
 * چهار نوع پشتیبانی می‌شود:
 *  - brand   → crm_brands
 *  - device  → crm_devices
 *  - province (در WP: state) → crm_provinces
 *  - city    → crm_cities
 *
 * در WP این‌ها همه taxonomy ساده هستند با سه فیلد: term_id, name, slug.
 * Idempotent با کلید wp_id.
 */
class SyncTaxonomyController extends Controller
{
    /** نگاشت type → کلاس مدل لاراول */
    public const TAXONOMIES = [
        'brand'    => Brand::class,
        'device'   => Device::class,
        'province' => Province::class,
        'city'     => City::class,
    ];

    /** سینک تکی — POST /api/crm/sync/taxonomy/{type} */
    public function upsert(Request $request, string $type): JsonResponse
    {
        $modelClass = $this->modelFor($type);
        if (! $modelClass) {
            return response()->json(['ok' => false, 'error' => 'unknown taxonomy'], 400);
        }

        $data = $request->validate([
            'wp_id' => 'required|integer|min:1',
            'name'  => 'required|string|max:255',
            'slug'  => 'nullable|string|max:255',
        ]);

        $result = $this->upsertOne($modelClass, $data);

        return response()->json(['ok' => true] + $result);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/taxonomy/{type}/batch */
    public function batch(Request $request, string $type): JsonResponse
    {
        $modelClass = $this->modelFor($type);
        if (! $modelClass) {
            return response()->json(['ok' => false, 'error' => 'unknown taxonomy'], 400);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1|max:500',
            'items.*.wp_id' => 'required|integer|min:1',
            'items.*.name'  => 'required|string|max:255',
            'items.*.slug'  => 'nullable|string|max:255',
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            try {
                $r = $this->upsertOne($modelClass, $item);
                $r['action'] === 'created' ? $created++ : $updated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'index' => $i,
                    'wp_id' => $item['wp_id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'type' => $type,
            'total' => count($data['items']),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    protected function modelFor(string $type): ?string
    {
        return self::TAXONOMIES[$type] ?? null;
    }

    /**
     * upsert یک رکورد تاکسونومی. تطبیق ابتدا با wp_id، سپس با name (case-sensitive).
     *
     * @param  class-string<Model>  $modelClass
     * @return array{ action: 'created'|'updated', id: int, wp_id: int }
     */
    protected function upsertOne(string $modelClass, array $data): array
    {
        $wpId = (int) $data['wp_id'];

        return DB::transaction(function () use ($modelClass, $wpId, $data) {
            /** @var Model $row */
            $row = $modelClass::where('wp_id', $wpId)->lockForUpdate()->first();

            if (! $row) {
                // در صورت نبود wp_id، اگر رکورد هم‌نام موجود است آن را لینک می‌کنیم
                $row = $modelClass::where('name', $data['name'])->whereNull('wp_id')->lockForUpdate()->first();
            }

            $payload = [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $data['name'],
            ];

            if ($row) {
                $row->fill($payload);
                if ($row->wp_id === null) {
                    $row->wp_id = $wpId;
                }
                $row->save();
                $action = 'updated';
            } else {
                $row = $modelClass::create($payload + ['wp_id' => $wpId]);
                $action = 'created';
            }

            return [
                'action' => $action,
                'id' => (int) $row->id,
                'wp_id' => (int) $row->wp_id,
            ];
        });
    }
}
