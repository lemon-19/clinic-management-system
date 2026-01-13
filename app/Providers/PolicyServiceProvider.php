<?php

namespace App\Providers;

use App\Models\VitalSign;
use App\Policies\VitalSignPolicy;
use Illuminate\Support\ServiceProvider;

class PolicyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register policies
        \Illuminate\Support\Facades\Gate::policy(VitalSign::class, VitalSignPolicy::class);
    }
}