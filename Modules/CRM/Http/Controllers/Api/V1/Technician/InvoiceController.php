<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Invoice;

/**
 * فاکتورهای تکنسین — لیست + آمار. هم‌ترازِ Tech\DashboardController::invoices.
 */
class InvoiceController extends Controller
{
    /** GET /v1/technician/invoices?status=&page= */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();
        $base = Invoice::query()->where('technician_id', $tech->id);

        $stats = [
            'count' => (int) (clone $base)->count(),
            'total_sum' => (int) (clone $base)->sum('total_amount'),
            'tech_share' => (int) (clone $base)->sum('tech_share'),
            'company_share' => (int) (clone $base)->sum('company_share'),
        ];

        $query = (clone $base)->with(['order:id,order_code', 'customer:id,first_name,last_name,mobile']);
        $status = (string) $request->query('status', '');
        if (in_array($status, ['draft', 'issued', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $invoices = $query->latest('issued_at')->latest('id')->paginate(15)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => collect($invoices->items())->map(fn (Invoice $inv) => [
                'id' => (int) $inv->id,
                'invoice_code' => $inv->invoice_code,
                'status' => $inv->status,
                'collection_method' => $inv->collection_method,
                'collection_method_label' => $inv->collectionMethodLabel(),
                'total_amount' => (int) $inv->total_amount,
                'tech_share' => (int) $inv->tech_share,
                'company_share' => (int) $inv->company_share,
                'order_code' => $inv->order?->order_code,
                'customer_name' => $inv->customer?->display_name,
                'issued_at' => $inv->issued_at?->utc()->toIso8601String(),
                'created_at' => $inv->created_at?->utc()->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
            'stats' => $stats,
        ]);
    }
}
