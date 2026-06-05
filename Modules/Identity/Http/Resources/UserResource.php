<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isProfileComplete = ! empty($this->first_name);

        return [
            'id' => (int) $this->id,
            'mobile' => $this->mobile,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name ?: null,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'is_profile_complete' => $isProfileComplete,
            'mobile_verified_at' => $this->mobile_verified_at?->toIso8601String(),
            'roles' => $this->roles?->pluck('name')->all() ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
