<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // پروفایل با «نام خانوادگی» کامل تلقی می‌شود (نام اختیاری است).
        $isProfileComplete = ! empty($this->last_name);
        // full_name با حذفِ تکرارِ نام خانوادگی ساخته می‌شود و هرگز شماره موبایل
        // نیست؛ اگر نامی نباشد null (تا فرانت خودش تصمیم بگیرد).
        $fullName = $this->resource->composedName();

        return [
            'id' => (int) $this->id,
            'mobile' => $this->mobile,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $fullName,
            'email' => $this->email,
            'avatar_url' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'is_profile_complete' => $isProfileComplete,
            'mobile_verified_at' => $this->mobile_verified_at?->toIso8601String(),
            'subscription' => $this->subscription,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
