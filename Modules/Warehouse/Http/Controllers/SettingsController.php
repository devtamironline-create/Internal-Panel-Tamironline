<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Modules\Warehouse\Services\WooCommerceService;
use Modules\Warehouse\Services\AmadastService;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        $settings = [
            // WooCommerce settings
            'woocommerce_store_url' => Setting::get('woocommerce_store_url', ''),
            'woocommerce_consumer_key' => Setting::get('woocommerce_consumer_key', ''),
            'woocommerce_consumer_secret' => Setting::get('woocommerce_consumer_secret', ''),
            'woocommerce_webhook_secret' => Setting::get('woocommerce_webhook_secret', ''),
            'warehouse_auto_sync' => Setting::get('warehouse_auto_sync', false),
            'warehouse_sync_interval' => Setting::get('warehouse_sync_interval', 15),

            // Amadast settings
            'amadast_client_code' => Setting::get('amadast_client_code', ''),
            'amadast_enabled' => Setting::get('amadast_enabled', false),
            'amadast_sender_name' => Setting::get('amadast_sender_name', ''),
            'amadast_sender_mobile' => Setting::get('amadast_sender_mobile', ''),
            'amadast_warehouse_address' => Setting::get('amadast_warehouse_address', ''),
            'amadast_province_id' => Setting::get('amadast_province_id', ''),
            'amadast_city_id' => Setting::get('amadast_city_id', ''),
            'amadast_postal_code' => Setting::get('amadast_postal_code', ''),
            'amadast_default_city_id' => Setting::get('amadast_default_city_id', 360),
            'amadast_user_id' => Setting::get('amadast_user_id'),
            'amadast_store_id' => Setting::get('amadast_store_id'),
            'amadast_location_id' => Setting::get('amadast_location_id'),
        ];

        return view('warehouse::settings.index', compact('settings'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'woocommerce_store_url' => 'nullable|url',
            'woocommerce_consumer_key' => 'nullable|string|max:255',
            'woocommerce_consumer_secret' => 'nullable|string|max:255',
            'woocommerce_webhook_secret' => 'nullable|string|max:255',
            'warehouse_auto_sync' => 'boolean',
            'warehouse_sync_interval' => 'integer|min:5|max:1440',
        ]);

        Setting::set('woocommerce_store_url', $request->woocommerce_store_url);
        Setting::set('woocommerce_consumer_key', $request->woocommerce_consumer_key);
        Setting::set('woocommerce_consumer_secret', $request->woocommerce_consumer_secret);
        Setting::set('woocommerce_webhook_secret', $request->woocommerce_webhook_secret);
        Setting::set('warehouse_auto_sync', $request->boolean('warehouse_auto_sync'));
        Setting::set('warehouse_sync_interval', $request->warehouse_sync_interval);

        // Update .env file for immediate effect
        $this->updateEnvFile([
            'WOOCOMMERCE_STORE_URL' => $request->woocommerce_store_url,
            'WOOCOMMERCE_CONSUMER_KEY' => $request->woocommerce_consumer_key,
            'WOOCOMMERCE_CONSUMER_SECRET' => $request->woocommerce_consumer_secret,
            'WOOCOMMERCE_WEBHOOK_SECRET' => $request->woocommerce_webhook_secret,
        ]);

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'تنظیمات با موفقیت ذخیره شد.');
    }

    /**
     * Update Amadast settings
     */
    public function updateAmadast(Request $request)
    {
        $request->validate([
            'amadast_client_code' => 'required|string|max:255',
            'amadast_enabled' => 'boolean',
            'amadast_default_city_id' => 'nullable|integer',
        ]);

        Setting::set('amadast_client_code', $request->amadast_client_code);
        Setting::set('amadast_enabled', $request->boolean('amadast_enabled'));
        Setting::set('amadast_default_city_id', $request->amadast_default_city_id ?? 360);

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'تنظیمات آمادست ذخیره شد.');
    }

    /**
     * Setup Amadast (create user, location, store)
     */
    public function setupAmadast(Request $request)
    {
        $request->validate([
            'sender_name' => 'required|string|max:255',
            'sender_mobile' => 'required|string|regex:/^09[0-9]{9}$/',
            'warehouse_title' => 'required|string|max:255',
            'warehouse_address' => 'required|string|max:500',
            'province_id' => 'required|integer',
            'city_id' => 'required|integer',
            'postal_code' => 'required|string|size:10',
            'store_title' => 'required|string|max:255',
        ]);

        $amadastService = app(AmadastService::class);

        $result = $amadastService->setup([
            'sender_name' => $request->sender_name,
            'sender_mobile' => $request->sender_mobile,
            'warehouse_title' => $request->warehouse_title,
            'warehouse_address' => $request->warehouse_address,
            'province_id' => $request->province_id,
            'city_id' => $request->city_id,
            'postal_code' => $request->postal_code,
            'store_title' => $request->store_title,
        ]);

        if ($result['success'] ?? false) {
            Setting::set('amadast_enabled', true);
            return redirect()->route('warehouse.settings.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('warehouse.settings.index')
            ->with('error', $result['message'] ?? 'خطا در تنظیم آمادست');
    }

    /**
     * Test WooCommerce connection
     */
    public function testConnection()
    {
        $wooService = app(WooCommerceService::class);
        $result = $wooService->testConnection();

        return response()->json($result);
    }

    /**
     * Test Amadast connection
     */
    public function testAmadastConnection()
    {
        $amadastService = app(AmadastService::class);
        $result = $amadastService->testConnection();

        return response()->json($result);
    }

    /**
     * Get Amadast provinces
     */
    public function getAmadastProvinces()
    {
        $amadastService = app(AmadastService::class);
        $provinces = $amadastService->getProvinces();

        return response()->json(['success' => true, 'data' => $provinces]);
    }

    /**
     * Get Amadast cities by province
     */
    public function getAmadastCities(Request $request)
    {
        $request->validate(['province_id' => 'required|integer']);

        $amadastService = app(AmadastService::class);
        $cities = $amadastService->getCities($request->province_id);

        return response()->json(['success' => true, 'data' => $cities]);
    }

    /**
     * Update .env file with new values
     */
    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $value = $value ?? '';
            $escapedValue = str_contains($value, ' ') ? "\"$value\"" : $value;

            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$escapedValue}",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
