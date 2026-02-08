<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class AmadestService
{
    protected ?string $apiKey;
    protected ?string $apiUrl;

    public function __construct()
    {
        $this->apiKey = WarehouseSetting::get('amadest_api_key');
        $this->apiUrl = rtrim(WarehouseSetting::get('amadest_api_url', 'https://api.amadest.com'), '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->apiUrl . '/api/v1/profile');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'اتصال به آمادست برقرار است.',
                    'data' => $response->json(),
                ];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('Amadest connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    public function createShipment(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده.'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($this->apiUrl . '/api/v1/shipments', $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'مرسوله با موفقیت ثبت شد.',
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'خطا: ' . ($response->json('message') ?? $response->status()),
            ];
        } catch (\Exception $e) {
            Log::error('Amadest create shipment failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }

    public function trackShipment(string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->apiUrl . '/api/v1/tracking/' . $trackingCode);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('Amadest tracking failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }
}
