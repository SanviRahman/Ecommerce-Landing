<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin-only', function ($user) {
            return isset($user->role) && $user->role === 'admin';
        });

        Gate::define('admin-or-employee', function ($user) {
            return isset($user->role) && in_array($user->role, ['admin', 'employee'], true);
        });
    }
}