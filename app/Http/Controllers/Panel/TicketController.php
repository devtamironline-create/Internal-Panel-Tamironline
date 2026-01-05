<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ticket\Models\Ticket;
use Modules\Ticket\Models\TicketReply;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $query = $customer->tickets();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $tickets = $query->latest()->paginate(10);

        return view('panel.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('panel.tickets.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'department' => 'required|in:support,technical,billing,sales',
            'priority' => 'required|in:low,normal,high,urgent',
            'description' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'ticket_number' => Ticket::generateTicketNumber(),
            'subject' => $validated['subject'],
            'department' => $validated['department'],
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        return redirect()->route('panel.tickets.show', $ticket)
            ->with('success', 'تیکت با موفقیت ایجاد شد. شماره تیکت: ' . $ticket->ticket_number);
    }

    public function show(Ticket $ticket)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($ticket->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این تیکت را ندارید.');
        }

        $ticket->load(['replies.user', 'assignedTo']);

        return view('panel.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($ticket->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این تیکت را ندارید.');
        }

        // Cannot reply to closed tickets
        if ($ticket->status === 'closed') {
            return back()->with('error', 'امکان پاسخ به تیکت بسته شده وجود ندارد.');
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_staff_reply' => false,
        ]);

        // Update ticket status
        $ticket->update([
            'status' => 'pending',
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'پاسخ شما با موفقیت ارسال شد.');
    }

    public function close(Ticket $ticket)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($ticket->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این تیکت را ندارید.');
        }

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', 'تیکت با موفقیت بسته شد.');
    }
}
