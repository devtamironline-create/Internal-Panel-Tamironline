<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Device;
use Modules\CRM\Support\HtmlSanitizer;

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
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_devices,slug'.($id ? ','.$id : ''),
            'icon' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:30',
            'thumbnail' => 'nullable|string|max:500',
            'thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // CMS-override fields
            'short_name' => 'nullable|string|max:80',
            'subtitle' => 'nullable|string|max:500',
            'eyebrow' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:10000',
            'service_name' => 'nullable|string|max:160',
            'technician_name' => 'nullable|string|max:160',
            'starting_price' => 'nullable|integer|min:0',
            'accent' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'bg' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'warranty_text' => 'nullable|string|max:3000',
            'support_info' => 'nullable|string|max:3000',

            'issues' => 'nullable|array',
            'issues.*.title' => 'nullable|string|max:160',
            'issues.*.description' => 'nullable|string|max:1000',

            'faq' => 'nullable|array',
            'faq.*.question' => 'nullable|string|max:300',
            'faq.*.answer' => 'nullable|string|max:5000',

            'service_steps' => 'nullable|array',
            'service_steps.*.title' => 'nullable|string|max:160',
            'service_steps.*.description' => 'nullable|string|max:1000',
            'service_steps.*.icon' => 'nullable|string|max:60',
        ]);
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $this->assertEnglishSlug($validated['slug']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['thumbnail_file']);

        // پاک‌سازی HTML خروجی TinyMCE قبل از ذخیره (allowlist tags + attrs)
        if (array_key_exists('description', $validated)) {
            $validated['description'] = HtmlSanitizer::clean($validated['description']);
        }

        // پاکسازی ردیف‌های خالی repeater
        foreach (['issues', 'faq', 'service_steps'] as $key) {
            if (isset($validated[$key]) && is_array($validated[$key])) {
                $validated[$key] = $this->cleanRepeater($validated[$key]);
                if (empty($validated[$key])) {
                    $validated[$key] = null;
                }
            }
        }
    }

    /**
     * تضمین اینکه slug فقط با English kebab-case است. URLهای /devices/{slug}
     * در فرانت Next.js مسیرسازی می‌شوند و باید ASCII باشند — slug فارسی
     * مشکل encoding و SEO ایجاد می‌کند.
     */
    private function assertEnglishSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد. اگر نام دستگاه فارسی است، حتماً یک اسلاگ انگلیسی وارد کنید (مثل washing-machine).',
            ]);
        }
    }

    /**
     * حذف ردیف‌های خالی repeater (ردیف‌هایی که هیچ مقدار غیرخالی ندارند).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function cleanRepeater(array $rows): array
    {
        $cleaned = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $hasValue = false;
            foreach ($row as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if ($hasValue) {
                $cleaned[] = array_map(
                    fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v,
                    $row
                );
            }
        }

        return array_values($cleaned);
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
