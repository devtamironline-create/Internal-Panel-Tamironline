<?php

namespace Modules\CRM\Http\Controllers;

use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Services\Najva\NajvaPushService;

/**
 * ۵.۴ و ۵.۵ — گزارشِ ارسال‌ها و فهرستِ مشترکین.
 */
class PushReportController extends Controller
{
    use ExportsListToFile;

    /** ۵.۴ — جدولِ لاگِ داخلی با فیلتر. */
    public function logs(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('technician:id,first_name,firstname_tech')
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('crm::push.logs', [
            'logs' => $logs,
            'events' => PushEvent::cases(),
            'statuses' => PushLog::STATUS_LABELS,
            'technicians' => Technician::query()
                ->whereNull('deleted_at')
                ->orderBy('firstname_tech')
                ->get(['id', 'first_name', 'firstname_tech']),
            'summary' => $this->summary($request),
        ]);
    }

    /** خروجی اکسل/CSV از همان فیلترِ روی صفحه. */
    public function export(Request $request)
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $query = $this->filtered($request)->with('technician:id,first_name,firstname_tech');

        return $this->streamSpreadsheet(
            'push-logs',
            $format,
            ['تاریخ', 'تکنسین', 'رویداد', 'عنوان', 'متن', 'وضعیت', 'هزینه', 'کد خطا', 'شناسه درخواست'],
            function () use ($query) {
                foreach ($query->latest('created_at')->cursor() as $log) {
                    yield [
                        JalaliDate::toJalali($log->created_at?->toDateString()).' '.($log->created_at?->format('H:i') ?? ''),
                        $log->technician->firstname_tech ?? $log->technician->first_name ?? '—',
                        $log->event_key ? (PushEvent::tryFrom($log->event_key)?->label() ?? $log->event_key) : 'ارسال دستی',
                        $log->title,
                        $log->body,
                        $log->statusLabel(),
                        $log->cost,
                        $log->error_code ?? '',
                        $log->request_id ?? '',
                    ];
                }
            }
        );
    }

    /**
     * پیگیریِ یک درخواست از خودِ نجوا — تحویل و کلیک.
     *
     * لاگِ ما فقط می‌گوید نجوا درخواست را پذیرفت. آنچه بعد از آن افتاده
     * تنها از این مسیر معلوم می‌شود.
     */
    public function trace(Request $request, NajvaPushService $najva)
    {
        $validated = $request->validate(['request_id' => 'required|string|max:64']);

        $result = $najva->reportToken($validated['request_id']);

        if (! $result['ok']) {
            return back()->with('error', '✗ '.$result['message']);
        }

        if ($result['tokens'] === []) {
            return back()->with('error', 'نجوا برای این شناسه گزارشی ندارد — ممکن است هنوز پردازش نشده باشد.');
        }

        $summary = collect($result['tokens'])
            ->countBy(fn ($row) => (string) ($row['status'] ?? '؟'))
            ->map(fn ($count, $status) => $status.': '.$count)
            ->implode(' | ');

        return back()->with('success', '✓ گزارش نجوا — '.$summary);
    }

    /** ۵.۵ — مشترکین: چه کسی می‌تواند اعلان بگیرد و چه کسی نه. */
    public function subscribers(Request $request)
    {
        $tokens = TechnicianPushToken::query()
            ->selectRaw('technician_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when revoked_at is null then 1 else 0 end) as active_count')
            ->selectRaw('max(last_seen_at) as last_seen')
            ->groupBy('technician_id')
            ->get()
            ->keyBy('technician_id');

        $lastSend = PushLog::query()
            ->selectRaw('technician_id, max(created_at) as last_push')
            ->groupBy('technician_id')
            ->pluck('last_push', 'technician_id');

        $lastStatus = PushLog::query()
            ->whereIn('id', PushLog::query()->selectRaw('max(id)')->groupBy('technician_id'))
            ->pluck('status', 'technician_id');

        $rows = Technician::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('firstname_tech')
            ->get(['id', 'first_name', 'firstname_tech', 'mobile'])
            ->map(fn (Technician $t) => [
                'technician' => $t,
                'active' => (int) ($tokens[$t->id]->active_count ?? 0),
                'total' => (int) ($tokens[$t->id]->total ?? 0),
                'last_seen' => $tokens[$t->id]->last_seen ?? null,
                'last_push' => $lastSend[$t->id] ?? null,
                'last_status' => $lastStatus[$t->id] ?? null,
            ]);

        return view('crm::push.subscribers', [
            // بی‌توکن‌ها اول: همان فهرستی که باید پیگیری شود.
            'rows' => $rows->sortBy(fn ($r) => [$r['active'] > 0 ? 1 : 0, $r['technician']->firstname_tech])->values(),
            'without' => $rows->where('active', 0)->count(),
            'with' => $rows->where('active', '>', 0)->count(),
            'statuses' => PushLog::STATUS_LABELS,
        ]);
    }

    /** مشخصاتِ دستگاهِ مرورگرهای یک تکنسین — مستقیم از نجوا. */
    public function devices(Technician $technician, NajvaPushService $najva)
    {
        $rows = TechnicianPushToken::query()
            ->where('technician_id', $technician->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TechnicianPushToken $t) => [
                'row' => $t,
                'detail' => $t->isRevoked() ? null : $najva->tokenDetail($t->token),
            ]);

        return view('crm::push.devices', [
            'technician' => $technician,
            'rows' => $rows,
            'configured' => $najva->configured(),
        ]);
    }

    // ───────────────────────── فیلتر

    private function filtered(Request $request)
    {
        return PushLog::query()
            ->when($request->filled('event'), fn ($q) => $request->query('event') === 'manual'
                ? $q->whereNull('event_key')
                : $q->where('event_key', $request->query('event')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('technician_id'), fn ($q) => $q->where('technician_id', $request->query('technician_id')))
            ->when($request->filled('from'), function ($q) use ($request) {
                $from = JalaliDate::toGregorian((string) $request->query('from'));

                return $from ? $q->whereDate('created_at', '>=', $from) : $q;
            })
            ->when($request->filled('to'), function ($q) use ($request) {
                $to = JalaliDate::toGregorian((string) $request->query('to'));

                return $to ? $q->whereDate('created_at', '<=', $to) : $q;
            });
    }

    /** @return array<string, int> */
    private function summary(Request $request): array
    {
        $rows = $this->filtered($request)->get(['status', 'cost']);

        return [
            'count' => $rows->count(),
            'ok' => $rows->whereIn('status', [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED])->count(),
            'invalid' => $rows->where('status', PushLog::STATUS_INVALID)->count(),
            'failed' => $rows->where('status', PushLog::STATUS_FAILED)->count(),
            'cost' => (int) $rows->sum('cost'),
        ];
    }
}
