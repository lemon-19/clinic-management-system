<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization') ?? '';

        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);

            $row = ApiToken::where('token', $token)->first();

            if ($row) {
                // set authenticated user
                Auth::loginUsingId($row->user_id);
                $row->update(['last_used_at' => now()]);
            }
        }

        if (! Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
