<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Ticket;
use Modules\CRM\Models\TicketCategory;
use Modules\CRM\Models\TicketReply;
use Modules\CRM\Support\TechImageStorage;

/**
 * تیکتِ پشتیبانیِ تکنسین — نسخهٔ API اپِ موبایل (هم‌ارزِ Tech\TicketController وب).
 * قالبِ پاسخ استاندارد: { success, data|message }. مالکیت روی همهٔ اکشن‌ها.
 */
class TicketController extends Controller
{
    /** GET /v1/technician/ticket-categories — دسته‌های فعال برای منوی «تیکت جدید». */
    public function categories(): JsonResponse
    {
        $data = TicketCategory::active()->ordered()->get(['id', 'name'])
            ->map(fn (TicketCategory $c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** GET /v1/technician/tickets?status=&page= — فهرستِ تیکت‌های خودِ تکنسین. */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();

        $query = Ticket::where('technician_id', $tech->id)
            ->with(['order:id,order_code,customer_name', 'category:id,name'])
            ->latest();

        $status = (string) $request->query('status', '');
        if ($status !== '' && array_key_exists($status, Ticket::STATUSES)) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => collect($tickets->items())->map(fn (Ticket $t) => $this->listShape($t))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /** POST /v1/technician/tickets — ثبتِ تیکتِ جدید. */
    public function store(Request $request): JsonResponse
    {
        $tech = $request->user();

        $validated = $request->validate([
            'order_id' => 'nullable|integer|exists:crm_orders,id',
            'category_id' => 'required|exists:crm_ticket_categories,id',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:30720',
        ], [
            'body.required' => 'متن تیکت الزامی است.',
            'category_id.required' => 'دسته‌بندی را انتخاب کنید.',
            'image.max' => 'حجم تصویر حداکثر ۳۰ مگابایت.',
        ]);

        if (! empty($validated['order_id'])) {
            $belongs = Order::whereKey($validated['order_id'])
                ->where('technician_id', $tech->id)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages(['order_id' => 'این سفارش متعلق به شما نیست.']);
            }
        }

        $imagePath = $request->hasFile('image')
            ? TechImageStorage::store($request->file('image'), 'crm/tickets')
            : null;

        $ticket = Ticket::create([
            'technician_id' => $tech->id,
            'order_id' => $validated['order_id'] ?? null,
            'category_id' => $validated['category_id'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'status' => 'open',
            'image_path' => $imagePath,
        ]);

        $ticket->load('order:id,order_code,customer_name', 'category:id,name', 'replies');

        return response()->json([
            'success' => true,
            'message' => 'تیکت با موفقیت ثبت شد.',
            'data' => $this->detailShape($ticket),
        ], 201);
    }

    /** GET /v1/technician/tickets/{id} — جزئیات + گفتگو (فقط مالِ خودِ تکنسین). */
    public function show(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();

        $ticket = Ticket::with('order:id,order_code,customer_name', 'category:id,name', 'replies')
            ->findOrFail($id);
        abort_unless((int) $ticket->technician_id === (int) $tech->id, 403, 'این تیکت متعلق به شما نیست.');

        return response()->json(['success' => true, 'data' => $this->detailShape($ticket)]);
    }

    /** POST /v1/technician/tickets/{id}/reply — پاسخِ تکنسین (روی تیکتِ بسته مجاز نیست). */
    public function reply(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();

        $ticket = Ticket::findOrFail($id);
        abort_unless((int) $ticket->technician_id === (int) $tech->id, 403, 'این تیکت متعلق به شما نیست.');

        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['body' => 'این تیکت بسته شده — امکان پاسخ نیست.']);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:30720',
        ], [
            'body.required' => 'متن پاسخ الزامی است.',
            'image.max' => 'حجم تصویر حداکثر ۳۰ مگابایت.',
        ]);

        $imagePath = $request->hasFile('image')
            ? TechImageStorage::store($request->file('image'), 'crm/tickets')
            : null;

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'tech',
            'sender_id' => $tech->id,
            'body' => $validated['body'],
            'image_path' => $imagePath,
            'created_at' => now(),
        ]);

        $ticket->update(['status' => 'open', 'last_reply_at' => now()]);

        $ticket->load('order:id,order_code,customer_name', 'category:id,name', 'replies');

        return response()->json([
            'success' => true,
            'message' => 'پاسخ شما ثبت شد.',
            'data' => $this->detailShape($ticket),
        ]);
    }

    /** @return array<string, mixed> */
    private function listShape(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $t->status,
            'status_label' => $t->statusLabel(),
            'category' => $t->category ? ['id' => $t->category->id, 'name' => $t->category->name] : null,
            'order' => $t->order ? ['id' => $t->order->id, 'order_code' => $t->order->order_code] : null,
            'last_reply_at' => $t->last_reply_at?->utc()->toIso8601String(),
            'created_at' => $t->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailShape(Ticket $t): array
    {
        return array_merge($this->listShape($t), [
            'body' => $t->body,
            'image_url' => $t->image_path ? storage_url($t->image_path) : null,
            'replies' => $t->replies->map(fn (TicketReply $r) => [
                'id' => $r->id,
                'sender_type' => $r->sender_type,
                'body' => $r->body,
                'image_url' => $r->image_path ? storage_url($r->image_path) : null,
                'created_at' => $r->created_at?->utc()->toIso8601String(),
            ])->values(),
        ]);
    }
}
