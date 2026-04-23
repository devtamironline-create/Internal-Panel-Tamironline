<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\HappyCallQuestion;

class HappyCallQuestionController extends Controller
{
    public function index()
    {
        $customerQuestions = HappyCallQuestion::forAudience('customer')->ordered()->get();
        $technicianQuestions = HappyCallQuestion::forAudience('technician')->ordered()->get();

        return view('crm::happycall.questions.index', compact('customerQuestions', 'technicianQuestions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'audience' => 'required|in:customer,technician',
            'question_text' => 'required|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        HappyCallQuestion::create([
            'audience' => $validated['audience'],
            'question_text' => $validated['question_text'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return back()->with('success', 'سوال اضافه شد.');
    }

    public function update(Request $request, HappyCallQuestion $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $question->update([
            'question_text' => $validated['question_text'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'سوال ویرایش شد.');
    }

    public function destroy(HappyCallQuestion $question)
    {
        $question->delete();

        return back()->with('success', 'سوال حذف شد.');
    }
}
