<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Models\EmployeeSetting;
use Modules\Attendance\Models\LeaveRequest;
use Modules\Attendance\Models\LeaveType;

class LeaveController extends Controller
{
    /**
     * My leave requests
     */
    public function index()
    {
        $user = auth()->user();

        $requests = LeaveRequest::with(['leaveType', 'approver'])
            ->forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $employeeSettings = EmployeeSetting::getOrCreate($user->id);

        $stats = [
            'annual_balance' => $employeeSettings->annual_leave_balance,
            'sick_balance' => $employeeSettings->sick_leave_balance,
            'pending_count' => LeaveRequest::forUser($user->id)->pending()->count(),
            'approved_this_year' => LeaveRequest::forUser($user->id)
                ->approved()
                ->whereYear('start_date', now()->year)
                ->sum('days_count'),
        ];

        return view('attendance::leave.index', compact('requests', 'stats'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $leaveTypes = LeaveType::getActive();
        $colleagues = User::staff()->where('id', '!=', auth()->id())->get();
        $employeeSettings = EmployeeSetting::getOrCreate(auth()->id());

        return view('attendance::leave.create', compact('leaveTypes', 'colleagues', 'employeeSettings'));
    }

    /**
     * Store leave request
     */
    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'reason' => 'nullable|string|max:500',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'substitute_id' => 'nullable|exists:users,id',
            'days_count' => 'nullable|numeric|min:0.5',
        ], [
            'leave_type_id.required' => 'لطفا نوع مرخصی را انتخاب کنید',
            'start_date.required' => 'تاریخ شروع الزامی است',
            'start_date.date_format' => 'فرمت تاریخ شروع نامعتبر است',
            'end_date.required' => 'تاریخ پایان الزامی است',
            'end_date.date_format' => 'فرمت تاریخ پایان نامعتبر است',
        ]);

        $leaveType = LeaveType::findOrFail($request->leave_type_id);

        // Calculate days
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $daysCount = $request->days_count ?? ($startDate->diffInDays($endDate) + 1);

        // Check balance
        if (!LeaveRequest::checkBalance(auth()->id(), $request->leave_type_id, $daysCount)) {
            return back()->withErrors(['leave_type_id' => 'مانده مرخصی کافی نیست'])->withInput();
        }

        // Handle document upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents/' . date('Y/m'), 'public');
        }

        // Check if document is required
        if ($leaveType->requires_document && !$documentPath) {
            return back()->withErrors(['document' => 'برای این نوع مرخصی ارسال مدرک الزامی است'])->withInput();
        }

        $leaveRequest = LeaveRequest::createRequest([
            'user_id' => auth()->id(),
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'days_count' => $daysCount,
            'reason' => $request->reason,
            'document_path' => $documentPath,
            'substitute_id' => $request->substitute_id,
        ]);

        return redirect()->route('leave.index')
            ->with('success', 'درخواست مرخصی با موفقیت ثبت شد');
    }

    /**
     * Show single request
     */
    public function show(LeaveRequest $leaveRequest)
    {
        // Check permission
        if ($leaveRequest->user_id !== auth()->id() && !auth()->user()->can('manage-attendance')) {
            abort(403);
        }

        $leaveRequest->load(['user', 'leaveType', 'approver', 'substitute']);

        return view('attendance::leave.show', compact('leaveRequest'));
    }

    /**
     * Cancel request
     */
    public function cancel(LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            $leaveRequest->cancel();
            return redirect()->route('leave.index')
                ->with('success', 'درخواست مرخصی لغو شد');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approval queue - for supervisors
     */
    public function approvals()
    {
        $user = auth()->user();

        // Get users who have current user as supervisor
        $subordinateIds = EmployeeSetting::where('supervisor_id', $user->id)
            ->pluck('user_id')
            ->toArray();

        // If admin/manager, show all pending
        if ($user->can('manage-attendance')) {
            $pendingRequests = LeaveRequest::with(['user', 'leaveType'])
                ->pending()
                ->orderBy('created_at', 'asc')
                ->paginate(15);
        } else {
            $pendingRequests = LeaveRequest::with(['user', 'leaveType'])
                ->whereIn('user_id', $subordinateIds)
                ->pending()
                ->orderBy('created_at', 'asc')
                ->paginate(15);
        }

        $recentDecisions = LeaveRequest::with(['user', 'leaveType'])
            ->where('approved_by', $user->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->orderBy('approved_at', 'desc')
            ->limit(10)
            ->get();

        return view('attendance::leave.approvals', compact('pendingRequests', 'recentDecisions'));
    }

    /**
     * Approve request
     */
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeApproval($leaveRequest);

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $leaveRequest->approve(auth()->id(), $request->note);

        return redirect()->route('leave.approvals')
            ->with('success', 'درخواست مرخصی تایید شد');
    }

    /**
     * Reject request
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeApproval($leaveRequest);

        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $leaveRequest->reject(auth()->id(), $request->note);

        return redirect()->route('leave.approvals')
            ->with('success', 'درخواست مرخصی رد شد');
    }

    /**
     * Admin - All leave requests
     */
    public function adminIndex(Request $request)
    {
        $query = LeaveRequest::with(['user', 'leaveType', 'approver']);

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        $users = User::staff()->get();
        $leaveTypes = LeaveType::getActive();

        return view('attendance::leave.admin-index', compact('requests', 'users', 'leaveTypes'));
    }

    /**
     * Check if user can approve this request
     */
    protected function authorizeApproval(LeaveRequest $leaveRequest): void
    {
        $user = auth()->user();

        // Admin can approve all
        if ($user->can('manage-attendance')) {
            return;
        }

        // Check if supervisor
        $employeeSetting = EmployeeSetting::where('user_id', $leaveRequest->user_id)->first();

        if (!$employeeSetting || $employeeSetting->supervisor_id !== $user->id) {
            abort(403, 'شما مجوز تایید این درخواست را ندارید');
        }
    }
}
