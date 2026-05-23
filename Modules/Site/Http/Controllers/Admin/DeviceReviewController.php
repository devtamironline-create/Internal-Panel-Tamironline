<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\DeviceReview;
use Modules\Site\Models\DeviceReviewReply;

class DeviceReviewController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('view-site-device-reviews')
            && ! $u->can('manage-site-device-reviews')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-device-reviews')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = DeviceReview::query()->with('reply')->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($device = trim((string) $request->query('device', ''))) {
            $query->where('device_slug', $device);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('author_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        $reviews = $query->paginate(20)->withQueryString();

        $counts = [
            'all'      => DeviceReview::count(),
            'pending'  => DeviceReview::where('status', DeviceReview::STATUS_PENDING)->count(),
            'approved' => DeviceReview::where('status', DeviceReview::STATUS_APPROVED)->count(),
            'rejected' => DeviceReview::where('status', DeviceReview::STATUS_REJECTED)->count(),
        ];

        return view('site::admin.device-reviews.index', compact('reviews', 'counts'));
    }

    public function show(string $id): View
    {
        $this->checkView();
        $review = DeviceReview::with('reply')->findOrFail($id);
        return view('site::admin.device-reviews.show', compact('review'));
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review = DeviceReview::findOrFail($id);
        $review->update([
            'status'      => $data['status'],
            'is_verified' => $data['status'] === DeviceReview::STATUS_APPROVED,
        ]);

        return back()->with('success', 'وضعیت نظر به‌روز شد.');
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();

        $data = $request->validate([
            'content' => 'required|string|min:5|max:3000',
        ]);

        $review = DeviceReview::findOrFail($id);

        DeviceReviewReply::updateOrCreate(
            ['review_id' => $review->id],
            [
                'author_name' => auth()->user()->name ?? 'تیم تعمیرآنلاین',
                'is_expert'   => true,
                'content'     => trim($data['content']),
            ]
        );

        return back()->with('success', 'پاسخ ثبت شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkManage();
        DeviceReview::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.device-reviews.index')
            ->with('success', 'نظر حذف شد.');
    }
}
