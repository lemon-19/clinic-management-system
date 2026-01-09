<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionController extends Controller
{
    /**
     * List all roles
     */
    public function listRoles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')
            ->with('permissions')
            ->paginate(15);

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'total' => $roles->total(),
                'per_page' => $roles->perPage(),
                'current_page' => $roles->currentPage(),
            ],
        ]);
    }

    /**
     * List all permissions
     */
    public function listPermissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'web')
            ->paginate(50);

        return response()->json([
            'data' => $permissions->items(),
            'meta' => [
                'total' => $permissions->total(),
                'per_page' => $permissions->perPage(),
                'current_page' => $permissions->currentPage(),
            ],
        ]);
    }

    /**
     * Get specific role with permissions
     * FIX: Accept ID and manually query with web guard
     */
    public function showRole($id): JsonResponse
    {
        // Explicitly find the role with web guard
        $role = Role::where('id', $id)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        // Load permissions relationship
        $role->load('permissions');
        
        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($request->user_id);
        
        // Find role with web guard
        $role = Role::where('name', $request->role)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $user->assignRole($role);

        return response()->json([
            'message' => "Role '{$request->role}' assigned to user",
            'user' => [
                'id' => $user->id,
                'name' => $this->getFullName($user),
                'roles' => $user->getRoleNames()->toArray(),
            ],
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($request->user_id);
        
        // Find role with web guard
        $role = Role::where('name', $request->role)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $user->removeRole($role);

        return response()->json([
            'message' => "Role '{$request->role}' removed from user",
            'user' => [
                'id' => $user->id,
                'name' => $this->getFullName($user),
                'roles' => $user->getRoleNames()->toArray(),
            ],
        ]);
    }

    /**
     * Get user's roles and permissions
     * FIX: Ensure user is loaded properly and roles are retrieved
     */
    public function getUserPermissions($id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        // Refresh the user to ensure we have fresh data from the database
        $user->refresh();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $this->getFullName($user),
                'email' => $user->email,
            ],
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Grant permission to role
     */
    public function grantPermissionToRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'exists:roles,name'],
            'permission' => ['required', 'exists:permissions,name'],
        ]);

        // Find role and permission with web guard
        $role = Role::where('name', $request->role)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $permission = Permission::where('name', $request->permission)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $role->givePermissionTo($permission);

        return response()->json([
            'message' => "Permission '{$request->permission}' granted to role '{$request->role}'",
            'role' => [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Revoke permission from role
     */
    public function revokePermissionFromRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'exists:roles,name'],
            'permission' => ['required', 'exists:permissions,name'],
        ]);

        // Find role and permission with web guard
        $role = Role::where('name', $request->role)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $permission = Permission::where('name', $request->permission)
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $role->revokePermissionTo($permission);

        return response()->json([
            'message' => "Permission '{$request->permission}' revoked from role '{$request->role}'",
            'role' => [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Helper method to get full name from user
     */
    private function getFullName(User $user): string
    {
        $parts = array_filter([
            $user->first_name,
            $user->middle_name,
            $user->last_name,
            $user->suffix_name,
        ]);

        $fullName = implode(' ', $parts);
        
        // If we have a full name, return it
        if (!empty($fullName)) {
            return $fullName;
        }
        
        // If no name fields, check for username
        if (!empty($user->username)) {
            return $user->username;
        }
        
        // Fallback to email (ensure it's not null)
        return $user->email ?? 'Unknown User';
    }
}