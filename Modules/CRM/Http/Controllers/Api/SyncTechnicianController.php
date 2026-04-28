<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;
use Throwable;

/**
 * Endpointهای سینک تکنسین از CRM وردپرسی.
 *
 * مرجع نام فیلدها: libs/technician.php سمت WP. در API همان نام‌های WP
 * پذیرفته می‌شود؛ تنها استثنا ready_for_derliver (تایپو در WP) است که
 * به ready_for_delivery نگاشت می‌شود.
 */
class SyncTechnicianController extends Controller
{
    /** سینک تکی — POST /api/crm/sync/technician */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $result = $this->upsertOne($data);

        return response()->json(['ok' => true] + $result);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/technicians/batch */
    public function batch(Request $request): JsonResponse
    {
        $base = $this->rules();
        $rules = ['items' => 'required|array|min:1|max:200'];
        foreach ($base as $key => $rule) {
            $rules['items.*.' . $key] = $rule;
        }

        $data = $request->validate($rules);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            try {
                $r = $this->upsertOne($item);
                $r['action'] === 'created' ? $created++ : $updated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'index' => $i,
                    'wp_id' => $item['wp_id'] ?? null,
                    'mobile' => $item['mobile'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'total' => count($data['items']),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    protected function rules(): array
    {
        return [
            'wp_id' => 'required|integer|min:1',
            'mobile' => 'required|string|max:20',

            // مشخصات
            'first_name' => 'nullable|string|max:255',
            'firstname_tech' => 'nullable|string|max:255',
            'technician_id' => 'nullable|string|max:50',
            'national_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'phone_force' => 'nullable|string|max:20',

            // آدرس
            'province' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:5000',

            // تخصص
            'specialty' => 'nullable|string|max:255',
            'type_tech' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:10000',

            // تصاویر
            'img_personal' => 'nullable|string|max:1000',
            'cart_img' => 'nullable|string|max:1000',

            // مالی
            'percent' => 'nullable|integer|min:0|max:100',
            'tech_per_of_all' => 'nullable|integer|min:0|max:100',
            'max_order' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'type_of_calc_tech' => 'nullable|string|max:50',

            // وضعیت — WP value هرچه باشد می‌پذیریم؛ نگاشت در upsertOne
            'status' => 'nullable|string|max:30',

            // تایپو WP: ready_for_derliver. برای راحتی هر دو شکل را قبول می‌کنیم.
            'ready_for_delivery' => 'nullable',
            'ready_for_derliver' => 'nullable',
        ];
    }

    /**
     * upsert یک رکورد تکنسین. تطبیق بر اساس wp_id، سپس mobile.
     */
    protected function upsertOne(array $data): array
    {
        $wpId = (int) $data['wp_id'];
        unset($data['wp_id']);

        // تایپوی WP را به نام درست ترجمه می‌کنیم
        if (! array_key_exists('ready_for_delivery', $data) && array_key_exists('ready_for_derliver', $data)) {
            $data['ready_for_delivery'] = $data['ready_for_derliver'];
        }
        unset($data['ready_for_derliver']);

        // مقدار boolean
        if (array_key_exists('ready_for_delivery', $data)) {
            $data['ready_for_delivery'] = filter_var($data['ready_for_delivery'], FILTER_VALIDATE_BOOLEAN);
        }

        return DB::transaction(function () use ($wpId, $data) {
            $tech = Technician::where('wp_id', $wpId)->lockForUpdate()->first();

            if (! $tech) {
                $tech = Technician::where('mobile', $data['mobile'])
                    ->lockForUpdate()
                    ->first();
            }

            if ($tech) {
                $tech->fill($data);
                if ($tech->wp_id === null) {
                    $tech->wp_id = $wpId;
                }
                $tech->save();
                $action = 'updated';
            } else {
                $tech = Technician::create($data + ['wp_id' => $wpId]);
                $action = 'created';
            }

            return [
                'action' => $action,
                'id' => (int) $tech->id,
                'wp_id' => (int) $tech->wp_id,
            ];
        });
    }
}
