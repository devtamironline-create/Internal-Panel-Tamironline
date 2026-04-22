<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Province;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->toString();
        $provinceId = $request->integer('province_id');
        $status = $request->string('status')->toString();

        $customers = Customer::with(['province', 'city'])
            ->search($search)
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::customers.index', compact('customers', 'provinces', 'search', 'provinceId', 'status'));
    }

    public function create()
    {
        $provinces = Province::ordered()->get(['id', 'name']);
        $cities = collect();

        return view('crm::customers.create', compact('provinces', 'cities'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['registered_via'] = $validated['registered_via'] ?? 'panel';

        Customer::create($validated);

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری اضافه شد.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['province', 'city']);

        return view('crm::customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $provinces = Province::ordered()->get(['id', 'name']);
        $cities = $customer->province_id
            ? City::where('province_id', $customer->province_id)->ordered()->get(['id', 'name'])
            : collect();

        return view('crm::customers.edit', compact('customer', 'provinces', 'cities'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validateCustomer($request, $customer->id);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $customer->update($validated);

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری ویرایش شد.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری حذف شد.');
    }

    // Endpoint کمکی برای لود شهرهای یک استان (Ajax در فرم مشتری)
    public function citiesOfProvince(Province $province)
    {
        return response()->json(
            $province->cities()->ordered()->get(['id', 'name'])
        );
    }

    protected function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        $mobileRule = 'required|string|max:20|unique:crm_customers,mobile';
        if ($ignoreId) {
            $mobileRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'mobile' => $mobileRule,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'national_code' => 'nullable|string|max:20',
            'province_id' => 'nullable|exists:crm_provinces,id',
            'city_id' => 'nullable|exists:crm_cities,id',
            'address' => 'nullable|string|max:2000',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
            'registered_via' => 'nullable|in:app,panel,import',
        ]);
    }
}
