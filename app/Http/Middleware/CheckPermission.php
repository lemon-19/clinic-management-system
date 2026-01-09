<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Check if user has the required permission
        if (!$request->user() || !$request->user()->hasPermissionTo($permission)) {
            return response()->json([
                'message' => 'Unauthorized - Permission not granted',
                'permission_required' => $permission,
            ], 403);
        }

        return $next($request);
    }
}