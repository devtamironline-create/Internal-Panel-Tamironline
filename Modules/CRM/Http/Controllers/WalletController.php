<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\WalletService;

class WalletController extends Controller
{
    public function __construct(protected WalletService $wallet)
    {
    }

    public function index(Request $request)
    {
        $technicianId = $request->integer('technician_id');
        $type = $request->string('type')->toString();

        $transactions = WalletTransaction::with(['technician', 'order', 'invoice', 'creator'])
            ->when($technicianId, fn ($q) => $q->where('technician_id', $technicianId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $technicians = Technician::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'wallet_balance']);

        return view('crm::wallet.index', [
            'transactions' => $transactions,
            'technicians' => $technicians,
            'technicianId' => $technicianId,
            'type' => $type,
            'types' => WalletTxType::options(),
        ]);
    }

    public function show(Technician $technician)
    {
        $transactions = WalletTransaction::with(['order', 'invoice', 'creator'])
            ->where('technician_id', $technician->id)
            ->latest()
            ->paginate(30);

        return view('crm::wallet.show', [
            'technician' => $technician,
            'transactions' => $transactions,
            'types' => WalletTxType::options(),
        ]);
    }

    public function storeTransaction(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'type' => 'required|in:reward,penalty,payout,credit,adjustment',
            'amount' => 'required|integer|min:1',
            'direction' => 'required_if:type,adjustment|in:credit,debit',
            'note' => 'nullable|string|max:1000',
        ]);

        $type = WalletTxType::from($validated['type']);

        $amount = (int) $validated['amount'];
        $sign = $type->sign();

        if ($type === WalletTxType::Adjustment) {
            $sign = $validated['direction'] === 'credit' ? +1 : -1;
        }

        $this->wallet->recordTransaction(
            technician: $technician,
            type: $type,
            amount: $amount * $sign,
            note: $validated['note'] ?? null,
            createdBy: auth()->id(),
        );

        return back()->with('success', 'تراکنش ثبت شد.');
    }
}
