<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\ClinicDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Clinic $clinic): bool
    {
        // Admins or staff of the clinic
        return $user->hasRole('admin') || $user->clinicStaff()->where('clinic_id', $clinic->id)->exists();
    }

    public function upload(User $user, Clinic $clinic): bool
    {
        // Permit clinic owner/staff or admin to upload
        return $user->hasRole('admin') || $user->clinicStaff()->where('clinic_id', $clinic->id)->exists();
    }

    public function view(User $user, ClinicDocument $document): bool
    {
        return $user->hasRole('admin') || $user->clinicStaff()->where('clinic_id', $document->clinic_id)->exists();
    }

    public function download(User $user, ClinicDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function review(User $user, ClinicDocument $document): bool
    {
        return $user->hasRole('admin');
    }
}
