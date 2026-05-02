<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Throwable;

/**
 * Endpointهای سینک سفارش از CRM وردپرسی (post_type=orders).
 *
 * منطق upsert:
 *  1) تطبیق با wp_id (post_id سفارش در WP).
 *  2) اگر سفارش لاراولی موجود نبود، یک رکورد جدید با order_code جدید
 *     ساخته می‌شود.
 *
 * نگاشت روابط: همهٔ FKها (customer/technician/brand/device/province/city)
 * با ارسال wp_id متناظر (term_id برای taxonomyها، user_id برای customer/
 * technician) و resolve درون این کنترلر انجام می‌شود. اگر مرجع پیدا
 * نشد، آن FK به null می‌نشیند مگر customer که اجباری است.
 *
 * نگاشت وضعیت: مقدار عددی WP (0,1,2,3,4,5,10,100) با
 * OrderStatus::fromWpCode() به enum لاراول تبدیل می‌شود.
 */
class SyncOrderController extends Controller
{
    /** سینک تکی — POST /api/crm/sync/order */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $result = $this->upsertOne($data);

        return response()->json(['ok' => true] + $result);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/orders/batch */
    public function batch(Request $request): JsonResponse
    {
        $batchRules = ['items' => 'required|array|min:1|max:50'];
        foreach ($this->rules() as $key => $rule) {
            $batchRules["items.*.{$key}"] = $rule;
        }
        $data = $request->validate($batchRules);

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

    /** قواعد اعتبارسنجی برای یک رکورد. */
    protected function rules(): array
    {
        return [
            'wp_id' => 'required|integer|min:1',

            // FK refs (همه بر حسب wp_id متناظر؛ resolve داخلی)
            'customer_wp_id' => 'required|integer|min:1',
            'technician_wp_id' => 'nullable|integer|min:1',
            'brand_wp_id' => 'nullable|integer|min:1',
            'device_wp_id' => 'nullable|integer|min:1',
            'state_wp_id' => 'nullable|integer|min:1',  // WP اسمش state
            'city_wp_id' => 'nullable|integer|min:1',

            // پایه
            'subscription' => 'nullable|integer',
            'introduction' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',     // → customer_mobile snapshot
            'phone' => 'nullable|string|max:20',      // → customer_phone snapshot
            'address' => 'nullable|string|max:2000',
            'postal_code' => 'nullable|string|max:20',
            'objection' => 'nullable|string|max:255',         // → problem_title
            'objection_description' => 'nullable|string',     // → problem_description

            // وضعیت (عددی WP)
            'status' => 'nullable|integer',
            'order_type' => 'nullable|string|max:30',
            'return_type' => 'nullable|string|max:30',
            'return_description' => 'nullable|string',
            'status_internal_order' => 'nullable|string|max:30',
            'qc_status' => 'nullable|string|max:30',

            // پرچم‌ها
            'send_technician' => 'nullable|boolean',
            'send_sms_tec' => 'nullable|boolean',
            'send_sms_customer' => 'nullable|boolean',
            'save_as_draft' => 'nullable|boolean',
            'have_invoice' => 'nullable|boolean',
            'finish_order' => 'nullable|boolean',
            'finish_order_sh' => 'nullable|boolean',

            // مالی
            'customer_price' => 'nullable|integer|min:0',
            'buy_price' => 'nullable|integer|min:0',
            'price_customer' => 'nullable|integer|min:0',
            'cost_price' => 'nullable|integer|min:0',
            'total_invoice' => 'nullable|integer|min:0',
            'negative_invoice' => 'nullable|integer',
            'price_return' => 'nullable|integer|min:0',
            'type_of_send_invoice' => 'nullable|string|max:30',
            'invoice_email' => 'nullable|string|max:255',
            'invoice_paper' => 'nullable|string|max:255',
            'invoice_descripotion' => 'nullable|string',
            'hire' => 'nullable|integer|min:0',
            'transportation' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0',

            // تکنسین — متن و لیست
            'description_tech' => 'nullable|string',
            'description_tech1' => 'nullable|string',
            'description_tech2' => 'nullable|string',
            'piece_list' => 'nullable|array',
            'customer_price_list' => 'nullable|array',
            'buy_price_list' => 'nullable|array',
            'device_img1' => 'nullable|string|max:500',
            'device_image_input' => 'nullable|string|max:500',

            // logging
            'order_description_content' => 'nullable|string',
            'order_note_content' => 'nullable|string',
            'log_return' => 'nullable|string',

            // زمان‌بندی
            'visit_scheduled_at' => 'nullable|date',
        ];
    }

    /**
     * upsert یک سفارش. تطبیق با wp_id.
     *
     * @return array{action:'created'|'updated', id:int, wp_id:int, order_code:string}
     */
    protected function upsertOne(array $data): array
    {
        $wpId = (int) $data['wp_id'];

        return DB::transaction(function () use ($wpId, $data) {
            $order = Order::where('wp_id', $wpId)->lockForUpdate()->first();

            $payload = $this->buildPayload($data);

            if ($order) {
                $order->fill($payload);
                $order->save();
                $action = 'updated';
            } else {
                $payload['wp_id'] = $wpId;
                $payload['order_code'] = Order::generateOrderCode();
                $order = Order::create($payload);
                $action = 'created';
            }

            return [
                'action' => $action,
                'id' => (int) $order->id,
                'wp_id' => (int) $order->wp_id,
                'order_code' => (string) $order->order_code,
            ];
        });
    }

    /**
     * نگاشت کامل payload WP → ستون‌های crm_orders.
     * Foreign keys بر اساس wp_id resolve می‌شوند.
     *
     * @throws \RuntimeException اگر مشتری پیدا نشود.
     */
    protected function buildPayload(array $data): array
    {
        $customer = Customer::where('wp_id', (int) $data['customer_wp_id'])->first();
        if (! $customer) {
            throw new \RuntimeException(
                'customer not synced yet (wp_id=' . $data['customer_wp_id'] . ')'
            );
        }

        $payload = [
            'customer_id' => $customer->id,
            'subscription' => $data['subscription'] ?? null,
            'introduction' => $data['introduction'] ?? null,
            'order_type' => $data['order_type'] ?? null,

            // FK resolves (با fallback به null اگر مرجع نبود)
            'technician_id' => $this->resolveId(Technician::class, $data['technician_wp_id'] ?? null),
            'brand_id' => $this->resolveId(Brand::class, $data['brand_wp_id'] ?? null),
            'device_id' => $this->resolveId(Device::class, $data['device_wp_id'] ?? null),
            'province_id' => $this->resolveId(Province::class, $data['state_wp_id'] ?? null),
            'city_id' => $this->resolveId(City::class, $data['city_wp_id'] ?? null),

            // snapshot مشتری
            'customer_name' => $customer->display_name ?? null,
            'customer_mobile' => $data['mobile'] ?? $customer->mobile ?? '',
            'customer_phone' => $data['phone'] ?? $customer->phone ?? null,

            // محل و شرح
            'address' => $data['address'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'problem_title' => $data['objection'] ?? null,
            'problem_description' => $data['objection_description'] ?? null,

            // وضعیت — WP code → string enum
            'status' => OrderStatus::fromWpCode($data['status'] ?? null)?->value
                ?? OrderStatus::New->value,
            'return_type' => $data['return_type'] ?? null,
            'return_description' => $data['return_description'] ?? null,
            'status_internal_order' => $data['status_internal_order'] ?? null,
            'qc_status' => $data['qc_status'] ?? null,

            // پرچم‌ها
            'send_technician' => (bool) ($data['send_technician'] ?? false),
            'send_sms_tec' => (bool) ($data['send_sms_tec'] ?? false),
            'send_sms_customer' => (bool) ($data['send_sms_customer'] ?? false),
            'save_as_draft' => (bool) ($data['save_as_draft'] ?? false),
            'have_invoice' => (bool) ($data['have_invoice'] ?? false),
            'finish_order' => (bool) ($data['finish_order'] ?? false),
            'finish_order_sh' => (bool) ($data['finish_order_sh'] ?? false),

            // مالی (نام WP حفظ شده)
            'customer_price' => $data['customer_price'] ?? null,
            'buy_price' => $data['buy_price'] ?? null,
            'price_customer' => $data['price_customer'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'total_invoice' => $data['total_invoice'] ?? null,
            'negative_invoice' => $data['negative_invoice'] ?? null,
            'price_return' => $data['price_return'] ?? null,
            'type_of_send_invoice' => $data['type_of_send_invoice'] ?? null,
            'invoice_email' => $data['invoice_email'] ?? null,
            'invoice_paper' => $data['invoice_paper'] ?? null,
            'invoice_descripotion' => $data['invoice_descripotion'] ?? null,
            'hire' => $data['hire'] ?? null,
            'transportation' => $data['transportation'] ?? null,
            'discount' => $data['discount'] ?? null,

            // تکنسین
            'description_tech' => $data['description_tech'] ?? null,
            'description_tech1' => $data['description_tech1'] ?? null,
            'description_tech2' => $data['description_tech2'] ?? null,
            'piece_list' => $data['piece_list'] ?? null,
            'customer_price_list' => $data['customer_price_list'] ?? null,
            'buy_price_list' => $data['buy_price_list'] ?? null,
            'device_img1' => $data['device_img1'] ?? null,
            'device_image_input' => $data['device_image_input'] ?? null,

            // logging
            'order_description_content' => $data['order_description_content'] ?? null,
            'order_note_content' => $data['order_note_content'] ?? null,
            'log_return' => $data['log_return'] ?? null,

            // زمان‌بندی
            'visit_scheduled_at' => $data['visit_scheduled_at'] ?? null,
        ];

        // final_price سنتی لاراول از total_invoice (اگر داشته باشیم)
        // پر می‌شود تا dashboard/گزارش‌های موجود کار کنند.
        if (isset($data['total_invoice']) && $data['total_invoice'] !== null) {
            $payload['final_price'] = (int) $data['total_invoice'];
        }

        return $payload;
    }

    /**
     * resolve یک FK از روی wp_id. در صورت نبودن یا ناشناخته بودن، null.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function resolveId(string $modelClass, int|string|null $wpId): ?int
    {
        if ($wpId === null || $wpId === '' || (int) $wpId <= 0) {
            return null;
        }

        return $modelClass::where('wp_id', (int) $wpId)->value('id');
    }
}
