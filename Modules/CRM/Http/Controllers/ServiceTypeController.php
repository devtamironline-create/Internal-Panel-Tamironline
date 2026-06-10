<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\ServiceType;

class ServiceTypeController extends Controller
{
    public function index()
    {
        $items = ServiceType::ordered()->paginate(30);

        return view('crm::service-types.index', compact('items'));
    }

    public function create()
    {
        return view('crm::service-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        ServiceType::create($data);

        return redirect()->route('crm.service-types.index')->with('success', 'نوع خدمت اضافه شد.');
    }

    public function edit(ServiceType $serviceType)
    {
        return view('crm::service-types.edit', ['item' => $serviceType]);
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $data = $this->validateData($request, $serviceType->id);

        $serviceType->update($data);

        return redirect()->route('crm.service-types.index')->with('success', 'نوع خدمت ویرایش شد.');
    }

    public function destroy(ServiceType $serviceType)
    {
        // slugهای اصلی hardcoded (repair/service/install) را قفل می‌کنیم
        if (in_array($serviceType->slug, ['repair', 'service', 'install'], true)) {
            return back()->with('error', 'این نوع پایه است و قابل حذف نیست. به‌جایش غیرفعال کنید.');
        }
        $serviceType->delete();

        return redirect()->route('crm.service-types.index')->with('success', 'نوع خدمت حذف شد.');
    }

    public function toggleActive(ServiceType $serviceType)
    {
        $serviceType->forceFill(['is_active' => ! $serviceType->is_active])->save();

        return back()->with('success', $serviceType->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'slug' => 'nullable|string|max:60|alpha_dash|unique:crm_service_types,slug'.($id ? ','.$id : ''),
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
