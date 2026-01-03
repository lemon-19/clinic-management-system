<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $p = User::paginate($request->query('per_page', 15));

        return response()->json([
            'data' => $p->items(),
            'links' => ['self' => url()->current()],
            'meta' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($request->user())->withProperties($user->toArray())->log('created user');
        }

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($request->user())->withProperties($user->getChanges())->log('updated user');
        }

        return response()->json($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $user->delete();

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($request->user())->withProperties($user->toArray())->log('deleted user');
        }

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($request->user())->withProperties($user->toArray())->log('restored user');
        }

        return response()->json($user);
    }

    public function changePassword(\App\Http\Requests\ChangePasswordRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();

        // allow user to change own password or admin, or request bearer token belongs to this user
        $bt = request()->bearerToken();
        $btOk = false;
        if ($bt) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($bt);
            $btOk = $pat && $pat->tokenable_type === User::class && $pat->tokenable_id === $user->id;
        }

        if (! ($actor->id === $user->id || ($actor->user_type ?? '') === 'admin' || $btOk)) {
            \Log::debug('ChangePassword forbidden', ['actor_id' => $actor->id ?? null, 'route_user_id' => $user->id, 'bearer' => substr($bt ?: '', 0, 16), 'btOk' => $btOk]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user->password = bcrypt($request->input('password'));
        $user->save();

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($request->user())->withProperties(['password_changed' => true])->log('changed password');
        }

        return response()->json($user);
    }

    public function changeMyPassword(\App\Http\Requests\ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->password = bcrypt($request->input('password'));
        $user->save();

        if (function_exists('activity')) {
            activity()->performedOn($user)->causedBy($user)->withProperties(['password_changed' => true])->log('changed password');
        }

        return response()->json($user);
    }
}

