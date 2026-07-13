<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\Technician;
use Symfony\Component\HttpFoundation\Response;

/**
 * تضمین می‌کند صاحبِ توکنِ احرازشده یک Technician است (نه Customer/User) و
 * حسابش حذف/غیرفعال نشده. جلوی استفادهٔ توکنِ اپِ مشتری روی endpointهای تکنسین
 * را می‌گیرد.
 */
class EnsureTechnician
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Technician || $user->trashed() || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی تکنسین لازم است.',
            ], 403);
        }

        return $next($request);
    }
}
