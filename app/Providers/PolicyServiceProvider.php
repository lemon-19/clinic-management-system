<?php

namespace App\Providers;

use App\Models\VitalSign;
use App\Policies\VitalSignPolicy;
use App\Models\Prescription;
use App\Policies\PrescriptionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PolicyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(VitalSign::class, VitalSignPolicy::class);
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
    }
}
