<?php

namespace App\Providers;

use App\Models\Order;
use App\Support\LocalHomepageAccess;
use App\Support\ProductionHomepageAccess;
use App\Observers\OrderBidirectionalSyncObserver;
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

        // P13/P14: the menu is test-only by default. A separate local-only
        // opt-in can show the editor for the seeded local database. This menu
        // is never the DB security boundary; the service checks actual identity.
        Gate::define('isolated-homepage-builder', static function ($user): bool {
            if ($user->role !== 'admin' || ! (bool) $user->is_active
                || config('database.default') !== 'mysql') {
                return false;
            }

            if (ProductionHomepageAccess::configuredForBuilder()) {
                return true;
            }
            if (! app()->environment(['local', 'testing'])) {
                return false;
            }

            $db = config('database.connections.mysql', []);

            $isolatedTest = is_array($db)
                && ($db['database'] ?? null) === 'p01_campaign_test'
                && ($db['username'] ?? null) === 'p01_tester'
                && ($db['host'] ?? null) === '127.0.0.1'
                && (int) ($db['port'] ?? 0) === 3306
                && empty($db['url']) && empty($db['unix_socket'])
                && empty($db['read']) && empty($db['write']);

            return $isolatedTest || LocalHomepageAccess::configuredForBuilder();
        });

        Order::observe(OrderBidirectionalSyncObserver::class);
    }
}
