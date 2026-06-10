<?php

namespace Modules\CustomerApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\CustomerAddress;

/**
 * @mixin CustomerAddress
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'label' => $this->label,
            'full_address' => $this->full_address,
            'state_id' => $this->province_id ? (int) $this->province_id : null,
            'state_name' => $this->whenLoaded('province', fn () => $this->province?->name),
            'city_id' => $this->city_id ? (int) $this->city_id : null,
            'city_name' => $this->whenLoaded('city', fn () => $this->city?->name),
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
