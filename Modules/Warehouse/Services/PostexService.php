<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class PostexService
{
    protected string $apiUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(WarehouseSetting::get('postex_api_url', 'https://api.postex.ir'), '/');
        $this->apiKey = WarehouseSetting::get('postex_api_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    protected function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function endpoint(string $path): string
    {
        return $this->apiUrl . '/api/v1/' . ltrim($path, '/');
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست (API Key وارد نشده)'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('wallet/balance'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $amount = $data['amount'] ?? null;

                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است.' . ($amount !== null ? ' موجودی: ' . number_format($amount) . ' ریال' : ''),
                    'data' => $data,
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    public function getWalletBalance(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('wallet/balance'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return [
                    'success' => true,
                    'data' => [
                        'balance' => $data['amount'] ?? 0,
                        'formatted' => number_format($data['amount'] ?? 0) . ' ریال',
                    ],
                ];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getWalletBalance error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserProfile(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('user/profile'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getUserProfile error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getProvinces(): array
    {
        $cached = Cache::get('postex_provinces');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('location/provinces'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                if (!empty($data)) {
                    Cache::put('postex_provinces', $data, 86400);
                    return $data;
                }
            }

            Log::warning('Postex getProvinces failed', ['response' => $response->body()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Postex getProvinces error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getCities(?int $provinceCode = null): array
    {
        $cacheKey = 'postex_cities_' . ($provinceCode ?? 'all');
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) return $cached;

        try {
            $url = $provinceCode
                ? $this->endpoint("location/province/{$provinceCode}/cities")
                : $this->endpoint('location/cities');

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($url);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                if (!empty($data)) {
                    Cache::put($cacheKey, $data, 86400);
                    return $data;
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Postex getCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function calculateShippingCost(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $payload = [
                'collection_type' => $data['collection_type'] ?? 'postex_drop_off',
                'from_city_code' => $data['from_city_code'] ?? 444,
                'parcels' => $data['parcels'] ?? [],
            ];

            if (!empty($data['courier'])) {
                $payload['courier'] = $data['courier'];
            }

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('shipping/price'), $payload);

            if ($response->successful()) {
                $result = $response->json() ?? [];
                return [
                    'success' => true,
                    'data' => $result,
                ];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex calculateShippingCost error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function trackShipment(string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("tracking/parcel/{$trackingCode}"));

            if ($response->successful()) {
                $result = $response->json() ?? [];
                return ['success' => true, 'data' => $result];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex trackShipment error', ['tracking_code' => $trackingCode, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * جستجوی کد شهر مقصد بر اساس نام شهر و استان
     */
    public function findCityCode(string $cityName, string $provinceName = ''): ?int
    {
        $cityName = trim($cityName);
        if (empty($cityName)) return null;

        // اگر استان داریم، اول از طریق استان پیدا کن
        if (!empty($provinceName)) {
            $provinces = $this->getProvinces();
            $provinceCode = null;
            foreach ($provinces as $prov) {
                $pName = $prov['name'] ?? $prov['title'] ?? '';
                if (str_contains($pName, $provinceName) || str_contains($provinceName, $pName)) {
                    $provinceCode = $prov['code'] ?? $prov['id'] ?? null;
                    break;
                }
            }
            if ($provinceCode) {
                $cities = $this->getCities($provinceCode);
                foreach ($cities as $city) {
                    $cName = $city['name'] ?? $city['title'] ?? '';
                    if (str_contains($cName, $cityName) || str_contains($cityName, $cName)) {
                        return (int)($city['code'] ?? $city['id'] ?? 0) ?: null;
                    }
                }
            }
        }

        // fallback: جستجو در همه شهرها
        $allCities = $this->getCities();
        foreach ($allCities as $city) {
            $cName = $city['name'] ?? $city['title'] ?? '';
            if (str_contains($cName, $cityName) || str_contains($cityName, $cName)) {
                return (int)($city['code'] ?? $city['id'] ?? 0) ?: null;
            }
        }

        return null;
    }

    /**
     * ثبت مرسوله در پستکس
     */
    public function createShipment(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'پستکس تنظیم نشده (API Key خالی)'];
        }

        $collectionType = WarehouseSetting::get('postex_collection_type', 'postex_drop_off');
        $fromCityCode   = (int) WarehouseSetting::get('postex_from_city_code', 444);

        $postcode = $this->normalizePostalCode($data['recipient_postal_code'] ?? '');
        if (empty($postcode) || strlen($postcode) !== 10) {
            return ['success' => false, 'message' => 'کد پستی نامعتبر است (باید ۱۰ رقم باشد): "' . ($postcode ?: 'خالی') . '"'];
        }

        $toCityCode = $data['to_city_code'] ?? null;

        $payload = [
            'collection_type' => $collectionType,
            'from_city_code'  => $fromCityCode,
            'parcels'         => [[
                'amount'               => (int)($data['value'] ?? 100000),
                'description'          => $data['description'] ?? ('سفارش ' . ($data['external_order_id'] ?? '')),
                'weight'               => (int)($data['weight'] ?? 500),
                'to_city_code'         => $toCityCode,
                'recipient_name'       => $data['recipient_name'] ?? '',
                'recipient_phone'      => $this->formatMobile($data['recipient_mobile'] ?? ''),
                'recipient_postal_code'=> $postcode,
                'recipient_address'    => $data['recipient_address'] ?? '',
            ]],
        ];

        Log::info('Postex createShipment payload', ['order' => $data['external_order_id'] ?? '', 'payload' => $payload]);

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('parcel/create'), $payload);

            $body = $response->json() ?? [];
            Log::info('Postex createShipment response', ['order' => $data['external_order_id'] ?? '', 'status' => $response->status(), 'body' => $body]);

            if ($response->successful() && !empty($body)) {
                // پستکس tracking number یا barcode برمی‌گردونه
                $parcel  = is_array($body['data'] ?? null) ? ($body['data'][0] ?? $body['data']) : ($body[0] ?? $body);
                $barcode = $parcel['tracking_number']
                    ?? $parcel['barcode']
                    ?? $parcel['tracking_code']
                    ?? ($body['tracking_number'] ?? null)
                    ?? ($body['barcode'] ?? null);

                if (!empty($barcode)) {
                    return ['success' => true, 'data' => ['barcode' => (string)$barcode, 'raw' => $parcel]];
                }

                return ['success' => false, 'message' => 'پستکس: بارکد در پاسخ نیامد — ' . json_encode($body, JSON_UNESCAPED_UNICODE)];
            }

            $errorMsg = $body['message'] ?? $body['error'] ?? $body['detail'] ?? $response->body();
            return ['success' => false, 'message' => 'پستکس: ' . $errorMsg];
        } catch (\Exception $e) {
            Log::error('Postex createShipment error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function formatMobile(?string $mobile): string
    {
        if (!$mobile) return '';
        $mobile = preg_replace('/\D/', '', $mobile);
        if (str_starts_with($mobile, '98') && strlen($mobile) == 12) {
            $mobile = '0' . substr($mobile, 2);
        } elseif (!str_starts_with($mobile, '0') && strlen($mobile) == 10) {
            $mobile = '0' . $mobile;
        }
        return $mobile;
    }

    protected function normalizePostalCode(?string $postalCode): string
    {
        if (!$postalCode) return '';
        $postalCode = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $postalCode
        );
        $postalCode = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $postalCode
        );
        $postalCode = preg_replace('/\D/', '', $postalCode);
        return $postalCode;
    }
}
