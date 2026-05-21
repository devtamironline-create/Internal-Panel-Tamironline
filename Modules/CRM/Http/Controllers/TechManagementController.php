<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;

/**
 * مدیریت گروهی تکنسین‌ها — لیست با ادیت inline:
 *   - مانده کیف‌پول
 *   - حالت محاسبه (50/50 vs 70/30 vs custom)
 *   - حذف (soft delete → trash)
 *
 * Trash نمایش جدا با امکان restore.
 */
class TechManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->input('q', ''));
        $query = Technician::query()
            ->orderBy('firstname_tech')
            ->orderBy('first_name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('firstname_tech', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');
            });
        }

        $techs = $query->paginate(50)->withQueryString();
        $trashedCount = Technician::onlyTrashed()->count();

        return view('crm::tech-manage.index', compact('techs', 'search', 'trashedCount'));
    }

    /** آپدیت یک تکنسین خاص. */
    public function update(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'wallet_balance' => 'nullable|integer',
            'percent' => 'nullable|integer|min:0|max:100',
            'tech_per_of_all' => 'nullable|integer|min:0|max:100',
            'type_of_calc_tech' => 'nullable|in:,external,internal,1',
        ]);

        $update = [];

        // wallet
        if (array_key_exists('wallet_balance', $validated) && $validated['wallet_balance'] !== null) {
            $newBalance = (int) $validated['wallet_balance'];
            if ($newBalance !== (int) $technician->wallet_balance) {
                // wallet_txs قبلی پاک و opening balance جدید
                DB::table('crm_tech_wallet_transactions')
                    ->where('technician_id', $technician->id)
                    ->delete();

                $type = $newBalance >= 0 ? WalletTxType::WalletCharge->value : WalletTxType::Adjustment->value;
                DB::table('crm_tech_wallet_transactions')->insert([
                    'technician_id' => $technician->id,
                    'order_id' => null,
                    'invoice_id' => null,
                    'wp_id' => null,
                    'type' => $type,
                    'amount' => $newBalance,
                    'balance_after' => $newBalance,
                    'note' => 'تنظیم دستی مانده توسط ادمین به ' . number_format($newBalance) . ' تومان',
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $update['wallet_balance'] = $newBalance;
            }
        }

        // percent / calc
        if (array_key_exists('percent', $validated) && $validated['percent'] !== null) {
            $update['percent'] = (int) $validated['percent'];
        }
        if (array_key_exists('tech_per_of_all', $validated) && $validated['tech_per_of_all'] !== null) {
            $update['tech_per_of_all'] = (int) $validated['tech_per_of_all'];
        }
        if (array_key_exists('type_of_calc_tech', $validated)) {
            $val = $validated['type_of_calc_tech'];
            $update['type_of_calc_tech'] = ($val === 'internal' || $val === '1') ? '1' : '';
        }

        if (! empty($update)) {
            $update['updated_at'] = now();
            DB::table('crm_technicians')->where('id', $technician->id)->update($update);
        }

        return back()->with('success', "✓ تکنسین «{$technician->firstname_tech}» به‌روز شد.");
    }

    /** Soft delete — به trash می‌رود. */
    public function destroy(Technician $technician)
    {
        $technician->delete();
        return back()->with('success', "✓ «{$technician->firstname_tech}» به trash منتقل شد.");
    }

    /** نمایش trash. */
    public function trash()
    {
        $techs = Technician::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(50);

        return view('crm::tech-manage.trash', compact('techs'));
    }

    /** Restore از trash. */
    public function restore(int $id)
    {
        $tech = Technician::onlyTrashed()->findOrFail($id);
        $tech->restore();
        return back()->with('success', "✓ «{$tech->firstname_tech}» از trash بازگردانده شد.");
    }

    /** صفر کردن همه کیف‌پول‌ها. */
    public function zeroAllWallets(Request $request)
    {
        if (! $request->boolean('confirm')) {
            return back()->with('error', 'برای تأیید، چک‌باکس تأیید را بزن.');
        }

        $count = 0;
        Technician::chunk(100, function ($chunk) use (&$count) {
            foreach ($chunk as $tech) {
                if ((int) $tech->wallet_balance === 0
                    && DB::table('crm_tech_wallet_transactions')->where('technician_id', $tech->id)->count() === 0) {
                    continue;
                }

                DB::table('crm_tech_wallet_transactions')
                    ->where('technician_id', $tech->id)
                    ->delete();

                DB::table('crm_tech_wallet_transactions')->insert([
                    'technician_id' => $tech->id,
                    'order_id' => null,
                    'invoice_id' => null,
                    'wp_id' => null,
                    'type' => WalletTxType::WalletCharge->value,
                    'amount' => 0,
                    'balance_after' => 0,
                    'note' => 'صفر شدن دسته‌جمعی توسط ادمین',
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('crm_technicians')
                    ->where('id', $tech->id)
                    ->update(['wallet_balance' => 0, 'updated_at' => now()]);

                $count++;
            }
        });

        // فاکتورها هم superseded تا invoice_debt = 0
        DB::table('crm_invoices')
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now(), 'updated_at' => now()]);

        return back()->with('success', "✓ کیف‌پول {$count} تکنسین صفر شد + همه فاکتورها superseded شدند.");
    }
}
