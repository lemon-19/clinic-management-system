<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // Load API routes if present (project uses web.php by default)
        if (file_exists($path = base_path('routes/api.php'))){
            require $path;
        }
    }
}
