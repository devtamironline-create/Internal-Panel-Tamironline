<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isMobileVerified()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'شماره موبایل تایید نشده است'], 403);
            }
            
            return redirect()->route('login')->with('error', 'لطفاً ابتدا شماره موبایل خود را تایید کنید');
        }

        return $next($request);
    }
}
