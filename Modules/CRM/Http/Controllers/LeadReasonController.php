<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\LeadReason;

class LeadReasonController extends Controller
{
    public function index()
    {
        $reasons = LeadReason::ordered()->withCount('leads')->get();

        return view('crm::lead-reasons.index', compact('reasons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:120|unique:crm_lead_reasons,name',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ], [
            'name.required' => 'عنوان دلیل الزامی است.',
            'name.unique'   => 'این عنوان قبلاً ثبت شده.',
        ]);

        LeadReason::create([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'دلیل جدید اضافه شد.');
    }

    public function update(Request $request, LeadReason $leadReason)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:120|unique:crm_lead_reasons,name,' . $leadReason->id,
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active'  => 'nullable|boolean',
        ]);

        $leadReason->update([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'دلیل به‌روزرسانی شد.');
    }

    public function destroy(LeadReason $leadReason)
    {
        if ($leadReason->leads()->exists()) {
            return back()->with('error', 'این دلیل در لیدهای ثبت‌شده استفاده شده — به‌جای حذف، آن را غیرفعال کنید.');
        }

        $leadReason->delete();

        return back()->with('success', 'دلیل حذف شد.');
    }
}
