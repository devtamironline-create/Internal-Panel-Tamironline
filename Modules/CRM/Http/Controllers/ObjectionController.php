<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Objection;

class ObjectionController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = $request->integer('device_id');

        $query = Objection::with('devices:id,name')->ordered();
        if ($deviceId > 0) {
            $query->forDevice($deviceId);
        }

        $items = $query->paginate(30)->withQueryString();
        // پنل ادمین همه‌ی دستگاه‌ها را نشان می‌دهد (فعال + غیرفعال)؛ فلگ is_active
        // فقط نمایشِ سایت را کنترل می‌کند.
        $devices = Device::query()->orderBy('name')->get(['id', 'name']);

        // پوششِ ایرادات: تعدادِ ایرادِ متصل به هر دستگاه. مبنای نمایشِ
        // «دستگاه‌هایی که هنوز ایرادی ندارند».
        $coverage = Device::query()
            ->withCount('objections')
            ->orderBy('objections_count')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $devicesWithout = $coverage->where('objections_count', 0)->values();

        return view('crm::objections.index', compact('items', 'devices', 'deviceId', 'coverage', 'devicesWithout'));
    }

    public function create(Request $request)
    {
        $devices = Device::query()->orderBy('name')->get(['id', 'name', 'is_active']);
        // پیش‌انتخابِ دستگاه وقتی از «افزودن ایراد برای این دستگاه» آمده‌ایم.
        $preDevice = $request->integer('device_id');
        $selectedDeviceIds = $preDevice > 0 ? [$preDevice] : [];

        return view('crm::objections.create', compact('devices', 'selectedDeviceIds'));
    }

    /**
     * ویرایشِ دستگاه‌محور: انتخاب یک دستگاه و تیک‌زدنِ ایرادهای متصل به آن
     * (برعکسِ فرمِ ایراد→دستگاه). راهِ سریعِ افزودن چند ایراد به یک دستگاه.
     */
    public function manageDevice(Device $device)
    {
        $objections = Objection::ordered()->get(['id', 'name', 'slug', 'is_active']);
        $attachedIds = $device->objections()->pluck('crm_objections.id')->map(fn ($i) => (int) $i)->all();
        $devices = Device::query()->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('crm::objections.device', compact('device', 'objections', 'attachedIds', 'devices'));
    }

    public function syncDevice(Request $request, Device $device)
    {
        $data = $request->validate([
            'objection_ids' => 'nullable|array',
            'objection_ids.*' => 'integer|exists:crm_objections,id',
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['objection_ids'] ?? [])));
        $device->objections()->sync($this->withOrder($ids));

        return redirect()->route('crm.objections.device', $device)
            ->with('success', 'ایرادهای دستگاه «'.$device->name.'» به‌روزرسانی شد.');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $deviceIds = $this->extractDevices($data);

        $obj = Objection::create($data);
        $obj->devices()->sync($this->withOrder($deviceIds));

        return redirect()->route('crm.objections.index')->with('success', 'ایراد اضافه شد.');
    }

    public function edit(Objection $objection)
    {
        $devices = Device::query()->orderBy('name')->get(['id', 'name', 'is_active']);
        $selectedDeviceIds = $objection->devices()->pluck('crm_devices.id')->all();

        return view('crm::objections.edit', [
            'item' => $objection,
            'devices' => $devices,
            'selectedDeviceIds' => $selectedDeviceIds,
        ]);
    }

    public function update(Request $request, Objection $objection)
    {
        $data = $this->validateData($request, $objection->id);
        $deviceIds = $this->extractDevices($data);

        $objection->update($data);
        $objection->devices()->sync($this->withOrder($deviceIds));

        return redirect()->route('crm.objections.index')->with('success', 'ایراد ویرایش شد.');
    }

    public function destroy(Objection $objection)
    {
        $objection->delete();

        return redirect()->route('crm.objections.index')->with('success', 'ایراد حذف شد.');
    }

    public function toggleActive(Objection $objection)
    {
        $objection->forceFill(['is_active' => ! $objection->is_active])->save();

        return back()->with('success', $objection->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'slug' => 'nullable|string|max:100|alpha_dash|unique:crm_objections,slug'.($id ? ','.$id : ''),
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:crm_devices,id',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data  passed by reference
     * @return array<int, int>
     */
    private function extractDevices(array &$data): array
    {
        $ids = array_values(array_map('intval', array_filter((array) ($data['device_ids'] ?? []))));
        unset($data['device_ids']);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{sort_order: int}>
     */
    private function withOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
