<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class DatabaseMigrationCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_open_database_migration_page(): void
    {
        $this->get('/command/database-migrations')->assertRedirect();
    }

    public function test_employee_cannot_open_database_migration_page(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE, 'is_active' => true]);
        $this->actingAs($employee)->get('/command/database-migrations')->assertForbidden();
    }

    public function test_status_preview_is_read_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        Artisan::shouldReceive('call')->with('migrate:status')->once()->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Pending 2026_10_29_100001_create_order_purge_audits');

        $this->actingAs($admin)->get('/command/database-migrations')
            ->assertOk()->assertSee('MIGRATE AUDIT');
    }

    public function test_post_requires_preview_and_exact_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->actingAs($admin)->post('/command/database-migrations', [
            'scope' => 'all',
            'confirmation' => 'MIGRATE',
            'current_password' => 'not-important',
            'backup_confirmed' => '1',
        ])->assertSessionHasErrors('confirmation');
    }

    public function test_confirmed_audit_scope_calls_only_approved_file(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'password' => Hash::make('correct-admin-password'),
        ]);
        $status = 'Pending 2026_10_29_100001_create_order_purge_audits';

        Artisan::shouldReceive('call')->with('migrate:status')->twice()->andReturn(0);
        Artisan::shouldReceive('output')->times(3)->andReturn($status, $status, 'Migrated');
        Artisan::shouldReceive('call')->once()->with('migrate', Mockery::on(static fn ($options): bool =>
            $options['--path'] === 'database/migrations/2026_10_29_100001_create_order_purge_audits.php'
            && $options['--force'] === true
            && $options['--no-interaction'] === true
        ))->andReturn(0);

        $lock = Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once()->andReturn(true);
        Cache::shouldReceive('lock')->with('admin:database-migrations', 900)->once()->andReturn($lock);

        $this->actingAs($admin)->get('/command/database-migrations')->assertOk();
        $this->post('/command/database-migrations', [
            'scope' => 'audit',
            'confirmation' => 'MIGRATE AUDIT',
            'current_password' => 'correct-admin-password',
            'backup_confirmed' => '1',
        ])->assertRedirect(route('command.database-migrations.index'));
    }
}
