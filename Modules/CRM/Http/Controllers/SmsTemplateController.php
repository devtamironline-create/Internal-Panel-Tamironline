<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\SmsTemplate;

class SmsTemplateController extends Controller
{
    public function index()
    {
        $templates = SmsTemplate::orderBy('recipient')->orderBy('trigger_key')->get();

        return view('crm::sms.templates.index', compact('templates'));
    }

    public function edit(SmsTemplate $template)
    {
        return view('crm::sms.templates.edit', [
            'template' => $template,
            'variables' => $this->availableVariables(),
        ]);
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $template->update($validated);

        return redirect()->route('crm.sms.templates.index')->with('success', 'قالب ذخیره شد.');
    }

    public function toggle(SmsTemplate $template)
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active ? 'قالب فعال شد.' : 'قالب غیرفعال شد.');
    }

    public function logs(Request $request)
    {
        $logs = SmsLog::with(['order', 'sender'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('order_id'), fn ($q, $id) => $q->where('order_id', $id))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('crm::sms.logs', [
            'logs' => $logs,
            'filterStatus' => $request->string('status')->toString(),
        ]);
    }

    protected function availableVariables(): array
    {
        return [
            '{customer_name}' => 'نام مشتری',
            '{customer_mobile}' => 'موبایل مشتری',
            '{order_code}' => 'کد سفارش',
            '{technician_name}' => 'نام تکنسین',
            '{technician_mobile}' => 'موبایل تکنسین',
            '{status}' => 'وضعیت فعلی سفارش',
            '{shop_name}' => 'نام فروشگاه (از تنظیمات)',
            '{visit_date}' => 'زمان مراجعه',
        ];
    }
}
