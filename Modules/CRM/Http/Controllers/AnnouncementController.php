<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Announcement;
use Modules\CRM\Models\Technician;

/**
 * مدیریت اعلانات تکنسین‌ها از پنل ادمین/اپراتور.
 *
 * اعلان فعال در پنل تکنسین (بخش «اعلانات» + پاپ‌آپ) نمایش داده می‌شود
 * و هر تکنسین با «متوجه شدم» تأییدش می‌کند. صفحهٔ جزئیات نشان می‌دهد
 * کدام تکنسین‌ها تأیید کرده‌اند.
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::query()
            ->withCount('acks')
            ->with('creator:id,first_name,last_name')
            ->latest()
            ->paginate(20);

        $activeTechCount = Technician::where('status', 'active')->count();

        return view('crm::announcements.index', compact('announcements', 'activeTechCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
        ], [
            'title.required' => 'عنوان اعلان را وارد کنید.',
            'title.max' => 'عنوان حداکثر ۲۰۰ کاراکتر است.',
            'body.required' => 'متن اعلان را وارد کنید.',
            'body.max' => 'متن اعلان حداکثر ۵۰۰۰ کاراکتر است.',
        ]);

        $announcement = Announcement::create($validated + [
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // اطلاعیه به همهٔ تکنسین‌های فعال پوش می‌شود — یک Job به ازای هر
        // تکنسین، چون هر کدام مرورگرهای خودش را دارد و ضدِتکرار و ساعتِ
        // مجاز هم فردی‌اند.
        //
        // این رویداد در پنجرهٔ ساعتِ مجاز است: اطلاعیه مهلت ندارد و نیمه‌شب
        // بیدارکردنِ تکنسین برایش توجیه ندارد.
        \Modules\CRM\Models\Technician::query()
            ->where('status', 'active')
            ->pluck('id')
            ->each(fn (int $technicianId) => \Modules\CRM\Jobs\SendTechnicianPush::queue(
                \Modules\CRM\Enums\PushEvent::Announcement,
                $technicianId,
                ['title' => (string) $announcement->title],
                sentBy: auth()->id(),
            ));

        return redirect()->route('crm.announcements.index')
            ->with('success', 'اعلان ثبت شد و از همین لحظه برای تکنسین‌ها نمایش داده می‌شود.');
    }

    /** جزئیات اعلان + وضعیت تأیید همهٔ تکنسین‌های فعال. */
    public function show(Announcement $announcement)
    {
        // pivot acknowledged_at — keyBy id برای lookup سریع در view
        $acked = $announcement->acks()->get()->keyBy('id');

        // تکنسین‌های فعال — تأییدنکرده‌ها اول (برای پیگیریِ راحت‌تر)، سپس بر اساسِ نام.
        $technicians = Technician::where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'mobile'])
            ->sortBy(fn ($t) => ($acked->has($t->id) ? '1' : '0').mb_strtolower($t->full_name))
            ->values();

        $totalCount = $technicians->count();
        $seenCount = $technicians->filter(fn ($t) => $acked->has($t->id))->count();
        $pendingCount = $totalCount - $seenCount;
        $percent = $totalCount > 0 ? (int) round($seenCount / $totalCount * 100) : 0;

        return view('crm::announcements.show', compact(
            'announcement', 'technicians', 'acked',
            'totalCount', 'seenCount', 'pendingCount', 'percent'
        ));
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('success', $announcement->is_active
            ? 'اعلان فعال شد و دوباره برای تکنسین‌ها نمایش داده می‌شود.'
            : 'اعلان غیرفعال شد و دیگر در پنل تکنسین نمایش داده نمی‌شود.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->route('crm.announcements.index')
            ->with('success', 'اعلان حذف شد.');
    }
}
