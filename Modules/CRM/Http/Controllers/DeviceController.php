<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CRM\Models\Device;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::query();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('active'))) {
            $query->where('is_active', $request->boolean('active'));
        }
        if (! is_null($request->query('featured'))) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $devices = $query->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('crm::devices.index', compact('devices'));
    }

    public function create()
    {
        return view('crm::devices.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['thumbnail'] = $this->handleThumbnail($request, null);

        $this->applyDefaults($validated, true);

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
        $validated = $this->validateRequest($request, $device->id);
        $validated['thumbnail'] = $this->handleThumbnail($request, $device);

        $this->applyDefaults($validated, false);

        $device->update($validated);

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه ویرایش شد.');
    }

    public function destroy(Device $device)
    {
        $this->deleteStoredImage($device->thumbnail);
        $device->delete();

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه حذف شد.');
    }

    public function toggle(Request $request, Device $device, string $flag)
    {
        if (! in_array($flag, ['is_active', 'is_featured'], true)) {
            abort(404);
        }
        $device->{$flag} = ! $device->{$flag};
        $device->save();

        return back()->with('success', 'به‌روز شد.');
    }

    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'nullable|string|max:255|unique:crm_devices,slug' . ($id ? ',' . $id : ''),
            'icon'           => 'nullable|string|max:60',
            'tone'           => 'nullable|string|max:30',
            'thumbnail'      => 'nullable|string|max:500',
            'thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
        ]);
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug']        = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order']  = $validated['sort_order'] ?? 0;
        $validated['is_active']   = (bool) ($validated['is_active']   ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['thumbnail_file']);
    }

    private function handleThumbnail(Request $request, ?Device $device): ?string
    {
        $cleared = $request->input('thumbnail_cleared') === '1';

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('site/devices', 'public');
            if ($device) {
                $this->deleteStoredImage($device->thumbnail);
            }
            return $path;
        }

        if ($cleared) {
            if ($device) {
                $this->deleteStoredImage($device->thumbnail);
            }
            return null;
        }

        $url = trim((string) $request->input('thumbnail', ''));
        return $url === '' ? ($device?->thumbnail) : $url;
    }

    private function deleteStoredImage(?string $value): void
    {
        if (! $value) {
            return;
        }
        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return;
        }
        Storage::disk('public')->delete($value);
    }
}
