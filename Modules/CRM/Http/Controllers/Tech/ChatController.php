<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Models\TechChatMessage;

/**
 * چت تکنسین با اپراتورِ تخصیص‌داده‌شده‌اش — سمت پنل تکنسین.
 */
class ChatController extends Controller
{
    public function index()
    {
        $tech = Auth::guard('tech')->user();
        $tech->loadMissing('operators:id,first_name,last_name');

        $messages = TechChatMessage::where('technician_id', $tech->id)
            ->orderBy('created_at')
            ->get();

        // پیام‌های اپراتور را به‌عنوان خوانده ثبت کن
        TechChatMessage::where('technician_id', $tech->id)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('crm::tech-panel.messages', [
            'technician' => $tech,
            'operators' => $tech->operators,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        if ($tech->operators()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'هنوز کارشناسی برای شما تخصیص داده نشده است.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ], [
            'body.required' => 'متن پیام الزامی است.',
        ]);

        $msg = TechChatMessage::create([
            'technician_id' => $tech->id,
            'sender_type'   => TechChatMessage::SENDER_TECH,
            'sender_id'     => $tech->id,
            'body'          => $validated['body'],
            'created_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'body' => $msg->body,
                'sender_type' => $msg->sender_type,
                'created_at' => $msg->created_at->format('H:i'),
            ],
        ]);
    }

    public function poll(Request $request)
    {
        $tech = Auth::guard('tech')->user();
        $afterId = (int) $request->query('after_id', 0);

        $messages = TechChatMessage::where('technician_id', $tech->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get(['id', 'sender_type', 'body', 'created_at']);

        TechChatMessage::where('technician_id', $tech->id)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'body' => $m->body,
                'created_at' => $m->created_at?->format('H:i') ?? '',
            ])->values(),
        ]);
    }

    /** تعداد پیام‌های نخوانده از اپراتور — برای badge در bottom-nav. */
    public function unread()
    {
        $tech = Auth::guard('tech')->user();

        $count = TechChatMessage::where('technician_id', $tech->id)
            ->where('sender_type', TechChatMessage::SENDER_OPERATOR)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $count]);
    }
}
