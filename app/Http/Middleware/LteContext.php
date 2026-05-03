<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class LteContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $context = 'admin'): Response
    {
        if ($context === 'admin') {
            Config::set('adminlte.dashboard_url', 'admin');
            Config::set('adminlte.login_url', 'admin/login');
            Config::set('adminlte.logout_url', 'admin/logout');
            Config::set('adminlte.register_url', 'admin/register');
            Config::set('adminlte.password_reset_url', 'admin/password/reset');
            Config::set('adminlte.password_email_url', 'admin/password/email');
            Config::set('adminlte.profile_url', 'admin/profile');
        }

        return $next($request);
    }
}