<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MaintenanceRoutesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_maintenance_get_requests_never_run_commands(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->actingAs($admin);

        foreach (['clear-cache', 'optimize', 'storage-link', 'sync-pathao-statuses', 'sync-steadfast-statuses'] as $command) {
            $this->get('/command/' . $command)->assertStatus(405);
        }
    }

    public function test_http_database_migration_and_seeding_endpoints_are_absent(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->actingAs($admin);

        foreach (['migrate', 'seed', 'migrate-fresh', 'migrate-fresh-seed'] as $command) {
            $this->post('/command/' . $command)->assertNotFound();
        }
    }

    public function test_inactive_admin_is_redirected_from_common_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);
        $this->actingAs($admin)->get('/admin/profile')
            ->assertRedirect(route('admin.login'));
    }
}
