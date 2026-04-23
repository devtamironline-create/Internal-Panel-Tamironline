<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\HappyCallQuestion;
use Modules\CRM\Models\HappyCallResponse;
use Modules\CRM\Models\Order;

class HappyCallResponseController extends Controller
{
    public function index(Request $request)
    {
        $audience = $request->string('audience')->toString();
        $rating = $request->integer('rating');

        $responses = HappyCallResponse::with(['order.customer', 'caller'])
            ->when($audience, fn ($q) => $q->where('audience', $audience))
            ->when($rating, fn ($q, $r) => $q->where('overall_rating', $r))
            ->latest('called_at')
            ->paginate(30)
            ->withQueryString();

        return view('crm::happycall.responses.index', compact('responses', 'audience', 'rating'));
    }

    public function create(Order $order, Request $request)
    {
        $audience = $request->string('audience', 'customer')->toString();
        if (! in_array($audience, ['customer', 'technician'], true)) {
            $audience = 'customer';
        }

        $questions = HappyCallQuestion::forAudience($audience)->active()->ordered()->get();

        return view('crm::happycall.responses.create', compact('order', 'audience', 'questions'));
    }

    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'audience' => 'required|in:customer,technician',
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.question_text' => 'required|string|max:500',
            'answers.*.answer' => 'nullable|string|max:2000',
            'overall_rating' => 'nullable|integer|min:1|max:5',
            'note' => 'nullable|string|max:2000',
        ]);

        HappyCallResponse::create([
            'order_id' => $order->id,
            'audience' => $validated['audience'],
            'answers' => $validated['answers'] ?? [],
            'overall_rating' => $validated['overall_rating'] ?? null,
            'note' => $validated['note'] ?? null,
            'called_by' => auth()->id(),
            'called_at' => now(),
        ]);

        return redirect()->route('crm.orders.show', $order)->with('success', 'پاسخ HappyCall ثبت شد.');
    }

    public function show(HappyCallResponse $response)
    {
        $response->load(['order.customer', 'caller']);

        return view('crm::happycall.responses.show', compact('response'));
    }

    public function destroy(HappyCallResponse $response)
    {
        $orderId = $response->order_id;
        $response->delete();

        return redirect()->route('crm.orders.show', $orderId)->with('success', 'پاسخ حذف شد.');
    }
}
