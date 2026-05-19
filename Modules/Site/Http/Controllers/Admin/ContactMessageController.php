<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\ContactMessage;

class ContactMessageController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('view-site-contact-messages')
            && ! $u->can('manage-site-contact-messages')
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
            ! $u->can('manage-site-contact-messages')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = ContactMessage::query()->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('full_name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $messages = $query->paginate(20)->withQueryString();

        $counts = [
            'all'     => ContactMessage::count(),
            'new'     => ContactMessage::where('status', ContactMessage::STATUS_NEW)->count(),
            'seen'    => ContactMessage::where('status', ContactMessage::STATUS_SEEN)->count(),
            'replied' => ContactMessage::where('status', ContactMessage::STATUS_REPLIED)->count(),
            'spam'    => ContactMessage::where('status', ContactMessage::STATUS_SPAM)->count(),
        ];

        return view('site::admin.contact-messages.index', compact('messages', 'counts'));
    }

    public function show(string $id): View
    {
        $this->checkView();

        $message = ContactMessage::findOrFail($id);

        if ($message->status === ContactMessage::STATUS_NEW && auth()->user()->can('manage-site-contact-messages')) {
            $message->update(['status' => ContactMessage::STATUS_SEEN]);
        }

        return view('site::admin.contact-messages.show', compact('message'));
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->checkManage();

        $data = $request->validate([
            'status' => 'required|in:new,seen,replied,spam',
        ]);

        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => $data['status']]);

        return back()->with('success', 'وضعیت پیام به‌روز شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkManage();

        $message = ContactMessage::findOrFail($id);
        $message->delete();

        return redirect()
            ->route('site.admin.contact-messages.index')
            ->with('success', 'پیام حذف شد.');
    }
}
