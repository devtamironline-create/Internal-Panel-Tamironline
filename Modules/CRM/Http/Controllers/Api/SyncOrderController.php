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
        $skipped = 0;
        $skippedDetails = [];
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            try {
                $r = $this->upsertOne($item);
                if ($r['action'] === 'created') {
                    $created++;
                } elseif ($r['action'] === 'updated') {
                    $updated++;
                } else {
                    // 'skipped' (source_of_truth بلاک کرد). قبلاً اشتباها
                    // به‌عنوان updated شمارش می‌شد — این باعث می‌شد افزونهٔ
                    // WP فکر کند سفارش سینک شد و تلاش مجدد نکند.
                    $skipped++;
                    $skippedDetails[] = [
                        'index' => $i,
                        'wp_id' => $item['wp_id'] ?? null,
                        'reason' => $r['reason'] ?? 'unknown',
                    ];
                }
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
            'skipped' => $skipped,
            'skipped_details' => $skippedDetails,
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
            // objection در WP پست‌متا آرایه است (لیست ایرادها). پلاگین
            // آن را به‌صورت آرایه می‌فرستد؛ اگر تک‌ایراد باشد ممکن است
            // string هم بیاید — هر دو را قبول می‌کنیم.
            'objection' => 'nullable',                        // → problem_title
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

            // logging — WP postmeta is PHP-serialized array when read via
            // get_post_meta. Plugin may send them as either string (JSON
            // or already-serialized) or as a nested array. Accept both.
            'order_description_content' => 'nullable',
            'order_note_content' => 'nullable',
            'log_return' => 'nullable',

            // زمان‌بندی — visit_scheduled_at مستقیم، یا visit_date+visit_time
            // که می‌توانند جلالی/میلادی، با ارقام فارسی یا لاتین باشند.
            'visit_scheduled_at' => 'nullable|string|max:64',
            'visit_date' => 'nullable|string|max:32',
            'visit_time' => 'nullable|string|max:32',
            // DEBUG — موقتی برای کشف کلید زمان مراجعه در هاست کاربر.
            '_debug_all_meta' => 'nullable|array',

            // تاریخ ثبت سفارش در WP — به created_at نگاشت می‌شود تا
            // تاریخچهٔ واقعی حفظ شود (به‌جای زمان import).
            'post_date' => 'nullable|string|max:32',
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

            // منبع داده سفارش — source_of_truth در سطح خود سفارش، فقط
            // روی UPDATE سفارش‌های موجود اعمال می‌شود. ساخت اولیه همیشه
            // اجازه دارد.
            //
            // استثنا: اگر سفارش هنوز ناقص ست شده (FKهای اصلی null هستند)
            // و WP حالا با مقدار کامل آمده، بگذار payload کامل ذخیره شود.
            // پلاگین WP در چند مرحلهٔ هوک پشت‌سر هم payload می‌فرستد:
            // اولی با terms خالی، دومی با terms کامل. ما باید اولی + هر
            // تکمیل بعدی را قبول کنیم؛ فقط override تغییرات اپراتور را
            // بلاک کنیم.
            $isIncompleteOrder = $order && (
                $order->brand_id === null
                || $order->device_id === null
                || $order->province_id === null
                || $order->city_id === null
            );

            if ($order && ! $isIncompleteOrder && ! $order->shouldAcceptInboundFromWp()) {
                $sot = $order->source_of_truth ?: 'auto';
                $reason = $sot === 'auto'
                    ? 'blocked_by_technician_sync_direction'
                    : 'blocked_by_order_source_of_truth:'.$sot;

                return [
                    'action' => 'skipped',
                    'id' => $order->id,
                    'wp_id' => $wpId,
                    'order_code' => $order->order_code,
                    'reason' => $reason,
                ];
            }

            // post_date در WP زمان واقعی ثبت سفارش است؛ به created_at نگاشت
            // می‌شود تا تاریخچه دقیق باشد (به‌جای زمان import). در update هم
            // ست می‌کنیم چون post_date در WP immutable است و این تنها راه
            // اصلاح خودکار سفارش‌هایی است که قبلاً بدون تاریخ درست imported شده‌اند.
            $wpCreatedAt = $this->parseWpDate($data['post_date'] ?? null);

            if ($order) {
                // فیلدهایی که توسط تکنسین/اپراتور در پنل Laravel مدیریت
                // می‌شوند نباید با sync دوره‌ای WP overwrite شوند —
                // وگرنه هر بار cron WP اجرا می‌شود تغییرات Laravel
                // از بین می‌روند (مثلاً وضعیتی که تکنسین به Open برده
                // به New برمی‌گردد چون WP هنوز New است).
                $laravelManaged = [
                    'status', 'technician_id', 'visit_scheduled_at',
                    'description_tech', 'description_tech1', 'description_tech2',
                    'piece_list', 'buy_price_list', 'customer_price_list',
                    'price_customer', 'cost_price', 'total_invoice', 'final_price',
                    'hire', 'transportation', 'discount',
                    'device_img1', 'invoice_descripotion',
                    'save_as_draft', 'completed_at', 'cancel_reason',
                    'return_type', 'return_description',
                    'status_internal_order', 'qc_status',
                    'order_note_content', 'log_return',
                    'assigned_at',
                ];
                foreach ($laravelManaged as $key) {
                    unset($payload[$key]);
                }

                // اگر سفارش هنوز تکنسینی در Laravel ندارد ولی WP تکنسین
                // داده، آن را ست کن. این هم اعتماد به انتساب اولیهٔ WP
                // را حفظ می‌کند، هم نگذاشته تخصیص دستی Laravel overwrite
                // شود (که laravelManaged قبلاً جلویش را گرفته).
                if (! $order->technician_id && ! empty($payload['technician_wp_id'])) {
                    $resolved = $this->resolveId(Technician::class, $payload['technician_wp_id']);
                    if ($resolved) {
                        $order->technician_id = $resolved;
                        // بدونِ این، SLA فاز هماهنگی مبنای زمانی ندارد.
                        $order->assigned_at = $order->assigned_at ?? now();
                    }
                }

                $order->fill($payload);
                if ($wpCreatedAt) {
                    $order->created_at = $wpCreatedAt;
                }
                $order->save();
                $action = 'updated';
            } else {
                $payload['wp_id'] = $wpId;
                $payload['order_code'] = Order::generateOrderCode();
                if ($wpCreatedAt) {
                    $payload['created_at'] = $wpCreatedAt;
                }
                // سفارشی که از همان ابتدا «انجام کار» از WP می‌آید
                // completed_at ندارد (WP این فیلد را نمی‌فرستد). بدون
                // آن، صفحهٔ «فاکتورهای از قلم افتاده» سفارش را هرگز
                // نمی‌دید. زمان دقیق تکمیل را نداریم — زمان import
                // نزدیک‌ترین تقریب است و سفارش را در پنجرهٔ بررسی
                // نگه می‌دارد.
                if (($payload['status'] ?? null) === OrderStatus::Completed->value) {
                    $payload['completed_at'] = now();
                }
                $order = Order::create($payload);
                $action = 'created';
            }

            // auto-maintain: تاکسونومی شهر در WP بدون اطلاع از استان والد
            // سینک می‌شود (state و city دو taxonomy جدا هستند). هر سفارش
            // اما هم province_id و هم city_id درست را می‌داند، پس اگر شهر
            // هنوز province_id ندارد، آن را از سفارش می‌نویسیم. تنها در
            // صورت خالی بودن — مقادیر دستی ادمین بازنویسی نمی‌شوند.
            if ($order->province_id && $order->city_id) {
                City::where('id', $order->city_id)
                    ->whereNull('province_id')
                    ->update(['province_id' => $order->province_id]);
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
                'customer not synced yet (wp_id='.$data['customer_wp_id'].')'
            );
        }

        $payload = [
            'customer_id' => $customer->id,
            'subscription' => $data['subscription'] ?? null,
            'introduction' => $data['introduction'] ?? null,
            'order_type' => $data['order_type'] ?? null,

            // FK resolves (با fallback به null اگر مرجع نبود).
            // technician_wp_id خام را هم ذخیره می‌کنیم تا اگر تکنسین
            // هنوز sync نشده باشد، بعد از sync تکنسین‌ها بتوان با
            // backfill این سفارش‌های یتیم را وصل کرد.
            'technician_id' => $syncTechId = $this->resolveId(Technician::class, $data['technician_wp_id'] ?? null),
            // سفارشی که با تکنسین وارد می‌شود، از همین لحظه «تخصیص‌یافته» است.
            'assigned_at' => $syncTechId ? now() : null,
            'technician_wp_id' => isset($data['technician_wp_id']) && (int) $data['technician_wp_id'] > 0
                ? (int) $data['technician_wp_id']
                : null,
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
            // problem_title همان objection است؛ اگر آرایه باشد، با ، فارسی join.
            'problem_title' => $this->joinObjections($data['objection'] ?? null),
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

            // logging — اگر آرایه فرستاده شده، به json تبدیل کن. اگر
            // string بود، همان را به عنوان متن خام نگه می‌داریم (می‌تواند
            // JSON یا serialize PHP باشد — accessor مدل هر دو را parse
            // می‌کند).
            'order_description_content' => $this->encodeLogField($data['order_description_content'] ?? null),
            'order_note_content' => $this->encodeLogField($data['order_note_content'] ?? null),
            'log_return' => $this->encodeLogField($data['log_return'] ?? null),

            // زمان‌بندی — اولویت با visit_scheduled_at مستقیم، سپس
            // ترکیب visit_date + visit_time که پلاگین از کلیدهای
            // متنوع WP استخراج می‌کند.
            'visit_scheduled_at' => $this->buildVisitScheduledAt($data),
        ];

        // DEBUG: اگر پلاگین لیست کامل متاها را فرستاده، در لاگ ذخیره
        // کنیم تا کلید واقعی زمان مراجعه قابل کشف باشد. فقط برای
        // سفارش‌هایی که visit_scheduled_at هنوز null است (برای کاهش نویز).
        if (! empty($data['_debug_all_meta']) && ! $payload['visit_scheduled_at']) {
            \Illuminate\Support\Facades\Log::info('crm.sync.order.meta_dump', [
                'wp_id' => $data['wp_id'] ?? null,
                'meta' => $data['_debug_all_meta'],
            ]);
        }

        // final_price سنتی لاراول از price_customer (جمع کل صورت حساب) پر
        // می‌شود — این چیزی است که مشتری پرداخت کرده و در dashboardها/
        // گزارش‌ها به‌عنوان «مبلغ نهایی» نمایش داده می‌شود. در WP،
        // total_invoice همان «مانده» (پس از کسر هزینه‌ها) است و برای
        // محاسبه سهم تکنسین/شرکت کاربرد دارد، نه به‌عنوان مبلغ نهایی.
        if (isset($data['price_customer']) && $data['price_customer'] !== null) {
            $payload['final_price'] = (int) $data['price_customer'];
        } elseif (isset($data['total_invoice']) && $data['total_invoice'] !== null) {
            $payload['final_price'] = (int) $data['total_invoice'];
        }

        return $payload;
    }

    /**
     * parse امن یک تاریخ WP (post_date فرمت "YYYY-MM-DD HH:MM:SS"). در
     * صورت رشتهٔ خالی، '0000-00-00...' یا parse ناموفق null برمی‌گرداند
     * تا فال‌بک به Eloquent timestamp انجام شود.
     */
    protected function parseWpDate(?string $value): ?\Carbon\Carbon
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-')) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * تبدیل visit_date + visit_time (یا visit_scheduled_at مستقیم) به یک
     * datetime استاندارد قابل ذخیره. ورودی‌ها می‌توانند جلالی، میلادی،
     * با timezone یا بدون آن باشند — هر کدام را parse می‌کنیم.
     */
    protected function buildVisitScheduledAt(array $data): ?string
    {
        // ۱) اگر visit_scheduled_at کامل ارسال شده، همان را استفاده کن.
        if (! empty($data['visit_scheduled_at'])) {
            $parsed = $this->parseWpDate((string) $data['visit_scheduled_at']);
            if ($parsed) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        // ۲) ترکیب visit_date + visit_time
        $date = trim((string) ($data['visit_date'] ?? ''));
        $time = trim((string) ($data['visit_time'] ?? ''));
        if ($date === '') {
            return null;
        }

        // تاریخ ممکن است شمسی (مثل 1404/02/20) یا میلادی (2025-05-10) باشد.
        $gregorianDate = $this->normalizeDate($date);
        if (! $gregorianDate) {
            return null;
        }

        // ساعت ممکن است '09:00:00'، '9:00'، '9' یا حتی '۹' (Persian digits) باشد.
        $normalizedTime = $this->normalizeTime($time);

        return $gregorianDate.' '.$normalizedTime;
    }

    /** نرمال‌سازی رشتهٔ تاریخ به YYYY-MM-DD میلادی. */
    protected function normalizeDate(string $date): ?string
    {
        // ارقام فارسی/عربی → لاتین
        $date = strtr($date, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $date = str_replace(['/', '\\', '.'], '-', $date);
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
            return null;
        }
        $year = (int) $m[1];
        $mo = (int) $m[2];
        $day = (int) $m[3];

        // اگر سال < 1700، شمسی است — به میلادی تبدیل کن.
        if ($year < 1700 && class_exists(\Morilog\Jalali\CalendarUtils::class)) {
            try {
                [$gy, $gm, $gd] = \Morilog\Jalali\CalendarUtils::toGregorian($year, $mo, $day);

                return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return sprintf('%04d-%02d-%02d', $year, $mo, $day);
    }

    /** نرمال‌سازی رشتهٔ ساعت به HH:MM:SS. خالی → 09:00:00 (پیش‌فرض). */
    protected function normalizeTime(string $time): string
    {
        $time = strtr($time, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        if ($time === '') {
            return '09:00:00';
        }
        // اگر بازهٔ متنی است (مثلاً '9 تا 12 ظهر')، ساعت اول را بگیر.
        if (preg_match('/(\d{1,2})/', $time, $m)) {
            $h = max(0, min(23, (int) $m[1]));
            // اگر دقیقه/ثانیه داشت، نگه دار.
            if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $time, $hm)) {
                return sprintf('%02d:%02d:%02d', (int) $hm[1], (int) $hm[2], (int) ($hm[3] ?? 0));
            }

            return sprintf('%02d:00:00', $h);
        }

        return '09:00:00';
    }

    /**
     * objection در WP می‌تواند آرایهٔ چند عنوانی، رشتهٔ تک‌عنوانی، یا null
     * باشد. خروجی همیشه string قابل ذخیره در ستون string problem_title
     * — آرایه با ، فارسی join می‌شود.
     */
    protected function joinObjections($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $items = array_filter(array_map(
                fn ($v) => is_string($v) ? trim($v) : null,
                $value
            ), fn ($v) => $v !== null && $v !== '');

            return empty($items) ? null : implode('، ', $items);
        }
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        return null;
    }

    /**
     * فیلدهای لاگ WP می‌توانند به‌صورت آرایه (پاسخ get_post_meta unserialized)
     * یا string (serialize خام/JSON) از پلاگین برسند. در دیتابیس همیشه
     * به‌صورت JSON ذخیره می‌کنیم تا accessor مدل بتواند مستقل از منبع
     * parse کند.
     */
    protected function encodeLogField($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return empty($value) ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return null;
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
