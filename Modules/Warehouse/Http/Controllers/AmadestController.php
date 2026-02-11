<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\AmadestService;

class AmadestController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $settings = [
            'api_url' => WarehouseSetting::get('amadest_api_url', 'https://shop-integration.amadast.com'),
            'client_code' => WarehouseSetting::get('amadest_client_code'),
            'user_id' => WarehouseSetting::get('amadest_user_id'),
            'sender_name' => WarehouseSetting::get('amadest_sender_name'),
            'sender_mobile' => WarehouseSetting::get('amadest_sender_mobile'),
            'warehouse_address' => WarehouseSetting::get('amadest_warehouse_address'),
            'token_expires_at' => WarehouseSetting::get('amadest_token_expires_at'),
            'has_token' => !empty(WarehouseSetting::get('amadest_api_key')),
            'store_id' => WarehouseSetting::get('amadest_store_id', '84085'),
        ];

        return view('warehouse::amadest.index', compact('settings'));
    }

    /**
     * ذخیره تنظیمات API (کد کلاینت و URL)
     */
    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_url' => 'nullable|url|max:500',
            'client_code' => 'nullable|string|max:500',
            'api_key' => 'nullable|string|max:5000',
            'user_id' => 'nullable|string|max:50',
        ]);

        if (!empty($validated['api_url'])) {
            WarehouseSetting::set('amadest_api_url', $validated['api_url']);
        }
        // X-Client-Code اختیاریه
        if (isset($validated['client_code'])) {
            WarehouseSetting::set('amadest_client_code', $validated['client_code']);
        }
        // ذخیره توکن دستی اگه وارد شده باشه
        if (!empty($validated['api_key'])) {
            WarehouseSetting::set('amadest_api_key', $validated['api_key']);
            // توکن دستی رو ۱ سال معتبر بذار (شاید long-lived باشه)
            WarehouseSetting::set('amadest_token_expires_at', (string) (time() + 86400 * 365));
            \Illuminate\Support\Facades\Cache::put('amadest_access_token', $validated['api_key'], 86400 * 365);
        }
        if (!empty($validated['user_id'])) {
            WarehouseSetting::set('amadest_user_id', $validated['user_id']);
        }
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تنظیمات ذخیره شد.']);
        }

        return redirect()->route('warehouse.amadest.index')
            ->with('success', 'تنظیمات آمادست ذخیره شد.');
    }

    /**
     * ساخت کاربر در آمادست و دریافت توکن
     */
    public function registerUser(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'national_code' => 'nullable|string|max:10',
        ]);

        $service = new AmadestService();

        // مرحله ۱: ساخت کاربر
        $userResult = $service->createUser(
            $validated['full_name'],
            $validated['mobile'],
            $validated['national_code'] ?? null
        );

        if (!($userResult['success'] ?? false)) {
            return response()->json($userResult);
        }

        $userId = $userResult['data']['id'] ?? null;
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'شناسه کاربر از آمادست دریافت نشد.']);
        }

        // مرحله ۲: دریافت توکن
        $tokenResult = $service->fetchToken($userId);

        if ($tokenResult['success'] ?? false) {
            return response()->json([
                'success' => true,
                'message' => 'کاربر ساخته شد و توکن دریافت شد.',
                'data' => [
                    'user_id' => $userId,
                    'token_received' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'کاربر ساخته شد ولی دریافت توکن ناموفق بود: ' . ($tokenResult['message'] ?? ''),
            'data' => ['user_id' => $userId, 'token_received' => false],
        ]);
    }

    /**
     * تمدید/دریافت مجدد توکن
     */
    public function refreshToken()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new AmadestService();
        $result = $service->fetchToken();

        return response()->json($result);
    }

    /**
     * لیست فروشگاه‌ها از آمادست
     */
    public function getStores()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new AmadestService();
        $result = $service->getStores();

        return response()->json($result);
    }

    /**
     * ذخیره store_id انتخابی
     */
    public function selectStore(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'store_id' => 'required|integer',
        ]);

        WarehouseSetting::set('amadest_store_id', (string) $validated['store_id']);

        return response()->json(['success' => true, 'message' => 'فروشگاه انتخاب شد.']);
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new AmadestService();
        $result = $service->testConnection();

        return response()->json($result);
    }

    public function track(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'tracking_code' => 'required|string|max:100',
        ]);

        $input = trim($request->tracking_code);
        $service = new AmadestService();

        // اگه شماره موبایل هست مستقیم جستجو کن
        if (preg_match('/^09\d{9}$/', $input)) {
            $result = $service->searchOrders([$input]);
            return response()->json($result);
        }

        // اگه شماره سفارش هست - اول از دیتابیس محلی موبایل رو پیدا کن
        $order = \Modules\Warehouse\Models\WarehouseOrder::where('order_number', 'LIKE', '%' . $input . '%')
            ->orWhere('order_number', $input)
            ->orWhere('order_number', 'WC-' . $input)
            ->first();

        if ($order && $order->customer_mobile) {
            $result = $service->searchOrders([$order->customer_mobile]);
            // فیلتر کن فقط همین سفارش رو نشون بده
            if (($result['success'] ?? false) && !empty($result['data'])) {
                $externalId = (int) preg_replace('/\D/', '', $order->order_number);
                $filtered = collect($result['data'])->filter(function ($item) use ($externalId) {
                    return ($item['external_order_id'] ?? null) == $externalId;
                })->values()->toArray();
                if (!empty($filtered)) {
                    $result['data'] = $filtered;
                }
            }
            return response()->json($result);
        }

        // fallback - مستقیم تو آمادست جستجو کن
        $result = $service->trackShipment($input);
        return response()->json($result);
    }

    public function saveSenderInfo(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'sender_name' => 'required|string|max:255',
            'sender_mobile' => 'required|string|max:20',
            'warehouse_address' => 'nullable|string|max:1000',
        ]);

        WarehouseSetting::set('amadest_sender_name', $validated['sender_name']);
        WarehouseSetting::set('amadest_sender_mobile', $validated['sender_mobile']);
        if (!empty($validated['warehouse_address'])) {
            WarehouseSetting::set('amadest_warehouse_address', $validated['warehouse_address']);
        }

        return redirect()->route('warehouse.amadest.index')
            ->with('success', 'اطلاعات فرستنده ذخیره شد.');
    }
}
