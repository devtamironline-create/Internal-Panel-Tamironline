<?php

namespace Modules\CustomerApp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\OrderReview;
use Modules\CRM\Services\TechnicianRatingService;

/**
 * مدیریت نظرسنجی‌های مشتریان اپ.
 *
 * Admin می‌تواند:
 *   - لیست را با فیلتر status/rating/technician ببیند
 *   - یک نظر را تأیید/رد کند (با یادداشت اختیاری)
 *   - نظر را حذف کند (در موارد توهین/spam)
 *
 * هر تغییر status روی نظری که technician_id دارد → recompute satisfaction_score.
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $minRating = $request->integer('min_rating');
        $maxRating = $request->integer('max_rating');

        $query = OrderReview::query()
            ->with([
                'order:id,order_code,device_id,brand_id,customer_id,technician_id',
                'order.device:id,name',
                'order.brand:id,name',
                'customer:id,first_name,last_name,mobile',
                'technician:id,first_name,last_name,firstname_tech,mobile',
            ])
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }
        if ($minRating > 0) {
            $query->where('rating', '>=', $minRating);
        }
        if ($maxRating > 0) {
            $query->where('rating', '<=', $maxRating);
        }

        $items = $query->paginate(20)->withQueryString();

        $counts = [
            'pending' => OrderReview::query()->pending()->count(),
            'approved' => OrderReview::query()->approved()->count(),
            'rejected' => OrderReview::query()->where('status', 'rejected')->count(),
        ];

        return view('customerapp::admin.reviews.index', compact('items', 'counts', 'status', 'minRating', 'maxRating'));
    }

    public function show(OrderReview $review): View
    {
        $review->load([
            'order:id,order_code,device_id,brand_id,customer_id,technician_id,problem_title,problem_description,total_invoice',
            'order.device:id,name',
            'order.brand:id,name',
            'customer:id,first_name,last_name,mobile',
            'technician:id,first_name,last_name,firstname_tech,mobile,satisfaction_score',
            'moderator:id,name',
        ]);

        return view('customerapp::admin.reviews.show', ['review' => $review]);
    }

    public function moderate(Request $request, OrderReview $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'moderation_note' => 'nullable|string|max:500',
        ]);

        $review->forceFill([
            'status' => $data['status'],
            'moderation_note' => $data['moderation_note'] ?? null,
            'moderated_by_user_id' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();

        if ($review->technician_id) {
            TechnicianRatingService::recompute((int) $review->technician_id);
        }

        return back()->with('success', $data['status'] === 'approved' ? 'نظر تأیید شد.' : 'نظر رد شد.');
    }

    public function destroy(OrderReview $review): RedirectResponse
    {
        $techId = $review->technician_id;
        $review->delete();

        if ($techId) {
            TechnicianRatingService::recompute((int) $techId);
        }

        return redirect()->route('customer-app.reviews.index')->with('success', 'نظر حذف شد.');
    }
}
