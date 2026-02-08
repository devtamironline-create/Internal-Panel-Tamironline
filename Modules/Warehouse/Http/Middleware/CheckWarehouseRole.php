<?php

namespace Modules\Warehouse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWarehouseRole
{
    /**
     * Warehouse role hierarchy and permissions
     */
    protected array $rolePermissions = [
        'admin' => ['*'], // All permissions
        'preparation' => [
            'dashboard', 'queue', 'orders.index', 'orders.show', 'orders.print',
            'orders.print-amadast', 'orders.update-internal-status'
        ],
        'packing' => [
            'dashboard', 'queue', 'packing.*', 'orders.show', 'orders.print',
            'orders.print-amadast', 'orders.mark-packed', 'orders.update-weight'
        ],
        'courier' => [
            'dashboard', 'courier.*', 'orders.show', 'orders.assign-courier',
            'orders.mark-shipped'
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

        // Admin has access to everything
        if ($userRole === 'admin' || $user->role === 'admin') {
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
}
