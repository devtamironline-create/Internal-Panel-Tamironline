<?php

namespace Modules\Ticket\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Ticket\Models\Ticket;
use Modules\Ticket\Models\TicketReply;
use Modules\Customer\Models\Customer;
use App\Models\User;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['customer', 'assignedTo', 'replies']);

        if ($search = $request->input('search')) {
            $query->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhereHas('customer', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query->latest()->paginate(20);

        return view('ticket::tickets.index', compact('tickets'));
    }

    public function create()
    {
        $customers = Customer::active()->get();
        return view('ticket::tickets.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'department' => 'required|in:support,technical,billing,sales',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        $validated['ticket_number'] = Ticket::generateTicketNumber();
        $validated['status'] = 'open';

        $ticket = Ticket::create($validated);

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'تیکت جدید ایجاد شد');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['customer', 'assignedTo', 'replies.user']);
        $staffUsers = User::where('is_staff', true)->get();

        return view('ticket::tickets.show', compact('ticket', 'staffUsers'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,pending,answered,closed',
            'priority' => 'required|in:low,normal,high,urgent',
            'department' => 'required|in:support,technical,billing,sales',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validated['status'] === 'closed' && !$ticket->closed_at) {
            $validated['closed_at'] = now();
        }

        $ticket->update($validated);

        return back()->with('success', 'تیکت بروزرسانی شد');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.tickets.index')
            ->with('success', 'تیکت حذف شد');
    }

    // Reply to ticket
    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $ticket->replies()->create([
            'user_id' => auth()->id(),
            'message' => $validated['message'],
            'is_staff' => auth()->user()->is_staff,
        ]);

        return back()->with('success', 'پاسخ شما ثبت شد');
    }

    // Close ticket
    public function close(Ticket $ticket)
    {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', 'تیکت بسته شد');
    }

    // Reopen ticket
    public function reopen(Ticket $ticket)
    {
        $ticket->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return back()->with('success', 'تیکت دوباره باز شد');
    }
}
