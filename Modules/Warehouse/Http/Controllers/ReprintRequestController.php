<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\OrderLog;
use Modules\Warehouse\Models\ReprintRequest;

class ReprintRequestController extends Controller
{
    /**
     * لیست درخواست‌های چاپ مجدد
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('warehouse.reprint-invoice')) {
            abort(403, 'دسترسی ندارید');
        }

        $status = $request->input('status', 'pending');

        $requests = ReprintRequest::with(['order', 'requester', 'approver'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $pendingCount = ReprintRequest::where('status', 'pending')->count();

        return view('warehouse::reprint-requests.index', compact('requests', 'status', 'pendingCount'));
    }

    /**
     * تایید درخواست
     */
    public function approve(ReprintRequest $request)
    {
        if (!auth()->user()->can('warehouse.reprint-invoice')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید'], 403);
        }

        if (!$request->isPending()) {
            return response()->json(['success' => false, 'message' => 'این درخواست قبلاً پردازش شده است']);
        }

        $request->approve(auth()->user());

        OrderLog::log($request->order, OrderLog::ACTION_PRINTED, 'درخواست چاپ مجدد تایید شد', [
            'approver' => auth()->user()->name,
            'requester' => $request->requester->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'درخواست تایید شد. کاربر می‌تواند فاکتور را چاپ کند.'
        ]);
    }

    /**
     * رد درخواست
     */
    public function reject(Request $request, ReprintRequest $reprintRequest)
    {
        if (!auth()->user()->can('warehouse.reprint-invoice')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید'], 403);
        }

        if (!$reprintRequest->isPending()) {
            return response()->json(['success' => false, 'message' => 'این درخواست قبلاً پردازش شده است']);
        }

        $reason = $request->input('reason');

        $reprintRequest->reject(auth()->user(), $reason);

        OrderLog::log($reprintRequest->order, OrderLog::ACTION_PRINTED, 'درخواست چاپ مجدد رد شد', [
            'approver' => auth()->user()->name,
            'requester' => $reprintRequest->requester->name,
            'reason' => $reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'درخواست رد شد.'
        ]);
    }

    /**
     * تعداد درخواست‌های pending (برای badge)
     */
    public function pendingCount()
    {
        $count = ReprintRequest::where('status', 'pending')->count();

        return response()->json(['count' => $count]);
    }
}
