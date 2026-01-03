<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function register(\App\Http\Requests\RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);

        $token = $user->createToken('api-token')->plainTextToken;

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($user)->withProperties($user->toArray())->log('registered user');
        }

        return response()->json(['token' => $token, 'user' => $user], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // Token management
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get();

        return response()->json($tokens);
    }

    public function revokeToken(Request $request, $id): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);

        $token->delete();

        return response()->json(null, 204);
    }
}
