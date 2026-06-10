<?php

namespace Modules\CustomerApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\OrderReview;
use Modules\CRM\Models\ReviewTag;

/**
 * @mixin OrderReview
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'rating' => (int) $this->rating,
            'criteria' => $this->criteria ?: null,
            'comment' => $this->comment,
            'would_recommend' => $this->would_recommend,
            'status' => $this->status,
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn (ReviewTag $t) => [
                    'id' => (int) $t->id,
                    'slug' => $t->slug,
                    'label' => $t->label,
                    'type' => $t->type,
                    'icon' => $t->icon,
                ])->values()->all();
            }, []),
            'submitted_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
