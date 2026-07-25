<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\BaleCampaign;
use Modules\CRM\Models\BaleCampaignRecipient;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Channels\BaleChannel;
use Modules\CustomerApp\Support\BaleMessenger;

/**
 * مدیریتِ کمپین‌های پیام‌رسانِ بله — ساخت، انتخابِ مخاطب، ارسالِ chunkبهchunk
 * (بدونِ نیاز به queue worker) و پیگیریِ per-recipient.
 */
class BaleCampaignController extends Controller
{
    /** اندازهٔ هر دستهٔ ارسال + سقفِ زمانیِ هر درخواستِ ارسال (ثانیه). */
    private const BATCH_SIZE = 40;

    private const TIME_BUDGET = 20;

    public function index()
    {
        $campaigns = BaleCampaign::query()
            ->with('creator:id,first_name,last_name')
            ->latest()
            ->paginate(20);

        return view('crm::bale-campaigns.index', [
            'campaigns' => $campaigns,
            'botConfigured' => (string) config('services.bale.bot_token') !== '',
            'safirConfigured' => (string) config('services.bale.safir_access_key') !== '' && (string) config('services.bale.safir_bot_id') !== '',
        ]);
    }

    public function create()
    {
        return view('crm::bale-campaigns.create', [
            'audiences' => BaleCampaign::AUDIENCES,
            'counts' => $this->audienceCounts(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'text' => 'required|string|max:3500',
            'audience' => 'required|string|in:'.implode(',', array_keys(BaleCampaign::AUDIENCES)),
            'manual_phones' => 'nullable|string|max:20000|required_if:audience,manual',
        ], [
            'title.required' => 'نامِ کمپین الزامی است.',
            'text.required' => 'متنِ پیام الزامی است.',
            'manual_phones.required_if' => 'برای مخاطبِ دستی، شماره‌ها را وارد کنید.',
        ]);

        $campaign = BaleCampaign::create($validated + [
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        $total = $this->buildRecipients($campaign);
        $campaign->update(['total_count' => $total]);

        if ($total === 0) {
            return redirect()->route('crm.bale-campaigns.show', $campaign)
                ->with('error', 'هیچ گیرنده‌ای برای این مخاطب پیدا نشد.');
        }

        return redirect()->route('crm.bale-campaigns.show', $campaign)
            ->with('success', "کمپین ساخته شد — {$total} گیرنده. با دکمهٔ «ارسالِ دستهٔ بعدی» شروع کنید.");
    }

    public function show(BaleCampaign $baleCampaign)
    {
        $baleCampaign->load('creator:id,first_name,last_name');

        return view('crm::bale-campaigns.show', [
            'campaign' => $baleCampaign,
            'pending' => $baleCampaign->recipients()->where('status', 'pending')->count(),
            'recent' => $baleCampaign->recipients()->with('customer:id,first_name,last_name,mobile')
                ->orderByDesc('id')->limit(50)->get(),
            'batchSize' => self::BATCH_SIZE,
        ]);
    }

    /** ارسالِ دستهٔ بعدی — با سقفِ زمانی تا درخواست timeout نشود. */
    public function process(BaleCampaign $baleCampaign)
    {
        if ($baleCampaign->status === 'done') {
            return back()->with('error', 'این کمپین قبلاً تمام شده است.');
        }

        $baleCampaign->update(['status' => 'sending']);

        $recipients = $baleCampaign->recipients()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        $started = microtime(true);
        $sent = $failed = 0;

        foreach ($recipients as $r) {
            if (microtime(true) - $started > self::TIME_BUDGET) {
                break; // بقیه با کلیکِ بعدی
            }

            $text = $this->personalize($baleCampaign->text, $r);
            $result = BaleMessenger::sendBest(
                $r->bale_user_id ? (int) $r->bale_user_id : null,
                $r->phone,
                $text,
            );

            $r->update([
                'status' => $result['ok'] ? 'sent' : 'failed',
                'error' => $result['ok'] ? null : $result['error'],
                'sent_at' => $result['ok'] ? now() : null,
            ]);
            $result['ok'] ? $sent++ : $failed++;
        }

        $remaining = $baleCampaign->recipients()->where('status', 'pending')->count();
        $baleCampaign->update([
            'sent_count' => $baleCampaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $baleCampaign->recipients()->where('status', 'failed')->count(),
            'status' => $remaining === 0 ? 'done' : 'sending',
        ]);

        $msg = "این دسته: {$sent} موفق، {$failed} ناموفق.";
        $msg .= $remaining === 0 ? ' ✅ کمپین تمام شد.' : " {$remaining} گیرنده باقی مانده — دوباره «ارسالِ دستهٔ بعدی» را بزنید.";

        return back()->with('success', $msg);
    }

    /** ارسالِ تستی به یک شماره (قبل از شروعِ کمپین). */
    public function test(Request $request, BaleCampaign $baleCampaign)
    {
        $validated = $request->validate(['phone' => 'required|string|max:20']);
        $phone = BaleChannel::intlPhone($validated['phone']);
        if ($phone === null) {
            return back()->with('error', 'شمارهٔ موبایل نامعتبر است.');
        }

        $result = BaleMessenger::sendToPhone($phone, $baleCampaign->text);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? "پیامِ تستی به {$phone} ارسال شد ✅" : 'ارسالِ تستی ناموفق: '.$result['error'],
        );
    }

    public function destroy(BaleCampaign $baleCampaign)
    {
        $baleCampaign->delete();

        return redirect()->route('crm.bale-campaigns.index')->with('success', 'کمپین حذف شد.');
    }

    /** ساختِ snapshotِ گیرنده‌ها بر اساسِ مخاطبِ انتخابی. برمی‌گرداند: تعداد. */
    private function buildRecipients(BaleCampaign $campaign): int
    {
        $rows = [];
        $now = now();

        if ($campaign->audience === 'manual') {
            $phones = preg_split('/[\s,،;]+/u', (string) $campaign->manual_phones) ?: [];
            foreach (array_unique(array_filter($phones)) as $raw) {
                $phone = BaleChannel::intlPhone($raw);
                if ($phone !== null) {
                    $rows[] = ['phone' => $phone, 'customer_id' => null, 'bale_user_id' => null];
                }
            }
        } else {
            $query = Customer::query()
                ->where('is_active', true)
                ->where('is_blocked', false)
                ->whereNotNull('mobile');

            if ($campaign->audience === 'bale_linked') {
                $query->whereNotNull('bale_user_id');
            } elseif ($campaign->audience === 'bale_customers') {
                $query->whereIn('id', Order::query()
                    ->whereIn('source', ['bale_bot', 'bale_miniapp'])
                    ->whereNotNull('customer_id')
                    ->distinct()
                    ->pluck('customer_id'));
            }

            foreach ($query->cursor() as $c) {
                $phone = BaleChannel::intlPhone((string) $c->mobile);
                if ($phone === null && empty($c->bale_user_id)) {
                    continue;
                }
                $rows[] = [
                    'phone' => $phone,
                    'customer_id' => $c->id,
                    'bale_user_id' => $c->bale_user_id ?: null,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            BaleCampaignRecipient::insert(array_map(fn ($r) => $r + [
                'campaign_id' => $campaign->id,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        return count($rows);
    }

    /** placeholderهای متنِ کمپین: {customer_name}. */
    private function personalize(string $text, BaleCampaignRecipient $r): string
    {
        $name = trim((string) ($r->customer?->display_name ?? ''));

        return strtr($text, ['{customer_name}' => $name !== '' ? $name : 'مشتری گرامی']);
    }

    /** @return array<string, int> */
    private function audienceCounts(): array
    {
        $base = Customer::query()->where('is_active', true)->where('is_blocked', false)->whereNotNull('mobile');

        return [
            'all' => (clone $base)->count(),
            'bale_linked' => (clone $base)->whereNotNull('bale_user_id')->count(),
            'bale_customers' => (clone $base)->whereIn('id', Order::query()
                ->whereIn('source', ['bale_bot', 'bale_miniapp'])
                ->whereNotNull('customer_id')->distinct()->pluck('customer_id'))->count(),
        ];
    }
}
