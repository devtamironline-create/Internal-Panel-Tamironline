<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerAddress;
use Modules\CustomerApp\Http\Resources\AddressResource;

/**
 * CRUD آدرس‌های مشتری برای اپ موبایل.
 *
 * تمام عملیات scoped روی $request->user() — کاربر فقط آدرس‌های خودش را
 * می‌بیند/ویرایش می‌کند. هر کاربر باید Customer باشد (auth:sanctum + ماژول
 * Identity تضمین می‌کند).
 *
 * is_default منطق سرویس‌لایر: اگر آدرس جدید/ویرایش‌شده is_default=true،
 * بقیه‌ی آدرس‌های همان کاربر به‌صورت atomic در یک transaction false می‌شوند.
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $rows = $customer->addresses()
            ->with(['province:id,name', 'city:id,name', 'district:id,name'])
            ->get();

        return response()->json([
            'data' => AddressResource::collection($rows),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $address = $this->find($customer->id, $id);

        $address->load(['province:id,name', 'city:id,name', 'district:id,name']);

        return response()->json([
            'data' => (new AddressResource($address))->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $data = $this->validateData($request);

        $address = DB::transaction(function () use ($customer, $data) {
            $address = $customer->addresses()->create($data);
            if (! empty($data['is_default'])) {
                $this->markDefault($customer->id, $address->id);
            }

            return $address->fresh(['province:id,name', 'city:id,name', 'district:id,name']);
        });

        return response()->json([
            'message' => 'آدرس ثبت شد.',
            'data' => (new AddressResource($address))->resolve(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $address = $this->find($customer->id, $id);
        $data = $this->validateData($request);

        DB::transaction(function () use ($address, $customer, $data) {
            $address->update($data);
            if (! empty($data['is_default'])) {
                $this->markDefault($customer->id, $address->id);
            }
        });

        $address->refresh()->load(['province:id,name', 'city:id,name', 'district:id,name']);

        return response()->json([
            'message' => 'آدرس به‌روز شد.',
            'data' => (new AddressResource($address))->resolve(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $address = $this->find($customer->id, $id);
        $wasDefault = $address->is_default;

        DB::transaction(function () use ($address, $customer, $wasDefault) {
            $address->delete();
            // اگر default را حذف کردیم، اولین آدرس باقیمانده default می‌شود
            if ($wasDefault) {
                $next = $customer->addresses()->orderBy('id')->first();
                $next?->forceFill(['is_default' => true])->save();
            }
        });

        return response()->json([
            'message' => 'آدرس حذف شد.',
            'data' => null,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    private function find(int $customerId, int $id): CustomerAddress
    {
        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->first();

        if (! $address) {
            abort(404, 'آدرس یافت نشد.');
        }

        return $address;
    }

    /**
     * فلوی جدید: استان از کاربر پرسیده نمی‌شود — از شهر تشخیص داده می‌شود.
     * اگر فرانت قدیمی به‌جای شهر اصلی، ردیف «منطقه» را در city_id بفرستد،
     * خودکار به district_id منتقل و city_id به شهر والد اصلاح می‌شود.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            // شهر اجباری — استان سمت سرور از شهر ست می‌شود
            'city_id' => ['required', 'integer', Rule::exists('crm_cities', 'id')->where('is_active', true)],
            'district_id' => ['nullable', 'integer', Rule::exists('crm_cities', 'id')->where('is_active', true)],
            'full_address' => 'required|string|min:10|max:500',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'postal_code' => 'nullable|string|size:10',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ], [
            'city_id.required' => 'انتخاب شهر الزامی است.',
            'latitude.required_with' => 'مختصات نقشه ناقص است.',
            'longitude.required_with' => 'مختصات نقشه ناقص است.',
        ]);

        $city = \Modules\CRM\Models\City::query()
            ->whereKey($data['city_id'])
            ->first(['id', 'province_id', 'parent_city_id']);

        // سازگاری با فرانت قدیمی: اگر city_id خودش یک «منطقه» است،
        // district = همان، city = شهر والد
        if ($city && $city->parent_city_id !== null) {
            $data['district_id'] = $city->id;
            $data['city_id'] = (int) $city->parent_city_id;
            $city = \Modules\CRM\Models\City::query()
                ->whereKey($data['city_id'])
                ->first(['id', 'province_id', 'parent_city_id']);
        }

        if (! $city) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'city_id' => 'شهر انتخاب‌شده معتبر نیست.',
            ]);
        }

        // cross-check: منطقه باید فرزند همین شهر باشد
        if (! empty($data['district_id'])) {
            $district = \Modules\CRM\Models\City::query()
                ->whereKey($data['district_id'])
                ->first(['id', 'parent_city_id']);
            if (! $district || (int) $district->parent_city_id !== (int) $city->id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'district_id' => 'منطقه‌ی انتخاب‌شده به این شهر تعلق ندارد.',
                ]);
            }
        }

        // استان همیشه سمت سرور از شهر ست می‌شود — ورودی کلاینت نادیده گرفته می‌شود
        $data['province_id'] = (int) $city->province_id;

        return $data;
    }

    private function markDefault(int $customerId, int $addressId): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->where('id', '!=', $addressId)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        CustomerAddress::query()
            ->where('id', $addressId)
            ->update(['is_default' => true]);
    }
}
