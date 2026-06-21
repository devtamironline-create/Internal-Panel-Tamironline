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
        // full_name فقط از نام/نام‌خانوادگی ساخته می‌شود — هرگز شماره موبایل
        // (accessor مدل برای جاهای دیگر fallback دارد، ولی این پاسخِ عمومی نباید
        // شماره را افشا کند).
        $fullName = trim((string) ($this->first_name ?? '').' '.(string) ($this->last_name ?? '')) ?: null;

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
