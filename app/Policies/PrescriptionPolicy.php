<?php
// ============================================
// app/Policies/PrescriptionPolicy.php
// ============================================

namespace App\Policies;

use App\Models\User;
use App\Models\Prescription;

class PrescriptionPolicy
{
    /**
     * Determine if the user can view prescription
     */
    public function view(User $user, Prescription $prescription): bool
    {
        return $user->can('view_prescriptions');
    }

    /**
     * Determine if the user can view any prescription
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_prescriptions');
    }

    /**
     * Determine if the user can create prescription
     */
    public function create(User $user): bool
    {
        return $user->can('create_prescriptions');
    }

    /**
     * Determine if the user can update prescription
     */
    public function update(User $user, Prescription $prescription): bool
    {
        return $user->can('update_prescriptions');
    }

    /**
     * Determine if the user can delete prescription
     */
    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->can('delete_prescriptions');
    }
}
?>