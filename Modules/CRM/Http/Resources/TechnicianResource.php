<?php

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Technician;

/**
 * شکلِ پروفایلِ تکنسین برای اپِ موبایل.
 *
 * @mixin Technician
 */
class TechnicianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = $this->trainingProgress();

        return [
            'id' => (int) $this->id,
            'name' => $this->display_name,
            'mobile' => $this->mobile,
            'avatar_url' => $this->img_personal ? storage_url($this->img_personal) : null,
            'status' => $this->status,
            // «اعتبار» کیف‌پول — همان ستونِ running sum که پنل ادمین نشان
            // می‌دهد. عمداً true_balance این‌جا نیست (کوئریِ جمعِ فاکتورها
            // لازم دارد)؛ آن را از GET /wallet بخوانید.
            'balance' => (int) ($this->wallet_balance ?? 0),
            'is_ready_for_delivery' => (bool) $this->ready_for_delivery,
            'training' => [
                'completed' => $this->isTrainingCompleted(),
                'watched' => (int) ($progress['watched'] ?? 0),
                'total' => (int) ($progress['total'] ?? 0),
                'remaining' => (int) ($progress['remaining'] ?? 0),
                'percent' => (int) ($progress['percent'] ?? 0),
            ],
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
