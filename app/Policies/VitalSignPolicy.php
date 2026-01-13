<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VitalSign;

class VitalSignPolicy
{
    /**
     * Determine if the user can view vital signs
     */
    public function view(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('view_vital_signs');
    }

    /**
     * Determine if the user can view any vital signs
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_vital_signs');
    }

    /**
     * Determine if the user can create vital signs
     */
    public function create(User $user): bool
    {
        return $user->can('create_vital_signs');
    }

    /**
     * Determine if the user can update vital signs
     */
    public function update(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('update_vital_signs');
    }

    /**
     * Determine if the user can delete vital signs
     */
    public function delete(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('delete_vital_signs');
    }
}