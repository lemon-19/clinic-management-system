<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Check if user has the required role
        if (!$request->user() || !$request->user()->hasRole($role)) {
            return response()->json([
                'message' => 'Unauthorized - Role not assigned',
                'role_required' => $role,
            ], 403);
        }

        return $next($request);
    }
}
