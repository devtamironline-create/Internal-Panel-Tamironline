<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Ticket;
use Modules\CRM\Models\TicketReply;

/**
 * تیکت‌های پشتیبانی تکنسین — سمت ادمین (مشاهده + پاسخ + بستن).
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->query('status');
        $priorityFilter = $request->query('priority');

        $query = Ticket::with(['technician:id,first_name,firstname_tech,mobile', 'order:id,order_code,customer_name'])
            ->latest();

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        if ($priorityFilter) {
            $query->where('priority', $priorityFilter);
        }

        $stats = [
            'open'    => Ticket::where('status', 'open')->count(),
            'replied' => Ticket::where('status', 'replied')->count(),
            'closed'  => Ticket::where('status', 'closed')->count(),
            'urgent'  => Ticket::where('priority', 'urgent')->where('status', '!=', 'closed')->count(),
        ];

        $tickets = $query->paginate(20)->withQueryString();

        return view('crm::tickets.index', compact('tickets', 'stats', 'statusFilter', 'priorityFilter'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'technician:id,first_name,firstname_tech,mobile',
            'order:id,order_code,customer_name,customer_mobile',
            'replies',
            'assignee:id,name',
        ]);

        return view('crm::tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $this->authorize('reply-crm-tickets');

        if ($ticket->status === 'closed') {
            return back()->with('error', 'این تیکت بسته شده — برای پاسخ ابتدا آن را باز کنید.');
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'body.required' => 'متن پاسخ الزامی است.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("crm/tickets", 'public');
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'sender_id' => auth()->id(),
            'body' => $validated['body'],
            'image_path' => $imagePath,
            'created_at' => now(),
        ]);

        $ticket->update([
            'status' => 'replied',
            'assigned_to' => $ticket->assigned_to ?? auth()->id(),
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'پاسخ شما به تکنسین ارسال شد.');
    }

    /**
     * سرو تصویر تیکت یا یکی از پاسخ‌هایش با چک دسترسی.
     * چون symlink استوریج روی هاست cPanel/LiteSpeed خراب است،
     * مستقیم با PHP فایل را می‌فرستیم.
     *
     * دسترسی:
     * - ادمین با view-crm-tickets: همهٔ تیکت‌ها
     * - تکنسین لاگین در guard tech: فقط تیکت‌های خودش
     */
    public function serveImage(Request $request, string $kind, int $id)
    {
        if (! in_array($kind, ['ticket', 'reply'], true)) {
            abort(404);
        }

        if ($kind === 'ticket') {
            $ticket = Ticket::find($id);
            if (! $ticket || ! $ticket->image_path) abort(404);
            $this->ensureCanView($ticket);
            $path = $ticket->image_path;
        } else {
            $reply = TicketReply::with('ticket')->find($id);
            if (! $reply || ! $reply->image_path || ! $reply->ticket) abort(404);
            $this->ensureCanView($reply->ticket);
            $path = $reply->image_path;
        }

        if (! Storage::disk('public')->exists($path)) abort(404);

        return Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function ensureCanView(Ticket $ticket): void
    {
        // ادمین با دسترسی → ok
        if (auth()->check() && auth()->user()->can('view-crm-tickets')) {
            return;
        }
        // تکنسینِ صاحب تیکت → ok
        $tech = Auth::guard('tech')->user();
        if ($tech && (int) $tech->id === (int) $ticket->technician_id) {
            return;
        }
        abort(403);
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $this->authorize('reply-crm-tickets');

        $validated = $request->validate([
            'status' => 'required|in:open,replied,closed',
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'وضعیت تیکت به‌روزرسانی شد.');
    }
}
