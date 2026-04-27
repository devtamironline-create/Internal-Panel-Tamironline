<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\Customer;
use Throwable;

/**
 * Endpointهای سینک مشتری از CRM وردپرسی.
 *
 * منطق upsert:
 *  1) ابتدا با wp_id جستجو می‌کند (اگر از قبل سینک شده).
 *  2) در غیر این صورت با mobile جستجو می‌کند و در صورت یافتن، wp_id را
 *     هم به آن لینک می‌کند.
 *  3) در غیر این صورت رکورد جدید می‌سازد.
 */
class SyncCustomerController extends Controller
{
    /** سینک تکی — POST /api/crm/sync/customer */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $result = $this->upsertOne($data);

        return response()->json([
            'ok' => true,
        ] + $result);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/customers/batch */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.wp_id' => 'required|integer|min:1',
            'items.*.mobile' => 'required|string|max:20',
            'items.*.first_name' => 'nullable|string|max:255',
            'items.*.phone' => 'nullable|string|max:20',
            'items.*.notes' => 'nullable|string',
        ]);

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

    /** قوانین اعتبارسنجی برای رکورد تکی. */
    protected function rules(): array
    {
        return [
            'wp_id' => 'required|integer|min:1',
            'mobile' => 'required|string|max:20',
            'first_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * upsert یک رکورد. تطبیق بر اساس wp_id، سپس mobile.
     *
     * @return array{ action: 'created'|'updated', id: int, wp_id: int, subscription: int }
     */
    protected function upsertOne(array $data): array
    {
        $wpId = (int) $data['wp_id'];
        unset($data['wp_id']);

        return DB::transaction(function () use ($wpId, $data) {
            $customer = Customer::where('wp_id', $wpId)->lockForUpdate()->first();

            if (! $customer) {
                $customer = Customer::where('mobile', $data['mobile'])
                    ->lockForUpdate()
                    ->first();
            }

            if ($customer) {
                $customer->fill($data);
                if ($customer->wp_id === null) {
                    $customer->wp_id = $wpId;
                }
                $changed = $customer->isDirty();
                $customer->save();
                $action = 'updated';
            } else {
                $customer = Customer::create($data + ['wp_id' => $wpId]);
                $action = 'created';
            }

            return [
                'action' => $action,
                'id' => (int) $customer->id,
                'wp_id' => (int) $customer->wp_id,
                'subscription' => (int) $customer->subscription,
            ];
        });
    }
}
