<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Modules\Warehouse\Services\WooCommerceService;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        $settings = [
            'woocommerce_store_url' => Setting::get('woocommerce_store_url', ''),
            'woocommerce_consumer_key' => Setting::get('woocommerce_consumer_key', ''),
            'woocommerce_consumer_secret' => Setting::get('woocommerce_consumer_secret', ''),
            'woocommerce_webhook_secret' => Setting::get('woocommerce_webhook_secret', ''),
            'warehouse_auto_sync' => Setting::get('warehouse_auto_sync', false),
            'warehouse_sync_interval' => Setting::get('warehouse_sync_interval', 15),
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
     * Test WooCommerce connection
     */
    public function testConnection()
    {
        $wooService = app(WooCommerceService::class);
        $result = $wooService->testConnection();

        return response()->json($result);
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
