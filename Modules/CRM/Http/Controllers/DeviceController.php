<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\Device;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::ordered()->paginate(20);

        return view('crm::devices.index', compact('devices'));
    }

    public function create()
    {
        return view('crm::devices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_devices,slug',
            'icon' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        Device::create($validated);

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه با موفقیت اضافه شد.');
    }

    public function edit(Device $device)
    {
        return view('crm::devices.edit', compact('device'));
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_devices,slug,' . $device->id,
            'icon' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $device->update($validated);

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه ویرایش شد.');
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه حذف شد.');
    }
}
