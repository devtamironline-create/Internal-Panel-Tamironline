<?php

namespace Modules\Warehouse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWarehouseRole
{
    /**
     * Warehouse role hierarchy and permissions
     *
     * Roles:
     * - admin: Full system access
     * - warehouse_manager: مدیر انبار - Full warehouse access
     * - shipping_packing: مسئول ارسال و بسته‌بندی - Orders, packing, shipping
     * - preparation: آماده‌سازی - Queue and order preparation
     * - packing: بسته‌بندی - Packing station only
     * - courier: پیک - Courier management only
     * - viewer: مشاهده - Read-only access
     */
    protected array $rolePermissions = [
        'admin' => ['*'], // All permissions
        'warehouse_manager' => ['*'], // مدیر انبار - Full warehouse access
        'shipping_packing' => [
            // مسئول ارسال و بسته‌بندی - Full operational access
            'dashboard', 'queue', 'orders.*', 'packing.*', 'courier.*',
            'orders.index', 'orders.show', 'orders.print', 'orders.print-amadast',
            'orders.update-internal-status', 'orders.mark-packed', 'orders.update-weight',
            'orders.assign-courier', 'orders.mark-shipped', 'orders.send-to-amadast',
            'orders.update-tracking', 'floating-orders'
        ],
        'preparation' => [
            'dashboard', 'queue', 'orders.index', 'orders.show', 'orders.print',
            'orders.print-amadast', 'orders.update-internal-status', 'floating-orders'
        ],
        'packing' => [
            'dashboard', 'queue', 'packing.*', 'orders.show', 'orders.print',
            'orders.print-amadast', 'orders.mark-packed', 'orders.update-weight', 'floating-orders'
        ],
        'courier' => [
            'dashboard', 'courier.*', 'orders.show', 'orders.assign-courier',
            'orders.mark-shipped', 'floating-orders'
        ],
        'viewer' => [
            'dashboard', 'orders.index', 'orders.show'
        ],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->denyAccess($request, 'لطفاً وارد شوید');
        }

        // Get user's warehouse role
        $userRole = $user->warehouse_role ?? 'viewer';

        // Admin and warehouse_manager have access to everything
        if ($userRole === 'admin' || $userRole === 'warehouse_manager' || $user->role === 'admin') {
            return $next($request);
        }

        // If a specific role is required, check it
        if ($requiredRole && $userRole !== $requiredRole && $userRole !== 'admin') {
            return $this->denyAccess($request, 'شما دسترسی به این بخش را ندارید');
        }

        // Check route-based permission
        $routeName = $request->route()->getName();
        if ($routeName && !$this->hasPermission($userRole, $routeName)) {
            return $this->denyAccess($request, 'شما دسترسی به این صفحه را ندارید');
        }

        return $next($request);
    }

    /**
     * Check if a role has permission for a route
     */
    protected function hasPermission(string $role, string $routeName): bool
    {
        $permissions = $this->rolePermissions[$role] ?? [];

        // Remove 'warehouse.' prefix for matching
        $routeName = str_replace('warehouse.', '', $routeName);

        foreach ($permissions as $permission) {
            if ($permission === '*') {
                return true;
            }

            // Wildcard matching (e.g., 'packing.*' matches 'packing.index')
            if (str_ends_with($permission, '.*')) {
                $prefix = substr($permission, 0, -2);
                if (str_starts_with($routeName, $prefix)) {
                    return true;
                }
            }

            if ($permission === $routeName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deny access to the request
     */
    protected function denyAccess(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->route('warehouse.dashboard')
            ->with('error', $message);
    }

    /**
     * Check if user can see floating orders panel
     */
    public static function canSeeFloatingOrders($user): bool
    {
        if (!$user) return false;

        $userRole = $user->warehouse_role ?? null;
        if (!$userRole) return false;

        // Roles that can see floating orders
        $allowedRoles = ['admin', 'warehouse_manager', 'shipping_packing', 'preparation', 'packing', 'courier'];

        return in_array($userRole, $allowedRoles) || $user->role === 'admin';
    }

    /**
     * Get role label in Persian
     */
    public static function getRoleLabel(string $role): string
    {
        return match($role) {
            'admin' => 'مدیر سیستم',
            'warehouse_manager' => 'مدیر انبار',
            'shipping_packing' => 'مسئول ارسال و بسته‌بندی',
            'preparation' => 'آماده‌سازی',
            'packing' => 'بسته‌بندی',
            'courier' => 'پیک',
            'viewer' => 'مشاهده‌کننده',
            default => $role,
        };
    }
}
