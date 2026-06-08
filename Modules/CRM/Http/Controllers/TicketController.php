<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\Ticket;
use Modules\CRM\Models\TicketCategory;
use Modules\CRM\Models\TicketReply;

/**
 * تیکت‌های پشتیبانی تکنسین — سمت ادمین (مشاهده + پاسخ + بستن).
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->query('status');
        $categoryFilter = $request->query('category');

        $query = Ticket::with([
                'technician:id,first_name,firstname_tech,mobile',
                'order:id,order_code,customer_name',
                'category:id,name',
            ])
            ->latest();

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        } else {
            // پیش‌فرض: تیکت‌های بایگانی‌شده در لیست فعال نمایش داده
            // نمی‌شوند — اپراتور باید روی تب «بایگانی» کلیک کند.
            $query->where('status', '!=', 'archived');
        }
        if ($categoryFilter) {
            $query->where('category_id', $categoryFilter);
        }

        $stats = [
            'open'     => Ticket::where('status', 'open')->count(),
            'replied'  => Ticket::where('status', 'replied')->count(),
            'closed'   => Ticket::where('status', 'closed')->count(),
            'archived' => Ticket::where('status', 'archived')->count(),
            'total'    => Ticket::count(),
        ];

        $tickets = $query->paginate(20)->withQueryString();
        $categories = TicketCategory::ordered()->get(['id', 'name', 'is_active']);

        return view('crm::tickets.index', compact('tickets', 'stats', 'statusFilter', 'categoryFilter', 'categories'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'technician:id,first_name,firstname_tech,mobile',
            'order:id,order_code,customer_name,customer_mobile',
            'category:id,name',
            'replies',
            'assignee:id,first_name,last_name',
            'creatorAdmin:id,first_name,last_name',
        ]);

        return view('crm::tickets.show', compact('ticket'));
    }

    /**
     * فرم ساخت تیکت از سمت ادمین برای یک تکنسین مشخص.
     */
    public function create(Request $request)
    {
        $technicians = Technician::query()
            ->select('id', 'first_name', 'last_name', 'firstname_tech', 'mobile')
            ->orderBy('first_name')
            ->get();

        $categories = TicketCategory::active()->ordered()->get(['id', 'name']);

        return view('crm::tickets.create', [
            'technicians' => $technicians,
            'categories' => $categories,
            'preselectedTech' => $request->query('technician_id'),
        ]);
    }

    /**
     * ذخیرهٔ تیکت ساخته‌شده توسط ادمین برای تکنسین.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'technician_id' => 'required|exists:crm_technicians,id',
            'category_id'   => 'required|exists:crm_ticket_categories,id',
            'subject'       => 'nullable|string|max:200',
            'body'          => 'required|string|max:5000',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ], [
            'technician_id.required' => 'انتخاب تکنسین الزامی است.',
            'category_id.required'   => 'دسته‌بندی الزامی است.',
            'body.required'          => 'متن تیکت الزامی است.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('crm/tickets', 'public');
        }

        $ticket = Ticket::create([
            'technician_id'       => $validated['technician_id'],
            'category_id'         => $validated['category_id'],
            'subject'             => $validated['subject'] ?? null,
            'body'                => $validated['body'],
            'status'              => 'replied', // ادمین خودش شروع‌کننده است → برای تکنسین «جدید»
            'direction'           => Ticket::DIRECTION_ADMIN_TO_TECH,
            'created_by_admin_id' => auth()->id(),
            'assigned_to'         => auth()->id(),
            'image_path'          => $imagePath,
            'last_reply_at'       => now(),
        ]);

        return redirect()->route('crm.tickets.show', $ticket)
            ->with('success', 'تیکت برای تکنسین ارسال شد.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
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

        if (! Storage::disk('public')->exists($path)) {
            // فایل ضمیمه روی disk موجود نیست (نتیجهٔ pull اشتباه برانچ
            // deploy/ganje). به‌جای 404 یک SVG placeholder برمی‌گردانیم
            // تا UI آیکن شکسته نشان ندهد.
            return TechPanelSettingsController::placeholderResponse('attachment');
        }

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
        $validated = $request->validate([
            'status' => 'required|in:open,replied,closed,archived',
        ]);

        $ticket->update([
            'status'      => $validated['status'],
            'closed_at'   => $validated['status'] === 'closed'   ? now() : null,
            'archived_at' => $validated['status'] === 'archived' ? now() : null,
        ]);

        return back()->with('success', 'وضعیت تیکت به‌روزرسانی شد.');
    }

    /**
     * بایگانی دستی تیکت توسط اپراتور. به‌جای تغییر آزاد status، یک
     * مسیر اختصاصی است تا در view راحت یک دکمهٔ «بایگانی» داشته باشیم.
     */
    public function archive(Ticket $ticket)
    {
        if ($ticket->status === 'archived') {
            return back()->with('success', 'این تیکت قبلاً بایگانی شده است.');
        }
        $ticket->update([
            'status'      => 'archived',
            'archived_at' => now(),
        ]);

        return back()->with('success', 'تیکت به بایگانی منتقل شد.');
    }

    public function unarchive(Ticket $ticket)
    {
        if ($ticket->status !== 'archived') {
            return back()->with('success', 'این تیکت در بایگانی نیست.');
        }
        // وضعیت قبل از بایگانی: اگر آخرین پاسخ از admin بود → replied،
        // در غیر اینصورت open (تکنسین پاسخ داده ولی هنوز رسیدگی نشده).
        $lastReply = $ticket->replies()->latest('id')->first();
        $restoreStatus = ($lastReply && $lastReply->sender_type === 'admin') ? 'replied' : 'open';

        $ticket->update([
            'status'      => $restoreStatus,
            'archived_at' => null,
        ]);

        return back()->with('success', 'تیکت از بایگانی خارج شد.');
    }
}
