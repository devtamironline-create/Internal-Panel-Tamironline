<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Ticket;
use Modules\CRM\Models\TicketCategory;
use Modules\CRM\Models\TicketReply;
use Modules\CRM\Support\TechImageStorage;

/**
 * تیکت‌های پشتیبانی — سمت پنل تکنسین.
 */
class TicketController extends Controller
{
    public function index()
    {
        $tech = Auth::guard('tech')->user();

        $tickets = Ticket::where('technician_id', $tech->id)
            ->with(['order:id,order_code,customer_name', 'category:id,name'])
            ->latest()
            ->paginate(15);

        return view('crm::tech-panel.tickets.index', [
            'technician' => $tech,
            'tickets' => $tickets,
        ]);
    }

    public function create()
    {
        $tech = Auth::guard('tech')->user();

        // ۲۰ سفارش اخیر تکنسین — برای dropdown انتخاب سفارش
        $orders = Order::query()
            ->forTechnician($tech->id)
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'order_code', 'customer_name']);

        $categories = TicketCategory::active()->ordered()->get(['id', 'name']);

        return view('crm::tech-panel.tickets.create', [
            'technician' => $tech,
            'orders' => $orders,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $validated = $request->validate([
            'order_id' => 'nullable|exists:crm_orders,id',
            'category_id' => 'required|exists:crm_ticket_categories,id',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:30720',
        ], [
            'body.required' => 'متن تیکت الزامی است.',
            'category_id.required' => 'دسته‌بندی را انتخاب کنید.',
            'image.max' => 'حجم تصویر حداکثر ۳۰ مگابایت.',
        ]);

        // اگر سفارشی انتخاب شده، باید متعلق به همین تکنسین باشد
        if (! empty($validated['order_id'])) {
            $belongs = Order::whereKey($validated['order_id'])
                ->where('technician_id', $tech->id)
                ->exists();
            if (! $belongs) {
                return back()->withErrors(['order_id' => 'این سفارش متعلق به شما نیست.'])->withInput();
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = TechImageStorage::store($request->file('image'), 'crm/tickets');
        }

        $ticket = Ticket::create([
            'technician_id' => $tech->id,
            'order_id' => $validated['order_id'] ?? null,
            'category_id' => $validated['category_id'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'status' => 'open',
            'image_path' => $imagePath,
        ]);

        return redirect()->route('tech.tickets.show', $ticket)->with('success', 'تیکت با موفقیت ثبت شد.');
    }

    public function show(Ticket $ticket)
    {
        $tech = Auth::guard('tech')->user();
        if ((int) $ticket->technician_id !== (int) $tech->id) {
            abort(403);
        }

        $ticket->load('order:id,order_code,customer_name', 'category:id,name', 'replies');

        return view('crm::tech-panel.tickets.show', [
            'technician' => $tech,
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $tech = Auth::guard('tech')->user();
        if ((int) $ticket->technician_id !== (int) $tech->id) {
            abort(403);
        }
        if ($ticket->status === 'closed') {
            return back()->with('error', 'این تیکت بسته شده — امکان پاسخ نیست.');
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:30720',
        ], [
            'body.required' => 'متن پاسخ الزامی است.',
            'image.max' => 'حجم تصویر حداکثر ۳۰ مگابایت.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = TechImageStorage::store($request->file('image'), 'crm/tickets');
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'tech',
            'sender_id' => $tech->id,
            'body' => $validated['body'],
            'image_path' => $imagePath,
            'created_at' => now(),
        ]);

        $ticket->update([
            'status' => 'open', // پاسخ تکنسین → open می‌شود تا اپراتور ببیند
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'پاسخ شما ثبت شد.');
    }
}
