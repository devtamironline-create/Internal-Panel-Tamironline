<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TechChatMessage;

/**
 * چت ۱:۱ اپراتور↔تکنسین — سمت ادمین.
 *
 * هر اپراتور فقط تکنسین‌های assign‌شدهٔ خودش را می‌بیند. ادمین با
 * دسترسی manage-technicians می‌تواند assignment را عوض کند.
 */
class TechChatController extends Controller
{
    /** لیست تکنسین‌های گفت‌وگو با آخرین پیام و تعداد نخوانده. */
    public function index(Request $request)
    {
        $user = Auth::user();

        // اگر دسترسی manage-technicians دارد، می‌تواند همه را ببیند با ?all=1.
        $showAll = $request->boolean('all') && $user->can('manage-technicians');

        $query = Technician::query()
            ->select('id', 'first_name', 'last_name', 'firstname_tech', 'mobile', 'assigned_operator_id')
            ->with('assignedOperator:id,first_name,last_name');

        if (! $showAll) {
            $query->where('assigned_operator_id', $user->id);
        }

        $technicians = $query->orderBy('first_name')->get();

        // آخرین پیام + تعداد نخوانده برای هر تکنسین
        $techIds = $technicians->pluck('id')->all();
        $last = TechChatMessage::query()
            ->whereIn('technician_id', $techIds)
            ->select('technician_id', DB::raw('MAX(created_at) as last_at'))
            ->groupBy('technician_id')
            ->pluck('last_at', 'technician_id');

        $unread = TechChatMessage::query()
            ->whereIn('technician_id', $techIds)
            ->where('sender_type', TechChatMessage::SENDER_TECH)
            ->whereNull('read_at')
            ->select('technician_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('technician_id')
            ->pluck('cnt', 'technician_id');

        $technicians = $technicians->map(function ($t) use ($last, $unread) {
            $t->last_message_at = $last->get($t->id);
            $t->unread_count = (int) ($unread->get($t->id) ?? 0);
            return $t;
        })->sortByDesc(fn($t) => $t->last_message_at ?? '');

        return view('crm::tech-chats.index', [
            'technicians' => $technicians,
            'showAll' => $showAll,
        ]);
    }

    /** نمایش thread یک تکنسین + علامت‌گذاری به‌عنوان خوانده. */
    public function show(Request $request, Technician $technician)
    {
        $this->authorizeAccess($technician);

        $messages = TechChatMessage::where('technician_id', $technician->id)
            ->orderBy('created_at')
            ->get();

        // پیام‌های تکنسین که توسط اپراتور خوانده می‌شوند → read_at
        TechChatMessage::where('technician_id', $technician->id)
            ->where('sender_type', TechChatMessage::SENDER_TECH)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('crm::tech-chats.show', [
            'technician' => $technician,
            'messages' => $messages,
        ]);
    }

    /** ارسال پیام اپراتور به تکنسین. */
    public function send(Request $request, Technician $technician)
    {
        $this->authorizeAccess($technician);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ], [
            'body.required' => 'متن پیام الزامی است.',
        ]);

        $msg = TechChatMessage::create([
            'technician_id' => $technician->id,
            'sender_type'   => TechChatMessage::SENDER_OPERATOR,
            'sender_id'     => Auth::id(),
            'body'          => $validated['body'],
            'created_at'    => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $msg->id,
                    'body' => $msg->body,
                    'sender_type' => $msg->sender_type,
                    'created_at' => $msg->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        }

        return back()->with('success', 'پیام ارسال شد.');
    }

    /**
     * Endpoint سبکی برای polling — پیام‌های جدید بعد از after_id را برمی‌گرداند
     * و آن‌هایی که از سمت تکنسین آمده‌اند را به‌عنوان خوانده ثبت می‌کند.
     */
    public function poll(Request $request, Technician $technician)
    {
        $this->authorizeAccess($technician);
        $afterId = (int) $request->query('after_id', 0);

        $messages = TechChatMessage::where('technician_id', $technician->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get(['id', 'sender_type', 'body', 'created_at']);

        TechChatMessage::where('technician_id', $technician->id)
            ->where('sender_type', TechChatMessage::SENDER_TECH)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'body' => $m->body,
                'created_at' => $m->created_at?->format('H:i') ?? '',
            ])->values(),
        ]);
    }

    /** مجموع پیام‌های نخوانده — برای badge سایدبار/صفحه. */
    public function unreadSummary()
    {
        $user = Auth::user();

        $count = TechChatMessage::query()
            ->whereHas('technician', fn($q) => $q->where('assigned_operator_id', $user->id))
            ->where('sender_type', TechChatMessage::SENDER_TECH)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $count]);
    }

    /** صفحهٔ تخصیص تکنسین‌ها به اپراتورها (فقط ادمین). */
    public function assignments()
    {
        abort_unless(Auth::user()?->can('manage-technicians'), 403);

        $technicians = Technician::query()
            ->select('id', 'first_name', 'last_name', 'firstname_tech', 'mobile', 'assigned_operator_id')
            ->orderBy('first_name')
            ->get();

        $operators = User::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('crm::tech-chats.assignments', [
            'technicians' => $technicians,
            'operators' => $operators,
        ]);
    }

    /** به‌روزرسانی اپراتور تخصیص‌داده‌شده برای یک تکنسین. */
    public function updateAssignment(Request $request, Technician $technician)
    {
        abort_unless(Auth::user()?->can('manage-technicians'), 403);

        $validated = $request->validate([
            'assigned_operator_id' => 'nullable|exists:users,id',
        ]);

        $technician->update([
            'assigned_operator_id' => $validated['assigned_operator_id'] ?? null,
        ]);

        return back()->with('success', 'اپراتورِ تخصیص‌داده‌شده به‌روزرسانی شد.');
    }

    /**
     * چک می‌کند اپراتور فعلی به این تکنسین تخصیص داده شده یا دسترسی
     * manage-technicians دارد.
     */
    protected function authorizeAccess(Technician $technician): void
    {
        $user = Auth::user();
        if ($user->can('manage-technicians')) return;
        if ((int) $technician->assigned_operator_id === (int) $user->id) return;
        abort(403, 'این تکنسین به شما تخصیص داده نشده است.');
    }
}
